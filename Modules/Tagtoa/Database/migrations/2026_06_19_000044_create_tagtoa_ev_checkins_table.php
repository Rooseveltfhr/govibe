<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tagtoa_ev_tickets')->cascadeOnDelete();
            $table->string('direction')->default('in');
            $table->string('method')->default('qr');
            $table->string('gate')->nullable();
            $table->string('client_uuid', 64)->nullable()->index();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_checkins');
    }
};
