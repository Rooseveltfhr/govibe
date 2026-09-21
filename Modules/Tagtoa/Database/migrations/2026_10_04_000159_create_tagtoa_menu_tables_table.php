<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — table vérifiée par QR/NFC.
 *
 * `table_label` existait déjà sur la commande, mais en texte libre tapé par
 * le client : rien n'empêchait d'écrire n'importe quel numéro, volontairement
 * ou par erreur — la cuisine pouvait porter un plat à la mauvaise table, ou
 * un client mal intentionné se faire livrer à une table qui n'est pas la
 * sienne. Une TABLE ici est un objet du commerce, avec un code que SEUL un
 * QR/NFC imprimé et posé sur la vraie table porte : le client ne le tape
 * jamais, il le scanne.
 *
 * Pas de contrainte de clé étrangère sur `menu_id`, par la même convention
 * que `category_id`/`supplier_id` ailleurs dans TAGTOA : rien ne doit
 * pouvoir bloquer la lecture d'un code déjà imprimé et collé sur une table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_menu_tables', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('menu_id')->index();
            $t->string('tenant_id')->nullable()->index();
            $t->string('label', 40);
            // Unique GLOBALEMENT (pas seulement par menu) : le code est ce
            // qu'un QR encode dans l'URL publique, jamais accompagné de
            // l'identifiant du menu — deux commerces ne doivent jamais
            // pouvoir se retrouver avec le même code par coïncidence.
            $t->string('code', 20)->unique();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_menu_tables');
    }
};
