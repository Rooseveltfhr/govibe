<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('numero_inscription')->unique();
            $table->string('nom_complet');
            $table->enum('sexe', ['Masculin', 'Féminin']);
            $table->date('date_naissance');
            $table->string('telephone');
            $table->string('email');
            $table->string('departement');
            $table->string('ville');
            $table->string('profession')->nullable();
            $table->string('niveau_etude');
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->string('source_info');
            $table->text('objectif')->nullable();
            $table->text('attentes')->nullable();
            $table->string('qr_code')->nullable();
            $table->boolean('present')->default(false);
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions');
    }
};
