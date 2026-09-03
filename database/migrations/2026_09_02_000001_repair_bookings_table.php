<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligne la table sur ce que le formulaire et la liste attendent depuis
 * toujours : un intitulé de réservation, et un client facultatif.
 *
 * Le formulaire proposait déjà « -- Aucun -- » pour le client alors que la
 * colonne était obligatoire, et affichait un champ « Titre » sans colonne
 * correspondante. Aucune réservation n'a donc jamais pu être enregistrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'title')) {
                $table->string('title')->nullable()->after('reference');
            }
        });

        // Rendu facultatif comme space_id l'a été : une réservation peut être
        // prise pour un visiteur qui n'est pas encore fiché comme client.
        Schema::table('bookings', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });

        // La recherche de chevauchement filtre sur ces trois colonnes.
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['space_id', 'start_datetime', 'end_datetime'], 'bookings_creneau_index');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('bookings_creneau_index');
            $table->dropColumn('title');
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
    }
};
