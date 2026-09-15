<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peman yon kòmand.
 *
 * Yon kòmand ka peye an plizyè fwa (yon avans, apre sa rès la) — se konsa
 * biznis fèt isit la. Kidonk peman yo se yon LIS, se pa yon kolòn « peye »
 * sou kòmand lan.
 *
 * Montan an an **inite minè** (santim) nan yon antye: zewo flotan nan lajan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agent_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency', 3)->default('HTG');
            $table->string('method', 30);                 // moncash | natcash | unibank | kach | lot
            $table->string('reference', 120)->nullable(); // nimewo tranzaksyon an
            $table->date('received_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['agent_order_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_payments');
    }
};
