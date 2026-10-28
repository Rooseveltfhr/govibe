<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reponses_enquete', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference', 30)->unique();

            // Dans quelle langue la personne a répondu. Les valeurs stockées
            // restent les mêmes clés dans les deux cas — cette colonne ne sert
            // qu'à relire ses réponses libres et à la recontacter comme il faut.
            $table->string('langue', 5)->default('fr');

            // ── L'activité ───────────────────────────────────────────────
            $table->string('entreprise', 190)->nullable();
            $table->string('type_activite', 40);
            $table->string('type_activite_autre', 150)->nullable();
            $table->string('ville', 120)->nullable();
            $table->string('departement', 30)->nullable();
            $table->string('effectif', 20)->nullable();
            $table->string('anciennete', 20)->nullable();

            // ── Comment l'activité est tenue ─────────────────────────────
            $table->json('outils')->nullable();
            $table->json('encaissements')->nullable();
            $table->string('connexion', 20)->nullable();
            $table->json('presences')->nullable();

            // ── Les difficultés : le cœur de l'enquête ───────────────────
            $table->json('difficultes')->nullable();
            $table->text('perte_de_temps')->nullable();
            $table->text('pertes')->nullable();
            $table->text('priorite')->nullable();

            // ── Ce qui intéresse ─────────────────────────────────────────
            $table->json('solutions')->nullable();
            $table->string('budget', 20)->nullable();

            // ── Suite à donner ───────────────────────────────────────────
            $table->boolean('souhaite_contact')->default(false);
            $table->string('nom_contact', 150)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email', 190)->nullable();

            $table->string('statut', 20)->default('nouvelle');
            $table->text('notes_internes')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            // Les deux lectures que l'équipe fera : la file à traiter, et le
            // dépouillement par type d'activité.
            $table->index(['statut', 'created_at']);
            $table->index(['type_activite', 'created_at']);
            // « Qui veut être rappelé » est la liste commerciale du formulaire.
            $table->index('souhaite_contact');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reponses_enquete');
    }
};
