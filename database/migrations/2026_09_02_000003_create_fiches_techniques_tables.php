<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fiche technique de diagnostic commercial.
 *
 * Les agents la remplissent chez le prospect. Les colonnes portent ce sur quoi
 * l'ERP filtre, trie et compte ; le reste du questionnaire vit en JSON.
 * Une table à quatre-vingts colonnes imposerait une migration à chaque
 * question ajoutée, pour des champs que personne ne requête individuellement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiches_techniques', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            // ── Identification : ce qui sert à chercher et regrouper ──
            $table->string('nom_organisation');
            $table->string('nom_commercial')->nullable();
            $table->string('type_organisation');
            $table->string('secteur')->nullable();
            $table->string('commune')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('taille_employes')->nullable();

            // ── Contact rencontré ──
            $table->string('contact_nom')->nullable();
            $table->string('contact_fonction')->nullable();
            $table->string('contact_telephone')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('est_decideur')->nullable();
            $table->string('decideur_nom')->nullable();
            $table->string('decideur_contact')->nullable();

            // ── Qualification : le cœur du pilotage commercial ──
            // 0 à 4 sur chaque axe ; leur somme classe le portefeuille.
            $table->unsignedTinyInteger('score_besoin')->default(0);
            $table->unsignedTinyInteger('score_potentiel')->default(0);
            $table->boolean('rendez_vous_possible')->nullable();
            $table->string('statut')->default('nouveau');
            $table->string('prochaine_action')->nullable();
            $table->date('date_relance')->nullable();

            $table->string('agent')->nullable();
            $table->string('responsable_assigne')->nullable();

            // ── Le questionnaire ──
            // Sections 3 à 11 et 13 : cases à cocher multiples et texte libre,
            // consultés en bloc sur la fiche, jamais filtrés un par un.
            $table->json('reponses')->nullable();

            $table->text('observation_agent')->nullable();
            $table->timestamps();

            // Les trois axes de lecture de la liste : file d'attente par
            // statut, priorité par score, et relances à venir.
            $table->index(['statut', 'created_at']);
            $table->index(['score_besoin', 'score_potentiel']);
            $table->index('date_relance');
        });

        // Journal de suivi : chaque contact, note ou message laissé sur une
        // fiche. Séparé pour garder l'historique complet plutôt qu'un seul
        // champ de notes écrasé à chaque passage.
        Schema::create('fiche_suivis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiche_technique_id')->constrained('fiches_techniques')->cascadeOnDelete();
            $table->string('agent')->nullable();
            $table->string('type')->default('note');
            $table->text('message');
            // Ce que le prospect a répondu, quand il y a eu un échange.
            $table->text('reponse_prospect')->nullable();
            $table->string('statut_apres')->nullable();
            $table->timestamps();

            $table->index(['fiche_technique_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiche_suivis');
        Schema::dropIfExists('fiches_techniques');
    }
};
