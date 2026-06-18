<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA PAY — Payment pages
|--------------------------------------------------------------------------
| Page de paiement publique appartenant à un vcard / tenant.
| URL publique : tagtoa.com/pay/{alias}
| N'altère aucune table existante — nouvelle table préfixée tagtoa_*.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_payment_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vcard_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title')->nullable();          // "Payez Jean Baptiste"
            $table->string('alias')->unique();            // URL: tagtoa.com/pay/jean-baptiste
            $table->text('description')->nullable();
            $table->string('default_currency', 10)->default('HTG');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_payment_pages');
    }
};
