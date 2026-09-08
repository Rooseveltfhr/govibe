<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formations payantes à inscription courte.
     *
     * Distinct de formations/inscriptions, qui portent le parcours Academy :
     * dix champs, QR code et feuille de présence. Ici l'inscription tient en
     * quatre réponses — un formulaire long fait abandonner quand la publicité
     * renvoie vers lui depuis un téléphone.
     */
    public function up(): void
    {
        Schema::create('sessions_formation', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('titre', 160);
            $table->string('sous_titre', 200)->nullable();
            $table->text('description')->nullable();

            $table->json('modules')->nullable();

            $table->decimal('prix', 10, 2);
            $table->string('devise', 8)->default('HTG');

            // Ce que le participant peut choisir. Une session uniquement en
            // ligne ne doit pas proposer le présentiel.
            $table->json('modes')->nullable();
            $table->string('lieu_presentiel', 200)->nullable();
            $table->string('precision_online', 200)->nullable();

            // Date affichée telle qu'écrite sur l'affiche, et date réelle pour
            // trier et fermer les inscriptions.
            $table->string('date_texte', 120)->nullable();
            $table->date('date_debut')->nullable();
            $table->string('heure_texte', 80)->nullable();

            $table->boolean('places_limitees')->default(false);
            $table->unsignedInteger('max_participants')->nullable();

            $table->string('whatsapp_contact', 40)->nullable();
            $table->string('flyer', 255)->nullable();
            $table->string('couleur', 9)->default('#DC2626');

            $table->boolean('inscriptions_ouvertes')->default(true);
            $table->boolean('actif')->default(true);
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->index(['actif', 'ordre']);
        });

        Schema::create('inscriptions_session', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            $table->foreignId('session_formation_id')->constrained('sessions_formation')->cascadeOnDelete();

            // Le strict nécessaire : de quoi rappeler la personne et savoir
            // comment elle suit.
            $table->string('nom_complet', 150);
            $table->string('whatsapp', 40);
            $table->string('mode', 20);

            // Montant figé : le prix de la session peut changer après coup.
            $table->decimal('montant', 10, 2)->nullable();
            $table->string('devise', 8)->default('HTG');
            $table->string('moyen_paiement', 60)->nullable();
            $table->string('moyen_paiement_nom', 80)->nullable();

            // La preuve vit sur le disque privé : une capture de transaction
            // porte des identifiants de compte.
            $table->string('fichier')->nullable();
            $table->string('fichier_nom_origine', 255)->nullable();
            $table->unsignedInteger('fichier_taille')->nullable();
            $table->string('fichier_mime', 80)->nullable();

            $table->string('statut', 20)->default('a_verifier');
            $table->text('commentaire_admin')->nullable();
            $table->string('verifiee_par', 120)->nullable();
            $table->timestamp('verifiee_le')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions_session');
        Schema::dropIfExists('sessions_formation');
    }
};
