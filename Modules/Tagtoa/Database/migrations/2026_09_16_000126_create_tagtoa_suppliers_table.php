<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — les fournisseurs du commerce.
 *
 * Un commerce achète toujours aux mêmes personnes : le dépôt de riz, la
 * brasserie, le grossiste au marché, le cousin qui livre les pâtés. Sans
 * annuaire, le numéro à rappeler quand le stock baisse vit dans un téléphone,
 * et se perd avec lui.
 *
 * L'utilité vraie vient plus loin : chaque réception étant rattachée à son
 * fournisseur, le commerce peut enfin répondre à « combien j'ai acheté chez
 * qui, et lequel me livre en retard ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_suppliers', function (Blueprint $table) {
            $table->id();

            // Le COMMERCE, pas le compte : un patron à deux boutiques a deux
            // annuaires (voir Support/Tenant et BelongsToTenant).
            $table->string('tenant_id')->index();

            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('address', 240)->nullable();

            // Ce que le marchand note pour lui : « livre le mardi »,
            // « paiement à 15 jours », « demander Jean ».
            $table->text('notes')->nullable();

            // Désactivé plutôt que supprimé : l'historique des réceptions
            // continue de désigner un fournisseur qui existe.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_suppliers');
    }
};
