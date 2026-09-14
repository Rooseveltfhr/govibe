<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — une ligne par objet physique, pour toujours.
 *
 * Le QR et la puce sont gravés une fois et ne changent plus jamais ; c'est
 * cette ligne qui décide de ce qu'ils atteignent. Toute la valeur du produit
 * est là : un objet permanent, une destination modifiable.
 *
 * DEUX AXES D'ÉTAT séparés (voir Support/Stand/StandState) :
 *   • `physical_state` — où est l'objet, piloté par la logistique ;
 *   • `digital_state`  — ce que fait l'URL, piloté par le commerce.
 * Un stand VENDU et NON RÉCLAMÉ est l'état normal d'un objet qui dort dans un
 * carton. Les fondre en une chaîne unique produirait des états composés sans fin.
 *
 * DEUX PROPRIÉTÉS séparées, et c'est le pivot du modèle revendeur :
 *   • `holder_*`  — qui détient l'OBJET ;
 *   • `tenant_id` — qui détient le COMPTE.
 * Un revendeur détient cent stands sans contrôler aucun compte client.
 *
 * PAS de trait BelongsToTenant : un stand non réclamé n'appartient à aucun
 * commerce, et une portée automatique le rendrait invisible au moment précis
 * où il faut le trouver — au scan, avant activation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stands', function (Blueprint $table) {
            $table->id();

            $table->foreignId('batch_id')->constrained('tagtoa_stand_batches')->cascadeOnDelete();

            // Ce qui est IMPRIMÉ. Séquentiel et public : il est affiché sur une
            // table de restaurant, le garder secret n'aurait aucun sens.
            $table->string('public_id', 24)->unique();
            $table->unsignedInteger('serial');

            // L'AUTORITÉ, jamais en clair. Même règle que le PIN des employés
            // et l'UID des cartes : une fuite de la base ne donne aucun stand.
            $table->string('secret_hash', 60)->nullable();
            // Incrémenté à chaque réémission (panneau gratté en transit, perte
            // déclarée) : l'ancien code cesse de valoir sans toucher au reste.
            $table->unsignedTinyInteger('secret_version')->default(1);

            $table->string('physical_state', 16)->default('manufactured')->index();
            $table->string('digital_state', 16)->default('unclaimed')->index();

            // Qui détient l'OBJET : platform | distributor | reseller | business
            $table->string('holder_type', 16)->nullable();
            $table->unsignedBigInteger('holder_id')->nullable();

            // Qui détient le COMPTE. Null tant que personne n'a réclamé.
            $table->string('tenant_id', 64)->nullable()->index();

            // « Table 05 ». Un libellé, pas une entité : il n'a ni cycle de vie,
            // ni relations, et une table dédiée ajouterait une jointure à la
            // requête la plus fréquente du système.
            $table->string('location_label', 60)->nullable();

            // Vers quoi le scan mène : menu | links | pay… Extensible sans
            // réimprimer quoi que ce soit — c'est tout l'intérêt du produit.
            $table->string('target_module', 16)->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('last_scanned_at')->nullable();
            $table->unsignedInteger('scan_count')->default(0);

            $table->timestamps();

            // Les lectures réelles : le stand d'un commerce à un emplacement,
            // et l'inventaire d'un lot par état.
            $table->index(['tenant_id', 'location_label'], 'tagtoa_stands_place');
            $table->index(['batch_id', 'physical_state'], 'tagtoa_stands_lot');
            $table->index(['holder_type', 'holder_id'], 'tagtoa_stands_holder');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stands');
    }
};
