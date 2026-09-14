<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — la taxe FIGÉE sur la vente.
 *
 * Même principe que le prix d'achat sur la ligne : le taux est celui du jour de
 * l'encaissement, écrit une fois pour toutes. Si l'État relève la TCA l'an
 * prochain, les reçus de cette année ne doivent pas se recalculer tout seuls —
 * ce sont des pièces comptables, pas un tableau vivant.
 *
 * `tax_breakdown` garde le détail PAR TAUX. Dès qu'un commerce vend de
 * l'exonéré à côté du taxé, c'est ce que la déclaration demande, et un total
 * unique ne permettrait plus de le reconstituer des mois plus tard.
 *
 * `tax_inclusive` est copié sur la vente : changer la convention du commerce
 * ne doit pas retourner le sens des reçus déjà émis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_pos_sales', 'tax_total')) {
                $t->decimal('tax_total', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('tagtoa_pos_sales', 'tax_base')) {
                $t->decimal('tax_base', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('tagtoa_pos_sales', 'tax_inclusive')) {
                $t->boolean('tax_inclusive')->default(true);
            }
            if (! Schema::hasColumn('tagtoa_pos_sales', 'tax_label')) {
                $t->string('tax_label', 24)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_pos_sales', 'tax_breakdown')) {
                $t->json('tax_breakdown')->nullable();
            }
        });

        Schema::table('tagtoa_pos_sale_items', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_pos_sale_items', 'tax_rate')) {
                $t->decimal('tax_rate', 6, 3)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_pos_sale_items', 'tax_amount')) {
                $t->decimal('tax_amount', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_sales', function (Blueprint $t) {
            foreach (['tax_total', 'tax_base', 'tax_inclusive', 'tax_label', 'tax_breakdown'] as $c) {
                if (Schema::hasColumn('tagtoa_pos_sales', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
        Schema::table('tagtoa_pos_sale_items', function (Blueprint $t) {
            foreach (['tax_rate', 'tax_amount'] as $c) {
                if (Schema::hasColumn('tagtoa_pos_sale_items', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
