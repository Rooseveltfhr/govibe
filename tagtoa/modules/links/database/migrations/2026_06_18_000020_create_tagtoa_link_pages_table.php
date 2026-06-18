<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA LINKS — Link pages
|--------------------------------------------------------------------------
| Page Linktree-style TAGTOA. URL publique : tagtoa.com/links/{alias}.
| Optionnellement reliée à une page TAGTOA PAY pour les dons (pay_page_id).
| Nouvelle table préfixée tagtoa_* — n'altère rien d'existant.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_link_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vcard_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->string('alias')->unique();             // tagtoa.com/links/{alias}
            $table->text('bio')->nullable();
            $table->string('theme', 20)->default('dark');  // dark|light|blue
            $table->string('donation_label')->nullable();  // "Soutiens-moi"
            $table->foreignId('pay_page_id')->nullable();  // -> tagtoa_payment_pages.id (don)
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_link_pages');
    }
};
