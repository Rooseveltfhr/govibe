<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| TAGTOA BILLING — Revenue settings
|--------------------------------------------------------------------------
| Modèle de revenu TAGTOA. 2 options à choisir (CLAUDE.md / décision business) :
|   - 'subscription' : le marchand paie un abonnement (Plan/Subscription existants),
|                      aucune commission prélevée sur ses ventes.
|   - 'commission'   : pas/peu d'abonnement, TAGTOA prélève une commission sur
|                      chaque vente (EVENT, POS, etc.).
|   - 'both'         : abonnement + commission réduite.
|
| Une ligne globale (tenant_id = null) sert de valeur par défaut plateforme ;
| une ligne par tenant peut la surcharger.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_revenue_settings', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->unique(); // null = défaut plateforme
            $table->string('revenue_model')->default('subscription'); // subscription|commission|both
            $table->decimal('commission_percent', 5, 2)->default(0);  // % sur le montant brut
            $table->decimal('commission_fixed', 10, 2)->default(0);   // frais fixe par transaction
            $table->string('currency', 10)->default('HTG');
            $table->json('applies_to')->nullable();            // ["event","pos","pay","loyalty"] (null = tous)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_revenue_settings');
    }
};
