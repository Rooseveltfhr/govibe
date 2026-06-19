<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Transactions de vente in-event
|--------------------------------------------------------------------------
| Le participant paie avec le wallet NFC de son ticket.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_sale_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('tagtoa_ev_events')->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('tagtoa_ev_tickets')->nullOnDelete();
            $table->json('items')->nullable();               // snapshot [{sale_item_id,name,price,qty}]
            $table->decimal('total', 10, 2)->default(0);
            $table->string('payment_method')->default('wallet'); // wallet|cash|moncash|...
            $table->string('client_uuid', 64)->nullable()->index();
            $table->tinyInteger('status')->default(1);        // 1=confirmed,0=pending,2=failed
            $table->timestamps();
            $table->unique('client_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_sale_transactions');
    }
};
