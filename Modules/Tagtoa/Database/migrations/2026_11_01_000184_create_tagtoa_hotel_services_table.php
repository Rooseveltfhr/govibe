<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA HÔTEL (H3) — le catalogue des services qu'un hôtel propose à ses clients
 * (ménage, serviettes, réception, maintenance, room service…).
 *
 * Un service appartient à UN commerce (`tenant_id`). Il n'est jamais supprimé (les demandes
 * des clients y seront rattachées en H5) : on le désactive. Le room service pointe vers un
 * Menu EXISTANT du même hôtel (`menu_id`) : pas de seconde carte parallèle.
 *
 * Rejouable : ne fait rien si la table existe déjà. Index nommé court (limite MySQL de 64).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_hotel_services')) {
            return;
        }

        Schema::create('tagtoa_hotel_services', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('kind', 30);
            $table->string('name', 80);
            $table->json('translations')->nullable();
            $table->string('description', 300)->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('team', 30)->nullable();
            $table->unsignedBigInteger('menu_id')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active'], 'hotel_services_tenant_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_hotel_services');
    }
};
