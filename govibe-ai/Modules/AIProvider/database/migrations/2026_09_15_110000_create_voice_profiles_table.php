<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bibliyotèk vwa platfòm nan — vwa NOU chwazi, pa tout sa founisè a genyen.
 *
 * ElevenLabs bay plizyè santèn vwa. Yon machann ki dwe chwazi nan yon lis
 * konsa pa chwazi: li abandone. Tab sa a se lis kout la — vwa nou teste,
 * ak lang chak vwa bon pou li.
 *
 * `language` se desizyon ki konte pou Ayiti: yon vwa ki li franse byen pa
 * nesesèman bon pou kreyòl. Nou make sa nou konnen, epi ajan yo pran vwa
 * pa defo a pou lang yo pale a.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('provider_key', 40)->default('elevenlabs');
            $table->string('voice_id', 120);
            $table->string('name', 120);
            $table->string('language', 5)->default('fr');   // ht | fr | en | es
            $table->string('source', 20)->default('library'); // library | cloned
            $table->boolean('is_default')->default(false);
            $table->string('preview_url')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('position')->default(100);
            $table->timestamps();

            // Yon vwa yon sèl fwa nan bibliyotèk la, pa de.
            $table->unique(['provider_key', 'voice_id']);
            $table->index(['language', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_profiles');
    }
};
