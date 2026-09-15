<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yon drapo administratè sou kont yo.
 *
 * Pa gen enskripsyon piblik: premye administratè a kreye ak `govibe:admin`
 * nan liy kòmand sou sèvè a. Yon paj enskripsyon piblik sou yon panèl ki
 * montre kòmand kliyan yo (non, nimewo WhatsApp, peman) se yon pòt ouvè.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_admin');
        });
    }
};
