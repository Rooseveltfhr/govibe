<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('tagtoa_ev_orders')->nullOnDelete();
            $table->foreignId('ticket_type_id')->nullable()->constrained('tagtoa_ev_ticket_types')->nullOnDelete();
            $table->string('code', 40)->unique();
            $table->string('holder_name')->nullable();
            $table->string('holder_phone')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->boolean('checked_in')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_tickets');
    }
};
