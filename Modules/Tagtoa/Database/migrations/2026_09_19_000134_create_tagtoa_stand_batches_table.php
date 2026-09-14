<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — les lots de production.
 *
 * Le jour où un stand pose problème, la première question sera : de quel lot
 * vient-il, qui l'a imprimé, quand ? Sans lot on ne peut ni rappeler une série,
 * ni prouver qu'une unité est authentique, ni régler un litige avec l'imprimeur.
 *
 * `manifest_sha256` est l'empreinte du fichier remis à la presse. C'est la
 * seule preuve qu'on imprime bien ce qui a été généré — un fichier modifié en
 * chemin donnerait des objets qui ne correspondent à rien en base.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stand_batches', function (Blueprint $table) {
            $table->id();

            $table->string('code', 40)->unique();          // TAGTOA-2026-001
            $table->unsignedInteger('quantity')->default(0);
            $table->string('id_prefix', 8)->default('TG');
            $table->unsignedInteger('range_start')->default(1);
            $table->unsignedInteger('range_end')->default(1);

            $table->string('manufacturer', 120)->nullable();
            // « NTAG213 · 144o · verrouillé » — ce qui a réellement été posé
            // dans l'objet. Un lot fabriqué autrement se diagnostique autrement.
            $table->string('hardware', 60)->nullable();

            $table->char('manifest_sha256', 64)->nullable();

            $table->timestamp('produced_at')->nullable();
            $table->timestamp('received_at')->nullable();
            // Rappel de lot : défaut d'impression, secrets compromis. Les stands
            // du lot cessent d'être réclamables sans qu'on touche à chacun.
            $table->timestamp('recalled_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stand_batches');
    }
};
