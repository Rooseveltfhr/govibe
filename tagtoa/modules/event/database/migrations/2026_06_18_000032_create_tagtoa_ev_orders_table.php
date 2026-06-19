<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Orders (achat de billets)
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->string('reference')->unique();           // TGE-XXXXXX
            $table->string('buyer_name');
            $table->string('buyer_phone')->nullable();
            $table->string('buyer_email')->nullable();
            $table->decimal('total', 10, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->string('payment_method')->nullable();    // moncash|natcash|cash|paypal|...
            $table->tinyInteger('status')->default(0);        // 0=pending,1=paid,2=cancelled,3=refunded
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_orders');
    }
};
