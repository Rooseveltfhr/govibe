<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — Sales
|--------------------------------------------------------------------------
| Paiement simple ou split (payments = JSON [{method, amount}]).
| client_uuid = idempotence pour la sync offline-first.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('tagtoa_pos_terminals')->cascadeOnDelete();
            $table->string('reference')->unique();        // TGP-XXXXXX
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->json('payments')->nullable();         // [{method, amount}] (split)
            $table->string('customer_phone')->nullable(); // pour reçu WhatsApp
            $table->string('client_uuid', 64)->nullable()->unique();
            $table->tinyInteger('status')->default(1);     // 1=completed,0=pending,2=refunded,3=void
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_sales');
    }
};
