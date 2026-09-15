<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kle API yon founisè, chiffre nan baz la.
 *
 * Poukisa nan baz la epi pa sèlman nan `.env`: mete yon kle nan `.env` mande
 * yon deplwaman ak yon aksè SSH. Yon fondatè ki jwenn yon kle OpenAI nan yon
 * samdi swa pa dwe tann yon deplwaman pou ajan yo kòmanse reponn.
 *
 * Règ: `.env` rete PI FÒ pase baz la. Konsa yon kle enfrastrikti (sa yon
 * ekip mete espre sou sèvè a) pa ka ranplase depi yon paj wèb.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_providers', function (Blueprint $table): void {
            $table->text('api_key')->nullable()->after('base_url_override');
        });
    }

    public function down(): void
    {
        Schema::table('ai_providers', function (Blueprint $table): void {
            $table->dropColumn('api_key');
        });
    }
};
