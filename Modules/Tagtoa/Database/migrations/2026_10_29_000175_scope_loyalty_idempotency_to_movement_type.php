<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA LOYALTY — la clé d'idempotence appartient à UN TYPE de mouvement.
 *
 * La contrainte posée la veille était (card_id, reference) : une référence
 * ne pouvait donc exister qu'UNE fois sur une carte, tous types confondus.
 * Or une même référence de commande a deux mouvements légitimes et
 * opposés : les points gagnés à l'achat, puis les mêmes points repris si
 * la commande est remboursée.
 *
 * Le piège n'était pas théorique, et il était silencieux : avec l'ancienne
 * portée, `redeem($card, $montant, ['reference' => $refCommande])` trouvait
 * la ligne du GAIN, la prenait pour un rejeu, et renvoyait « déjà fait » —
 * sans jamais débiter. Un remboursement aurait donc rendu l'argent au
 * client en laissant ses points intacts, et l'écran aurait affiché un
 * succès.
 *
 * L'unicité devient (card_id, type, reference) : chaque type de mouvement
 * reste idempotent pour lui-même, et les deux sens d'une même commande
 * cohabitent. MySQL autorise toujours plusieurs NULL, donc les mouvements
 * sans référence ne sont jamais concernés.
 *
 * L'ORDRE DES DEUX INSTRUCTIONS N'EST PAS UN DÉTAIL. `card_id` porte une clé
 * étrangère, et InnoDB exige un index utilisable sur la colonne référençante.
 * Quand un index composite couvrant `card_id` apparaît (c'était le cas de
 * l'ancien unique), MySQL peut supprimer en silence l'index implicite qu'il
 * avait créé pour la clé étrangère. L'ancien unique est alors le SEUL index
 * sur `card_id` : le supprimer en premier échoue avec l'erreur 1553
 * « Cannot drop index needed in a foreign key constraint ».
 *
 * SQLite — le moteur des tests — n'a pas cette règle : tout passait en test et
 * la migration aurait échoué en production. Or un `migrate` qui échoue fait
 * désactiver tout le module par remote-deploy.sh (rollback()). On pose donc le
 * NOUVEL index d'abord : la clé étrangère en a toujours un, quel que soit
 * l'état intermédiaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->unique(['card_id', 'type', 'reference'], 'tagtoa_loyalty_txn_card_type_reference_unique');
        });

        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique('tagtoa_loyalty_transactions_card_id_reference_unique');
        });
    }

    public function down(): void
    {
        // Même règle, dans l'autre sens : l'ancien index d'abord. Si deux
        // mouvements de types différents partagent déjà une référence, cet
        // ajout échouera — c'est voulu : mieux vaut refuser que fusionner un
        // gain et un remboursement sous une seule ligne.
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->unique(['card_id', 'reference'], 'tagtoa_loyalty_transactions_card_id_reference_unique');
        });

        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique('tagtoa_loyalty_txn_card_type_reference_unique');
        });
    }
};
