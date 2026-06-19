<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Ticket types (VIP, Standard, Gratuit…)
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('name');                         // VIP | Standard | Gratuit
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->nullable(); // null = illimité
            $table->unsignedInteger('sold')->default(0);
            $table->dateTime('sale_starts_at')->nullable();
            $table->dateTime('sale_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_ticket_types');
    }
};
