<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preuves_paiement', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();

            // Qui a payé. Le téléphone est le seul champ obligatoire avec la
            // capture : c'est par là que l'équipe recontacte le client.
            $table->string('nom', 150);
            $table->string('telephone', 40);
            $table->string('email', 190)->nullable();

            // Ce qui a été payé.
            $table->string('moyen', 60)->nullable();          // code de la passerelle
            $table->string('moyen_nom', 80)->nullable();      // nom figé au moment de l'envoi
            $table->decimal('montant', 12, 2)->nullable();
            $table->string('devise', 8)->default('HTG');
            $table->string('transaction_id', 120)->nullable();
            $table->string('motif', 200)->nullable();         // inscription, réservation, facture…
            $table->text('note')->nullable();

            // La capture vit sur le disque privé : une preuve de paiement
            // porte des identifiants de compte et ne doit pas être servie
            // par une URL devinable.
            $table->string('fichier')->nullable();
            $table->string('fichier_nom_origine', 255)->nullable();
            $table->unsignedInteger('fichier_taille')->nullable();
            $table->string('fichier_mime', 80)->nullable();

            $table->string('statut', 20)->default('recue');   // recue, verifiee, rejetee
            $table->text('commentaire_admin')->nullable();
            $table->string('verifiee_par', 120)->nullable();
            $table->timestamp('verifiee_le')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index('statut');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preuves_paiement');
    }
};
