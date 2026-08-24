<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La table portait uniquement les demandes de partenariat. Ces champs lui
     * ajoutent la vitrine publique : un partenaire accepté peut recevoir un
     * logo et être affiché sur /partenaires.
     */
    public function up(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            // Chemin relatif sur le disque « public » (storage/app/public).
            $table->string('logo')->nullable()->after('description');
            $table->string('site_web')->nullable()->after('logo');
            // Volontairement false par défaut : rien n'apparaît en ligne tant
            // qu'un administrateur ne l'a pas décidé explicitement.
            $table->boolean('affiche_public')->default(false)->after('site_web');
            $table->integer('ordre')->default(0)->after('affiche_public');
        });

        // La vitrine publique filtre sur affiche_public et trie par ordre.
        Schema::table('partenaires', function (Blueprint $table) {
            $table->index(['affiche_public', 'ordre'], 'partenaires_vitrine_index');
        });
    }

    public function down(): void
    {
        Schema::table('partenaires', function (Blueprint $table) {
            $table->dropIndex('partenaires_vitrine_index');
            $table->dropColumn(['logo', 'site_web', 'affiche_public', 'ordre']);
        });
    }
};
