<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — Sale items (lignes de vente, snapshot du produit)
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('tagtoa_pos_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('tagtoa_pos_products')->nullOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('qty')->default(1);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_sale_items');
    }
};
