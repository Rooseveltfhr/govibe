<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — le mot du commerce en bas de ses reçus.
 *
 * Un reçu haïtien porte presque toujours une phrase du patron : « Merci pour
 * votre confiance », « Pas d'échange après 3 jours », « Livraison : 3456-7890 ».
 * Ce n'est pas décoratif. C'est là que se règle une contestation au comptoir :
 * ce qui est imprimé sur le ticket que le client tient fait foi, et une règle
 * annoncée après la vente ne vaut rien.
 *
 * Le réglage appartient au COMMERCE, pas au poste de caisse — même raison que
 * la taxe : deux caisses du même commerce ne doivent pas délivrer des reçus qui
 * se contredisent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_businesses')) {
            return;
        }

        Schema::table('tagtoa_businesses', function (Blueprint $t) {
            // Assez pour trois lignes de politique commerciale ; pas assez pour
            // qu'on y colle un règlement intérieur qui ferait sortir le ticket
            // sur trente centimètres de papier à chaque vente.
            if (! Schema::hasColumn('tagtoa_businesses', 'receipt_footer')) {
                $t->string('receipt_footer', 240)->nullable()->after('tax_number');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tagtoa_businesses') && Schema::hasColumn('tagtoa_businesses', 'receipt_footer')) {
            Schema::table('tagtoa_businesses', function (Blueprint $t) {
                $t->dropColumn('receipt_footer');
            });
        }
    }
};
