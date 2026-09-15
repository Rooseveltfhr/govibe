<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chak ajan gen pwòp vwa l.
 *
 * Se pa yon detay estetik: yon restoran ak yon klinik ki pale ak menm vwa
 * a sonnen tankou menm konpayi. Epi lè yon machann anrejistre vwa pa l,
 * se isit la nou kenbe chwa a — pa nan `.env`, ki se yon sèl valè pou
 * tout platfòm nan.
 *
 * Nullable: san chwa, ajan an pran vwa pa defo a.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->string('voice_id')->nullable()->after('languages');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropColumn('voice_id');
        });
    }
};
