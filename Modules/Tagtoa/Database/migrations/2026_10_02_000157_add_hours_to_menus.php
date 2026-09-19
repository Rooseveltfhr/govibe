<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA MENU — le commerce peut enfin dire quand il est ouvert.
 *
 * Un pattern proche existe déjà pour le module Site (`tagtoa_sites.hours`),
 * mais en JSON libre `[{day,value}]` — bon pour de l'affichage, impossible à
 * évaluer côté serveur (« est-il ouvert MAINTENANT ? »). Ici `hours` est
 * structuré par jour (`{"mon": {"open":"08:00","close":"20:00"}, …}`, un
 * jour absent ou `null` = fermé ce jour-là) pour que
 * `BusinessHours::isOpenAt()` puisse répondre sans intervention humaine —
 * c'est ce qui permet de REFUSER une commande hors horaires, pas seulement
 * de l'afficher.
 *
 * `show_hours` par défaut à FALSE, à l'inverse de `tagtoa_sites` : la very
 * grande majorité des menus existants n'ont jamais renseigné d'horaires, et
 * les afficher comme « Fermé » par défaut serait un mensonge plus grave que
 * ne rien afficher.
 *
 * `timezone` : Haïti d'abord, mais TAGTOA sert aussi d'autres pays — sans ce
 * champ, « 8h » ne veut rien dire de précis pour un commerce hors du fuseau
 * par défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $t) {
            $t->json('hours')->nullable();
            $t->boolean('show_hours')->default(false);
            $t->string('timezone', 64)->default('America/Port-au-Prince');
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_menus', function (Blueprint $t) {
            $t->dropColumn(['hours', 'show_hours', 'timezone']);
        });
    }
};
