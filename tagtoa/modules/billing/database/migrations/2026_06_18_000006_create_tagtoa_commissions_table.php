<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA BILLING — Commissions ledger
|--------------------------------------------------------------------------
| Journal des commissions prélevées par TAGTOA sur les ventes des marchands
| (modèle 'commission' ou 'both'). source = polymorphe léger (type + id).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_commissions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('source_type');                  // event_order|pos_sale|pay_proof|loyalty_topup
            $table->unsignedBigInteger('source_id');
            $table->string('module', 30);                   // event|pos|pay|loyalty
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('commission_fixed', 10, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->tinyInteger('status')->default(1);       // 1=accrued,2=settled,0=void
            $table->timestamps();
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_commissions');
    }
};
