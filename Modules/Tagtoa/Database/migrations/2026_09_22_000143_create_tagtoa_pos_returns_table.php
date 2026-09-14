<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — les retours.
 *
 * ── Pourquoi un DOCUMENT, et pas une correction de la vente ──────────────
 *
 * La tentation est de rouvrir la vente et d'y retirer une ligne. Ce serait
 * réécrire une recette : le rapport Z de la journée changerait après coup, le
 * ticket remis au client ne correspondrait plus à rien, et plus personne ne
 * pourrait dire ce qui s'est réellement passé au comptoir. Dans un commerce où
 * le patron n'est pas derrière la caisse, c'est aussi la porte ouverte au vol :
 * on encaisse, on efface la ligne, on garde l'argent.
 *
 * Un retour est donc un document SÉPARÉ, daté, signé, qui pointe vers la vente
 * d'origine. La vente ne bouge jamais. La vérité, c'est la somme des deux.
 *
 * ── Ce que la table garantit ────────────────────────────────────────────
 *
 * `idempotency_key` unique : un double toucher sur un téléphone lent, ou une
 * connexion qui repart, ne rembourse pas deux fois. C'est la même protection
 * que sur les ventes — et elle compte davantage ici, puisque l'argent SORT.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_pos_returns')) {
            Schema::create('tagtoa_pos_returns', function (Blueprint $t) {
                $t->id();
                $t->string('tenant_id', 64)->index();
                $t->unsignedBigInteger('sale_id')->index();
                $t->unsignedBigInteger('terminal_id')->nullable()->index();
                $t->unsignedBigInteger('staff_id')->nullable();
                $t->string('reference', 40);
                // Montants FIGÉS au moment du retour, calculés côté serveur à
                // partir des prix gelés de la vente. Jamais recalculés depuis le
                // catalogue : un prix changé demain réécrirait un remboursement
                // d'hier.
                $t->decimal('subtotal', 14, 2)->default(0);
                $t->decimal('tax_total', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->string('currency', 10)->default('HTG');
                $t->string('reason', 160)->nullable();
                // Le motif du retour, tel que choisi — pour distinguer un
                // article défectueux d'un client qui change d'avis, et voir
                // lequel coûte vraiment de l'argent au commerce.
                $t->string('kind', 24)->default('customer');
                $t->boolean('restocked')->default(true);
                $t->string('idempotency_key', 64);
                $t->timestamp('returned_at')->nullable();
                $t->timestamps();

                // Deux envois identiques ne remboursent qu'une fois.
                $t->unique(['tenant_id', 'idempotency_key']);
            });
        }

        if (! Schema::hasTable('tagtoa_pos_return_items')) {
            Schema::create('tagtoa_pos_return_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('return_id')->index();
                // La ligne de vente d'origine : c'est elle qui plafonne ce qui
                // peut être rendu, en cumulant tous les retours passés.
                $t->unsignedBigInteger('sale_item_id')->index();
                $t->unsignedBigInteger('product_id')->nullable();
                $t->string('source', 16)->nullable();
                $t->string('name', 160);
                $t->decimal('price', 14, 2)->default(0);
                $t->float('qty')->default(0);
                $t->decimal('line_total', 14, 2)->default(0);
                $t->decimal('tax_amount', 14, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_return_items');
        Schema::dropIfExists('tagtoa_pos_returns');
    }
};
