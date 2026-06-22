<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('tagtoa_pos_terminals')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('emoji', 8)->nullable();
            $table->string('color', 12)->default('#0055FF');
            $table->integer('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_products');
    }
};
