<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Six questions de plus, demandées par Roosevelt pour rendre l'enquête
     * plus productive.
     *
     * Celle qui pèse le plus est « materiel » : aucune caisse, aucune
     * application et aucun site ne s'installe chez quelqu'un qui n'a ni
     * téléphone capable ni ordinateur. Sans elle, on proposerait des outils
     * que le répondant ne peut pas faire tourner.
     *
     * Toutes sont facultatives, et les réponses déjà reçues restent valides :
     * leurs nouvelles colonnes sont simplement vides.
     */
    public function up(): void
    {
        Schema::table('reponses_enquete', function (Blueprint $table) {
            // Taille réelle de l'activité : elle décide de l'outil qui convient.
            $table->string('points_vente', 20)->nullable()->after('anciennete');
            $table->string('volume', 20)->nullable()->after('points_vente');

            // Ce sur quoi un outil pourrait tourner.
            $table->json('materiel')->nullable()->after('outils');

            // Une tentative passée en dit plus long qu'une liste de souhaits.
            $table->string('essai', 20)->nullable()->after('presences');
            $table->text('essai_raison')->nullable()->after('essai');

            // Les objections, avant de construire l'offre.
            $table->json('freins')->nullable()->after('priorite');

            // Dans quel ordre rappeler.
            $table->string('echeance', 20)->nullable()->after('budget');
        });
    }

    public function down(): void
    {
        Schema::table('reponses_enquete', function (Blueprint $table) {
            $table->dropColumn([
                'points_vente', 'volume', 'materiel',
                'essai', 'essai_raison', 'freins', 'echeance',
            ]);
        });
    }
};
