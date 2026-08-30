<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajan yon machann kreye.
 *
 * Nou pa sere ni konsiy la ni lis zouti yo: yo soti nan modèl sektè a
 * (AgentTemplateRegistry). Konsa lè nou amelyore konsiy restoran an, TOUT
 * ajan restoran yo pwofite l imedyatman — nou pa gen mil kopi yon konsiy
 * ki fin vye ap trennen nan baz done a.
 *
 * Sa nou sere se sèlman sa ki PA nan modèl la: idantite biznis lan ak
 * konesans pa l (mni, orè, pri…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();          // slug: "ti-kafe"
            $table->string('name');                   // "Ti Kafe"
            $table->string('sector');                 // restaurant | clinic | school
            $table->json('knowledge')->nullable();    // orè, mni, pri…
            $table->json('channels')->nullable();     // whatsapp, web…
            $table->json('languages')->nullable();    // ht, fr…
            $table->string('handoff_to')->nullable(); // nimewo/imèl moun ki pran relè a
            $table->timestamps();

            $table->index('sector');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
