<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — l'histoire de chaque objet, en AJOUT SEUL.
 *
 * Quand deux personnes réclament le même stand, quand un commerce dit n'avoir
 * jamais cédé le sien, quand un revendeur conteste une affectation : c'est ici
 * qu'on tranche. Une ligne ne se modifie ni ne se supprime — se tromper se
 * corrige par un nouvel événement, jamais en réécrivant le passé.
 *
 * Même principe que le journal de stock et le ledger MAGOCASH.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stand_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('stand_id')->constrained('tagtoa_stands')->cascadeOnDelete();

            $table->string('event', 24)->index();   // minted, allocated, claimed…
            $table->string('from_state', 20)->nullable();
            $table->string('to_state', 20)->nullable();

            // QUI a agi : system | admin | partner | business | public
            $table->string('actor_type', 16)->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('actor_name', 120)->nullable();
            $table->string('tenant_id', 64)->nullable();

            // Pour distinguer une réclamation légitime d'un balayage.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['stand_id', 'created_at'], 'tagtoa_stand_ev_obj');
            $table->index('created_at', 'tagtoa_stand_ev_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stand_events');
    }
};
