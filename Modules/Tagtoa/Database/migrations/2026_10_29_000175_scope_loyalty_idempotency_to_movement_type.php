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
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique('tagtoa_loyalty_transactions_card_id_reference_unique');
            $table->unique(['card_id', 'type', 'reference'], 'tagtoa_loyalty_txn_card_type_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->dropUnique('tagtoa_loyalty_txn_card_type_reference_unique');
            // Retour à la portée large. Si deux mouvements de types
            // différents partagent déjà une référence, ce retour arrière
            // échouera — c'est voulu : mieux vaut refuser que fusionner un
            // gain et un remboursement sous une seule ligne.
            $table->unique(['card_id', 'reference'], 'tagtoa_loyalty_transactions_card_id_reference_unique');
        });
    }
};
