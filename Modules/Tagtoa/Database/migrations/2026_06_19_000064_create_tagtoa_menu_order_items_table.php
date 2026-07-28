<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — lignes d'une commande (snapshot nom + prix au moment de l'achat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('tagtoa_menu_orders')->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained('tagtoa_menu_items')->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_order_items');
    }
};
