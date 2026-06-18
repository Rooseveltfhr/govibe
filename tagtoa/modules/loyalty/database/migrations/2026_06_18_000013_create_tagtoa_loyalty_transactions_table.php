<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA LOYALTY — Transactions
|--------------------------------------------------------------------------
| Mouvements sur une carte : top_up | redeem | adjustment | refund.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('tagtoa_loyalty_cards')->cascadeOnDelete();
            $table->foreignId('reward_id')->nullable()->constrained('tagtoa_loyalty_rewards')->nullOnDelete();
            $table->string('type');                        // top_up|redeem|adjustment|refund
            $table->decimal('amount', 10, 2)->default(0);  // montant financier
            $table->integer('points_delta')->default(0);   // +/- points
            $table->decimal('balance_after', 10, 2)->nullable();
            $table->integer('points_after')->nullable();
            $table->string('payment_method')->nullable();  // moncash|natcash|cash|paypal|zelle
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);     // 1=confirmed, 0=pending, 2=failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_transactions');
    }
};
