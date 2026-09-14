<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — le rayon de l'article, et la date à laquelle il a été acheté.
 *
 * `category_id` : sans rayon, la grille de la caisse est un mur de boutons.
 * Nullable et sans contrainte étrangère : supprimer un rayon ne doit JAMAIS
 * empêcher de vendre les articles qui s'y trouvaient — ils redeviennent
 * simplement « sans rayon ».
 *
 * `purchased_at` : la date d'achat du lot. Elle ne sert pas à la vente, elle
 * sert à répondre à « depuis quand cette caisse de bière dort-elle ici ? » —
 * la question qui fait la différence entre un commerce qui tourne et un
 * commerce dont la trésorerie est immobilisée sur ses étagères.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_pos_products', 'category_id')) {
                $t->unsignedBigInteger('category_id')->nullable()->index();
            }
            if (! Schema::hasColumn('tagtoa_pos_products', 'purchased_at')) {
                $t->date('purchased_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_pos_products', function (Blueprint $t) {
            foreach (['category_id', 'purchased_at'] as $col) {
                if (Schema::hasColumn('tagtoa_pos_products', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
