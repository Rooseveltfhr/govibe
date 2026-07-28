<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — abonnement (forfait) du marchand. Un enregistrement actif par tenant.
 * Table tagtoa_* ; règle DB respectée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->unique();
            $table->string('plan', 30)->default('free');   // free|pro|enterprise
            $table->string('status', 20)->default('active'); // active|trial|expired
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_subscriptions');
    }
};
