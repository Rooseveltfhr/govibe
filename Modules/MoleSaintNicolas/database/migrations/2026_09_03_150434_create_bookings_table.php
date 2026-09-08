<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('establishment_id')->constrained()->cascadeOnDelete();

            $table->string('guest_name');
            $table->string('guest_phone');
            $table->string('guest_email')->nullable();

            // Séjour (hôtel) : starts_on/ends_on. Réservation resto/bar : starts_on
            // + reservation_time, ends_on non renseigné.
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->time('reservation_time')->nullable();
            $table->unsignedSmallInteger('party_size')->nullable();

            $table->text('notes')->nullable();

            // Pas de paiement en ligne au MVP (brief §8) : confirmation manuelle
            // par l'établissement (téléphone/WhatsApp), suivie ici pour traçabilité.
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
