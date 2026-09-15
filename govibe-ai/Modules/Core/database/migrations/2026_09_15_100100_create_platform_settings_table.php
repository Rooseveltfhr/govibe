<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigirasyon platfòm nan, chanjab depi panèl la.
 *
 * `is_secret` chanje de bagay: valè a chiffre nan baz la (jamè an klè), epi
 * entèfas la pa janm re-afiche l — li montre si li mete oswa non. Yon kle
 * API ou ka li sou yon paj se yon kle ki fin fwit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
