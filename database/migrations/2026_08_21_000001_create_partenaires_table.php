<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partenaires', function (Blueprint $table) {
            $table->id();
            $table->string('prenom');
            $table->string('nom');
            $table->string('organisation')->nullable();
            $table->string('pays')->default('Haïti');
            $table->string('ville');
            $table->string('telephone');
            $table->string('email');
            $table->string('type_partenariat');
            $table->text('description')->nullable();
            $table->enum('statut', ['nouveau', 'en_cours', 'accepte', 'refuse'])->default('nouveau');
            $table->text('notes_admin')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partenaires');
    }
};
