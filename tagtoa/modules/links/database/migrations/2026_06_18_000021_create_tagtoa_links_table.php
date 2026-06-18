<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA LINKS — Individual links
|--------------------------------------------------------------------------
| Un lien d'une page LINKS. `platform` est auto-détecté depuis l'URL
| (facebook, instagram, tiktok…) pour afficher le bon logo, ou 'custom'.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('link_page_id')->constrained('tagtoa_link_pages')->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            $table->string('platform', 30)->default('custom'); // facebook|instagram|...|custom
            $table->boolean('is_featured')->default(false);     // gros bouton mis en avant
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_links');
    }
};
