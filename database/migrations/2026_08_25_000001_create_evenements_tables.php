<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un événement = un formulaire d'inscription. Les administrateurs en
        // créent autant que nécessaire depuis l'ERP, sans toucher au code.
        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            // Sert d'URL partageable : /evenements/{slug}
            $table->string('slug')->unique();
            $table->string('sous_titre')->nullable();
            $table->text('description')->nullable();
            $table->string('lieu')->nullable();
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            // Proposé après inscription, et depuis la page de l'événement.
            $table->string('whatsapp_group_url')->nullable();
            // Un événement inactif reste consultable par son URL directe mais
            // disparaît de la liste et du sélecteur du formulaire.
            $table->boolean('actif')->default(true);
            // Ferme les inscriptions sans masquer l'événement.
            $table->boolean('inscriptions_ouvertes')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        Schema::create('evenement_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evenement_id')->constrained('evenements')->cascadeOnDelete();

            $table->string('prenom');
            $table->string('nom');
            $table->string('email');
            $table->string('whatsapp');
            $table->string('telephone')->nullable();

            $table->string('pays')->default('Haïti');
            $table->string('ville');
            $table->string('commune')->nullable();

            $table->string('profession')->nullable();
            $table->string('sexe')->nullable();
            $table->string('situation_matrimoniale')->nullable();
            $table->string('statut_actuel')->nullable();

            $table->text('motivation')->nullable();

            // Suivi côté organisateur.
            $table->boolean('presence_confirmee')->default(false);
            $table->text('notes_admin')->nullable();

            $table->timestamps();

            // Un même email ne s'inscrit qu'une fois par événement.
            $table->unique(['evenement_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenement_reservations');
        Schema::dropIfExists('evenements');
    }
};
