<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — menu digital (restaurant, club, lounge, hôtel, bar, café…).
 * Le commerçant vend produits & services via NFC/QR. Table préfixée tagtoa_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menus', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('type', 30)->default('restaurant'); // restaurant|bar|cafe|club|lounge|hotel|other
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('currency', 8)->default('HTG');
            $table->string('whatsapp', 40)->nullable();      // commande via WhatsApp
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->unsignedBigInteger('pay_page_id')->nullable(); // lien TAGTOA Pay
            $table->string('accent_color', 16)->default('#2cb809');
            $table->string('theme', 20)->default('light');    // light|dark
            $table->boolean('show_prices')->default(true);
            $table->boolean('ordering_enabled')->default(true); // commande WhatsApp
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menus');
    }
};
