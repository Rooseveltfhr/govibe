<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Langues que CE menu offre au client — sous-ensemble des langues globales
 * de TAGTOA (voir config('tagtoa.locales')). Nullable : un menu qui n'a
 * jamais choisi ne restreint rien, même convention que `hours` (nul = pas
 * encore réglé, jamais une liste vide qui bloquerait tout le monde).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->json('languages')->nullable()->after('translations');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $table) {
            $table->dropColumn('languages');
        });
    }
};
