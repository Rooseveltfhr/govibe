<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des connexions Admin + ERP — même table pour les deux surfaces,
 * puisqu'elles mènent au même compte (User::is_admin) : une série
 * d'échecs sur l'une doit rester visible même si l'attaquant bascule sur
 * l'autre. `surface` garde la distinction pour le support, sans la rendre
 * pour autant, comme connexions_portail (même schéma, même raison).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connexions_admin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('surface', 10);
            $table->string('email', 190);
            $table->boolean('reussie');
            $table->string('motif', 60)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['email', 'created_at']);
            $table->index(['ip', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connexions_admin');
    }
};
