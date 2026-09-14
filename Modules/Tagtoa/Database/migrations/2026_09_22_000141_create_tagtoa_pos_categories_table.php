<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA POS — les rayons du commerce.
 *
 * Le catalogue de caisse n'avait AUCUNE catégorie : quarante articles
 * donnaient quarante boutons d'affilée, et le caissier cherchait à l'œil au
 * moment où il a le moins de temps. Le menu digital, lui, avait ses catégories
 * depuis le début — d'où deux catalogues qui ne se ressemblaient pas alors
 * qu'ils décrivent le même commerce.
 *
 * La catégorie appartient au COMMERCE, pas à la caisse : deux caisses du même
 * commerce vendent les mêmes rayons.
 *
 * `icon` porte une classe Font Awesome, jamais un emoji — un emoji se dessine
 * autrement sur chaque téléphone et tombe en carré blanc sur beaucoup
 * d'Android bon marché. Nullable : l'icône se déduit du nom quand elle manque.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tagtoa_pos_categories')) {
            return;
        }

        Schema::create('tagtoa_pos_categories', function (Blueprint $t) {
            $t->id();
            // Indexé avec l'ordre d'affichage : la requête de la caisse trie
            // toujours par (commerce, sort), et c'est la plus fréquente.
            $t->string('tenant_id', 64);
            $t->string('name', 80);
            $t->string('icon', 40)->nullable();
            $t->string('color', 7)->nullable();
            $t->unsignedInteger('sort')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->index(['tenant_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_pos_categories');
    }
};
