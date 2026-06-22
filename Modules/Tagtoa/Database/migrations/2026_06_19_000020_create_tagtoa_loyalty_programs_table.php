<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vcard_id')->nullable()->index();
            $table->string('tenant_id')->nullable()->index();
            $table->string('name');
            $table->string('alias')->unique();
            $table->text('description')->nullable();
            $table->decimal('points_per_dollar', 8, 2)->default(1);
            $table->decimal('dollar_per_point', 8, 4)->default(0.01);
            $table->string('currency', 10)->default('HTG');
            $table->string('logo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_loyalty_programs');
    }
};
