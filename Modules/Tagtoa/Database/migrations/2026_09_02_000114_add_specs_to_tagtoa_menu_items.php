<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — champs propres au type d'établissement.
 *
 * Un hôtel décrit une capacité, des lits et des équipements ; un bar un volume
 * et un degré d'alcool ; un restaurant un temps de préparation et des
 * allergènes. Une colonne par champ et par métier ferait grossir la table sans
 * fin et imposerait une migration à chaque nouveau type d'établissement.
 *
 * Ces valeurs sont donc stockées en JSON (`specs`), validées à l'écriture contre
 * le profil du type (voir BusinessProfile::sanitize) : rien d'inconnu n'entre.
 * La colonne s'appelle `specs` et non `attributes` : Eloquent utilise déjà
 * `$attributes` en interne, et un accesseur écrit plus tard y lirait le tableau
 * interne du modèle au lieu de la colonne.
 * Elles ne servent qu'à décrire l'article — jamais au calcul d'un prix, qui
 * reste sur la colonne `price`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_menu_items', 'specs')) {
                $table->json('specs')->nullable()->after('badge');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menu_items', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_menu_items', 'specs')) {
                $table->dropColumn('specs');
            }
        });
    }
};
