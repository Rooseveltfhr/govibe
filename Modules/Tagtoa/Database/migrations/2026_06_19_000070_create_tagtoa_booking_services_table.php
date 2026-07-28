<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA BOOKING — prestations réservables (durée + prix).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_page_id')->constrained('tagtoa_booking_pages')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_min')->default(30);
            $table->decimal('price', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_booking_services');
    }
};
