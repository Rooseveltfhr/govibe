<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA LOYALTY — Programs
|--------------------------------------------------------------------------
| Programme de fidélité appartenant à un vcard / tenant. Nouvelle table
| préfixée tagtoa_* — n'altère rien d'existant.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vcard_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');                        // "TAGTOA Fidélité"
            $table->string('alias')->unique();
            $table->text('description')->nullable();
            $table->decimal('points_per_dollar', 8, 2)->default(1);
            $table->decimal('dollar_per_point', 8, 4)->default(0.01);
            $table->string('currency', 10)->default('HTG');
            $table->boolean('is_active')->default(true);
            // Logo via spatie/medialibrary — collection: 'program-logo'
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_programs');
    }
};
