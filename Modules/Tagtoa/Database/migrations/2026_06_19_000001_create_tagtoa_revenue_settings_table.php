<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_revenue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->unique();        // null = défaut plateforme
            $table->string('revenue_model')->default('subscription'); // subscription|commission|both
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->decimal('commission_fixed', 10, 2)->default(0);
            $table->string('currency', 10)->default('HTG');
            $table->json('applies_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_revenue_settings');
    }
};
