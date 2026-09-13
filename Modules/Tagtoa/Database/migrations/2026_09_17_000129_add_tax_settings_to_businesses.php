<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — la taxe du commerce, réglée une fois.
 *
 * Deux conventions d'affichage cohabitent dans le monde, et se tromper de sens
 * n'est pas un détail : soit le client paie 10 % de trop, soit le commerce paie
 * la taxe de sa poche à chaque vente. Le réglage est donc explicite, jamais
 * deviné — et il appartient au COMMERCE, pas au compte : une même personne peut
 * tenir une boutique assujettie et un service qui ne l'est pas.
 *
 * `tax_enabled` à faux par défaut : la majorité des petits commerces ne
 * facturent pas de taxe, et leur en ajouter une en silence à la mise à jour
 * serait la pire chose à faire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_businesses', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_businesses', 'tax_enabled')) {
                $t->boolean('tax_enabled')->default(false);
            }
            if (! Schema::hasColumn('tagtoa_businesses', 'tax_label')) {
                // TCA en Haïti, ITBIS en RD, TVA au Sénégal, GCT en Jamaïque.
                $t->string('tax_label', 24)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_businesses', 'tax_rate')) {
                $t->decimal('tax_rate', 6, 3)->nullable();
            }
            if (! Schema::hasColumn('tagtoa_businesses', 'tax_inclusive')) {
                // Vrai = le prix affiché est ce que le client paie (usage
                // haïtien et caribéen). Faux = la taxe s'ajoute à la caisse.
                $t->boolean('tax_inclusive')->default(true);
            }
            if (! Schema::hasColumn('tagtoa_businesses', 'tax_number')) {
                // Numéro fiscal (NIF, RCCM…) : un reçu professionnel le porte.
                $t->string('tax_number', 40)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_businesses', function (Blueprint $t) {
            foreach (['tax_enabled', 'tax_label', 'tax_rate', 'tax_inclusive', 'tax_number'] as $c) {
                if (Schema::hasColumn('tagtoa_businesses', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
