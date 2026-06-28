<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SITE — site web professionnel par abonnement (vitrine business).
 * Une table tagtoa_* ; contenus répétables (services, horaires, réseaux,
 * galerie) stockés en JSON pour la simplicité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('tagline')->nullable();
            $table->text('about')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('theme', 20)->default('light');     // light|dark
            $table->string('accent_color', 16)->default('#16A34A');
            // Contact
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('map_url')->nullable();
            // Blocs répétables (JSON)
            $table->json('services')->nullable();   // [{icon,title,desc}]
            $table->json('hours')->nullable();      // [{day,value}]
            $table->json('socials')->nullable();    // [{platform,url}]
            $table->json('gallery')->nullable();    // [path,…]
            // Intégrations TAGTOA (boutons)
            $table->unsignedBigInteger('menu_id')->nullable();
            $table->unsignedBigInteger('pay_page_id')->nullable();
            $table->unsignedBigInteger('link_page_id')->nullable();
            // Affichage
            $table->boolean('show_services')->default(true);
            $table->boolean('show_hours')->default(true);
            $table->boolean('show_gallery')->default(true);
            $table->boolean('show_contact')->default(true);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_sites');
    }
};
