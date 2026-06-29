<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA BOOKING — rendez-vous pris par les clients.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_page_id')->constrained('tagtoa_booking_pages')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('tagtoa_booking_services')->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('reference')->unique();
            $table->string('customer_name');
            $table->string('customer_phone', 40)->nullable();
            $table->string('customer_email')->nullable();
            $table->timestamp('starts_at');
            $table->string('status', 20)->default('pending')->index(); // pending|confirmed|completed|cancelled
            $table->text('note')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 8)->default('HTG');
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_bookings');
    }
};
