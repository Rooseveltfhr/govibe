<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — les tentatives de réclamation.
 *
 * Sert à VOIR un balayage pendant qu'il a lieu, pas après. Quelqu'un qui
 * essaie des codes au hasard produit une signature très reconnaissable :
 * beaucoup d'échecs, depuis peu d'adresses, sur beaucoup de stands.
 *
 * C'est aussi cette table qui porte la limitation par STAND — une limite par
 * IP seule se contourne avec un réseau de proxys, alors qu'une limite par
 * stand vaut quel que soit le nombre de machines de l'attaquant.
 *
 * Le code essayé n'est JAMAIS enregistré, même faux : une frappe malheureuse
 * peut être le code d'un stand voisin, et le journal deviendrait alors une
 * liste de codes valides en clair.
 *
 * Purgée à 90 jours : le volume de cette table vient des attaques, pas des
 * clients, et une table d'attaque qui grossit sans fin finit par coûter plus
 * cher que l'attaque elle-même.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_stand_claim_attempts', function (Blueprint $table) {
            $table->id();

            // Nullable : une tentative sur un identifiant qui n'existe pas est
            // justement le signal le plus intéressant.
            $table->unsignedBigInteger('stand_id')->nullable()->index();
            $table->string('public_id_tried', 24)->index();

            $table->boolean('succeeded')->default(false);
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->nullable()->index();

            // La lecture réelle : « combien d'échecs sur CE stand depuis une
            // heure », posée à chaque tentative.
            $table->index(['stand_id', 'created_at'], 'tagtoa_claim_recent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_stand_claim_attempts');
    }
};
