<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY — qui traite la carte bancaire, au choix du fondateur.
 *
 * La carte est désormais traitée par PayPal : le client paie par carte sans
 * avoir de compte PayPal, et TAGTOA n'a qu'un seul fournisseur à contractualiser.
 * Stripe reste disponible — il accepte le peso dominicain, que PayPal refuse, et
 * sert de réserve si un compte PayPal est bloqué.
 *
 * Colonne nullable : vide = le driver déclaré dans PaymentGateway::GATEWAYS.
 * La valeur écrite est toujours vérifiée contre PaymentGateway::ALTERNATIVES,
 * donc aucune chaîne arbitraire ne peut devenir un nom de driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tagtoa_gateway_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('tagtoa_gateway_settings', 'driver')) {
                $table->string('driver', 40)->nullable()->after('credential_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tagtoa_gateway_settings', function (Blueprint $table) {
            if (Schema::hasColumn('tagtoa_gateway_settings', 'driver')) {
                $table->dropColumn('driver');
            }
        });
    }
};
