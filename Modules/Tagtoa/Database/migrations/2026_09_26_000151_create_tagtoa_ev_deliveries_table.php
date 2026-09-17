<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA EVENT — journal des tentatives de livraison de billet (confirmation
 * d'achat, confirmation d'entrée) : un canal, un destinataire, un résultat.
 *
 * Ne remplace PAS la file `SendNotification` (partagée avec MENU et STORE,
 * générique) : celle-ci reste le chemin des alertes internes à
 * l'organisateur. Ce journal couvre spécifiquement ce que le CLIENT reçoit —
 * la seule chose qu'un marchand veut vraiment mesurer sous « statistiques de
 * livraison » : est-ce que mon acheteur a reçu son billet ?
 *
 * `ticket_id` nullable : une confirmation de commande peut couvrir plusieurs
 * billets à la fois (un envoi, plusieurs billets) — on la rattache alors à
 * l'ÉVÉNEMENT, pas à un billet précis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tagtoa_ev_tickets')->nullOnDelete();
            $table->string('context'); // order_confirmation | checkin_confirmation
            $table->string('channel'); // email | whatsapp
            $table->string('recipient');
            $table->string('status'); // sent | not_sent
            $table->timestamps();
            $table->index(['event_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_deliveries');
    }
};
