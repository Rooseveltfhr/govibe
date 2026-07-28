<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA AUDIT — journal des actions sensibles du marchand (modération,
 * finances, changements de statut). Lecture seule depuis le dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('action', 48)->index();          // ex. review.approved, booking.completed
            $table->string('subject_type', 24)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_audit_logs');
    }
};
