<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — la clé d'idempotence appartient à UNE caisse, pas à la planète.
 *
 * `client_uuid` est fourni par la caisse hors-ligne pour rejouer une vente sans
 * la compter deux fois. Il était contraint UNIQUE sur toute la table, alors que
 * le champ accepte n'importe quelle chaîne de 64 caractères — une caisse qui
 * numérote « 1 », « vente-42 » ou un horodatage entre donc en collision avec un
 * AUTRE commerce.
 *
 * Conséquences réelles de la collision, avant ce correctif :
 *   1. la vente du second commerce n'était jamais enregistrée (recette perdue) ;
 *   2. l'API lui répondait « ok » en lui renvoyant la référence du premier.
 *
 * L'unicité devient donc (terminal_id, client_uuid) : deux commerces peuvent
 * utiliser la même valeur sans se marcher dessus, et la protection anti-doublon
 * reste entière à l'intérieur d'une caisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $table) {
            $table->dropUnique('tagtoa_pos_sales_client_uuid_unique');
            $table->unique(['terminal_id', 'client_uuid'], 'tagtoa_pos_sales_terminal_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $table) {
            $table->dropUnique('tagtoa_pos_sales_terminal_client_uuid_unique');
            // L'unicité globale est restaurée telle qu'elle était. Si des doublons
            // légitimes entre commerces existent déjà, ce retour arrière échouera
            // — c'est voulu : mieux vaut refuser que perdre une vente.
            $table->unique('client_uuid', 'tagtoa_pos_sales_client_uuid_unique');
        });
    }
};
