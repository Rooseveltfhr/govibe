<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — Cash movements (fond de caisse, entrées/sorties)
|--------------------------------------------------------------------------
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('terminal_id')->constrained('tagtoa_pos_terminals')->cascadeOnDelete();
            $table->string('type');                       // open|in|out|close|sale
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_cash_movements');
    }
};
