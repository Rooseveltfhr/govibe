<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA BOOKING — page de réservation (salon, clinique, consultant…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_booking_pages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('theme', 20)->default('light');
            $table->string('accent_color', 16)->default('#2cb809');
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('currency', 8)->default('HTG');
            $table->unsignedBigInteger('pay_page_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_booking_pages');
    }
};
