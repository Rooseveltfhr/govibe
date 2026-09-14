<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA SMART STAND — la réservation d'activation.
 *
 * Le code est vérifié AVANT que la personne ne crée son compte : lui faire
 * créer un compte pour lui annoncer ensuite que son code est illisible est la
 * façon la plus sûre de la perdre.
 *
 * Entre la vérification et la réclamation, le stand est donc réservé quinze
 * minutes. Sans cela, deux personnes qui grattent le même carton pourraient
 * partir toutes les deux et l'une découvrirait, après avoir tout rempli, que
 * le stand est déjà pris.
 *
 * La réservation EXPIRE, et c'est essentiel : un client qui abandonne à
 * mi-parcours ne doit pas condamner un objet qu'il a payé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_stands', function (Blueprint $t) {
            if (! Schema::hasColumn('tagtoa_stands', 'claim_reserved_until')) {
                $t->timestamp('claim_reserved_until')->nullable();
            }
            if (! Schema::hasColumn('tagtoa_stands', 'claim_reserved_token')) {
                // Haché : ce jeton vaut le droit de réclamer pendant quinze
                // minutes, il ne doit pas être lisible dans un dump.
                $t->char('claim_reserved_token', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_stands', function (Blueprint $t) {
            foreach (['claim_reserved_until', 'claim_reserved_token'] as $c) {
                if (Schema::hasColumn('tagtoa_stands', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
