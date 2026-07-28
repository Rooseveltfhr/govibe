<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA STORE — boutique en ligne (e-commerce) : tagtoa.com/store/{alias}.
 * Catalogue de produits avec panier, commande WhatsApp et paiement TAGTOA Pay.
 * Remplace/consolide « store.tagtoa.com » DANS la plateforme (une seule admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('currency', 8)->default('HTG');
            $table->string('whatsapp', 40)->nullable();     // commande via WhatsApp
            $table->string('phone', 40)->nullable();
            $table->string('address')->nullable();
            $table->text('delivery_note')->nullable();       // livraison / retrait
            $table->unsignedBigInteger('pay_page_id')->nullable(); // lien TAGTOA Pay
            $table->string('accent_color', 16)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stores');
    }
};
