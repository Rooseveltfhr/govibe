<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — le commerce devient l'unité du système.
 *
 * Un compte TAGTOA peut tenir PLUSIEURS commerces : la même personne ouvre une
 * boulangerie, puis un bar, sans se recréer un compte.
 *
 * Choix structurant : le commerce EST le « tenant ».
 * `Tenant::id()` renvoie désormais l'identifiant du commerce courant, et non
 * celui du compte. Les 28 modèles isolés par BelongsToTenant se retrouvent donc
 * cloisonnés par commerce sans une seule ligne de code modifiée.
 *
 * Pour que rien ne soit à réécrire, l'identifiant n'est PAS auto-incrémenté :
 * le premier commerce d'un marchand existant reçoit pour identifiant son
 * `tenant_id` actuel. Toutes ses données continuent donc de correspondre telles
 * quelles. Les commerces créés ensuite reçoivent un ULID.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_businesses', function (Blueprint $table) {
            // Identifiant fourni, jamais généré par la base : voir ci-dessus.
            $table->string('id')->primary();

            // Le compte propriétaire. Plusieurs commerces peuvent le partager.
            $table->string('account_id')->index();

            $table->string('name', 160);
            // Type d'établissement — mêmes clés que Menu::TYPES / BusinessProfile,
            // pour qu'un restaurant retrouve ses champs métier partout.
            $table->string('type', 40)->default('other');
            $table->json('categories')->nullable();

            // Ce que le commerce vend. Les deux peuvent être vrais : un hôtel
            // vend des nuits (service) et des boissons (produit).
            $table->boolean('sells_products')->default(true);
            $table->boolean('sells_services')->default(false);

            $table->string('logo_path')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('phone', 40)->nullable();

            // HTG, USD, ou un code saisi par le marchand (certaines diasporas
            // tiennent leurs comptes dans une devise que nous ne listons pas).
            $table->string('currency', 10)->default('HTG');

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_businesses');
    }
};
