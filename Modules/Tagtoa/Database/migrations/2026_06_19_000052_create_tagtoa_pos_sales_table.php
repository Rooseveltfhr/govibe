<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('tagtoa_pos_terminals')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->json('payments')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->tinyInteger('status')->default(1);
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_sales');
    }
};
