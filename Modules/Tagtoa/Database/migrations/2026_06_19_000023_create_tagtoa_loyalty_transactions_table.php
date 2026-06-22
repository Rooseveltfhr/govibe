<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('tagtoa_loyalty_cards')->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained('tagtoa_loyalty_rewards')->nullOnDelete();
            $table->string('type'); // top_up|redeem
            $table->decimal('amount', 10, 2)->default(0);
            $table->integer('points_delta')->default(0);
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->integer('points_after')->nullable();
            $table->string('payment_method')->nullable();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_transactions');
    }
};
