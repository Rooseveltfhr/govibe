<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — Terminals (caisses)
|--------------------------------------------------------------------------
| Module 7 (CLAUDE.md §16). Tables préfixées tagtoa_pos_*.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_pos_terminals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vcard_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');                       // "Caisse 1"
            $table->string('currency', 10)->default('HTG');
            $table->boolean('is_active')->default(true);
            $table->decimal('cash_balance', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_terminals');
    }
};
