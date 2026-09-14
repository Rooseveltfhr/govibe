<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — le journal du stock : chaque mouvement, avec sa raison et son auteur.
 *
 * Jusqu'ici le stock n'était qu'un nombre qu'on écrasait. Quand il ne
 * correspondait plus à l'étagère — et cela arrive tous les mois — personne ne
 * pouvait remonter le fil. Le patron voyait « 12 » hier et « 3 » aujourd'hui,
 * sans savoir si on avait vendu, cassé, volé, ou mal compté.
 *
 * Chaque ligne garde le stock AVANT et APRÈS, pas seulement l'écart. C'est ce
 * qui permet de repérer le point exact où le compte a divergé, même si un
 * mouvement a été enregistré hors ligne ou en retard.
 *
 * Le journal est en AJOUT SEUL : on ne modifie ni ne supprime une ligne. Se
 * tromper se corrige par un nouveau mouvement, jamais en réécrivant le passé —
 * même principe que le ledger MAGOCASH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stock_movements', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id')->index();

            // L'article, dans le catalogue partagé MENU + POS (voir CatalogRef).
            // Deux colonnes plutôt qu'une chaîne « menu:7 » : on filtre et on
            // joint dessus, une chaîne rendrait chaque rapport plus lent.
            $table->string('source', 8);              // menu | pos
            $table->unsignedBigInteger('product_id');

            // Nom figé le jour du mouvement. L'article peut être renommé ou
            // supprimé plus tard : l'historique doit rester lisible.
            $table->string('product_name', 160)->nullable();

            $table->string('type', 16)->index();      // voir Support/Inventory/MovementType

            // Signé : négatif = sortie, positif = entrée.
            $table->decimal('delta', 12, 3);
            // Nullable : un article dont le stock n'était pas suivi n'avait pas
            // d'« avant ». Mentir avec 0 fausserait le premier écart.
            $table->decimal('stock_before', 12, 3)->nullable();
            $table->decimal('stock_after', 12, 3)->nullable();

            // Coût unitaire d'une réception : c'est lui qui met à jour le prix
            // d'achat de l'article et alimente la valeur du stock.
            $table->decimal('unit_cost', 12, 2)->nullable();

            $table->unsignedBigInteger('supplier_id')->nullable()->index();

            // QUI. Un employé au comptoir, ou le patron depuis son écran.
            $table->unsignedBigInteger('staff_id')->nullable()->index();
            $table->string('actor_name', 120)->nullable();

            // D'où vient le mouvement : la vente qui l'a provoqué, la commande
            // en ligne… Permet de remonter du stock à l'argent.
            $table->string('origin_type', 24)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();

            // Ce que la personne a écrit. Obligatoire côté service pour les
            // motifs manuels : « perte » sans explication n'apprend rien.
            $table->string('reason', 240)->nullable();

            $table->timestamp('created_at')->nullable();

            // Les deux lectures réelles : l'historique d'UN article, et le
            // journal du commerce sur une période.
            $table->index(['tenant_id', 'source', 'product_id'], 'tagtoa_stock_mv_article');
            $table->index(['tenant_id', 'created_at'], 'tagtoa_stock_mv_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stock_movements');
    }
};
