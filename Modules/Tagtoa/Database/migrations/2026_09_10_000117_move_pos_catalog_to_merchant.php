<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — le catalogue appartient au COMMERCE, plus à la caisse.
 *
 * Avant : `tagtoa_pos_products.terminal_id`. Un commerce à deux caisses
 * saisissait donc ses produits deux fois, et un article créé sur la caisse 1
 * restait introuvable depuis la caisse 2. C'est aussi ce qui rendait le scan
 * de code-barres impossible à faire correctement : un code identifie un produit
 * du commerce, pas un produit d'une caisse.
 *
 * Après : le catalogue est celui du commerce. Les caisses le partagent, et la
 * vente continue d'enregistrer SUR QUELLE caisse elle a eu lieu.
 *
 * Migration NON DESTRUCTIVE, et volontairement SANS fusion des doublons.
 * Un commerce qui avait « Coca 75 » sur ses deux caisses se retrouve avec deux
 * lignes « Coca ». Les fusionner obligerait à réaffecter `sale_items.product_id`
 * — donc à réécrire l'historique des ventes déjà encaissées. On préfère deux
 * lignes visibles, que le marchand peut ranger lui-même, à un historique
 * silencieusement modifié.
 *
 * `terminal_id` est CONSERVÉ tel quel : il dit sur quelle caisse le produit a
 * été saisi, et l'ancien code continue de fonctionner. La colonne n'est pas
 * rendue facultative ici — SQLite (utilisé par les tests) refuse de retirer une
 * clé étrangère, et une chirurgie de colonne qui ne s'exécute pas de la même
 * façon en test et en production est exactement ce qu'il ne faut pas faire sur
 * une table qui porte des ventes.
 *
 * Conséquence à connaître : la clé étrangère supprime encore les produits en
 * cascade si l'on supprime une caisse. Aucune route ne permet cette suppression
 * aujourd'hui, et le modèle Terminal pose un garde-fou pour le jour où elle
 * existera (voir Terminal::deleting).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_pos_products', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id')->index();
            }
        });

        // Chaque produit reçoit le commerce de sa caisse d'origine.
        foreach (DB::table('tagtoa_pos_terminals')->select('id', 'tenant_id')->get() as $terminal) {
            DB::table('tagtoa_pos_products')
                ->where('terminal_id', $terminal->id)
                ->whereNull('tenant_id')
                ->update(['tenant_id' => $terminal->tenant_id]);
        }

    }

    public function down(): void
    {
        // On ne remet pas les produits sous une caisse : on ne saurait pas
        // lesquels y étaient, et ceux créés depuis n'y ont jamais appartenu.
        Schema::table('tagtoa_pos_products', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_pos_products', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
