<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — les codes d'articles, en un seul endroit.
 *
 * La caisse vend les DEUX catalogues du commerce : ses propres boutons et les
 * articles du menu digital. Un code doit donc pouvoir désigner l'un ou l'autre,
 * d'où une table unique plutôt qu'une colonne sur chacune des deux.
 *
 * Trois choix qui comptent :
 *
 * 1. UNICITÉ PAR COMMERCE, jamais globale. Deux boutiques vendent le même
 *    Coca-Cola avec le même code imprimé dessus : une unicité mondiale
 *    empêcherait la seconde de l'enregistrer. En revanche, à l'intérieur d'un
 *    commerce, un code ne peut désigner qu'un article — sinon scanner
 *    deviendrait un tirage au sort.
 *
 * 2. PLUSIEURS CODES PAR ARTICLE. Le carton de 24 et la bouteille à l'unité
 *    portent des codes différents. Un produit local reçoit en plus un code
 *    interne imprimé par le commerce.
 *
 * 3. LA RECHERCHE EST INDEXÉE SUR (commerce, code). C'est la requête du
 *    caissier en heure de pointe : elle doit répondre instantanément, et ne
 *    jamais pouvoir regarder ailleurs que dans son commerce.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_product_codes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable();

            // L'article visé : « menu » ou « pos » + son identifiant. Les deux
            // catalogues numérotent à partir de 1, d'où la source obligatoire.
            $table->string('source', 10);
            $table->unsignedBigInteger('product_id');

            $table->string('code', 48);
            // ean13 | ean8 | upca | internal | other — voir Support/Catalog/Barcode.
            $table->string('type', 12)->default('other');
            // Étiquette lisible : « Carton de 24 », « À l'unité ».
            $table->string('label', 60)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            // Dans un commerce, un code ne désigne qu'un article.
            $table->unique(['tenant_id', 'code'], 'tagtoa_product_codes_tenant_code_unique');
            // La requête du caissier, et celle de la fiche article.
            $table->index(['tenant_id', 'code'], 'tagtoa_product_codes_lookup');
            $table->index(['source', 'product_id'], 'tagtoa_product_codes_article');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_product_codes');
    }
};
