<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réservations LANDRY, le service de lavage.
     *
     * Le prototype gardait les réservations dans le navigateur du visiteur :
     * elles disparaissaient avec son cache et n'arrivaient jamais à l'équipe.
     * Elles vivent ici, en base, comme tout le reste.
     */
    public function up(): void
    {
        Schema::create('reservations_landry', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();

            // Le client
            $table->string('nom_complet', 150);
            $table->string('whatsapp', 40);
            $table->text('adresse');
            $table->string('point_repere', 255)->nullable();

            // Le service
            $table->string('mode_service', 20);
            $table->text('instructions_recuperation')->nullable();
            $table->string('mode_facturation', 20);
            $table->unsignedInteger('quantite_vetements')->nullable();
            $table->string('frequence', 60);

            // Le paiement
            $table->string('mode_paiement', 30);
            $table->boolean('accepte_frais_inscription');
            $table->decimal('frais_inscription', 10, 2)->nullable();
            $table->string('devise', 8)->default('HTG');

            $table->string('statut', 20)->default('nouvelle');
            $table->text('notes_internes')->nullable();
            $table->foreignId('traitee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traitee_le')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('created_at');
            $table->index('accepte_frais_inscription');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations_landry');
    }
};
