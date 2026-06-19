<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — Events
|--------------------------------------------------------------------------
| Module 6 (CLAUDE.md §15). Événement payant ou gratuit, multi-type.
| URL publique : tagtoa.com/event/{alias}. Tables préfixées tagtoa_ev_*.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_ev_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vcard_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title');
            $table->string('alias')->unique();
            $table->string('type')->default('concert'); // concert|expo|mariage|sport|conference|autre
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('currency', 10)->default('HTG');
            $table->boolean('is_free')->default(false);
            $table->boolean('is_published')->default(false);
            $table->foreignId('pay_page_id')->nullable(); // -> tagtoa_payment_pages.id (paiement billets)
            $table->unsignedInteger('views')->default(0);
            // Cover via spatie/medialibrary — collection 'event-cover'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_ev_events');
    }
};
