<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA — la colonne vertébrale des commandes.
 *
 * Un commerce vend au comptoir, par QR au bord de la table, en ligne, et à
 * l'entrée d'un événement. Ce sont quatre écrans — mais UN seul chiffre
 * d'affaires, un seul client, une seule taxe à déclarer. Tant que chaque
 * module comptait dans son coin, le marchand additionnait quatre écrans de
 * tête, et ne pouvait pas voir qu'un même client achète chez lui par trois
 * chemins.
 *
 * CETTE TABLE NE REMPLACE PAS LES AUTRES. Chaque module garde la sienne pour
 * ce qui lui est propre : le numéro de table du restaurant, l'adresse de
 * livraison de la boutique, le billet de l'événement. Ici ne vit que ce qui
 * est COMMUN — et l'écriture se fait dans la même transaction que celle du
 * module, jamais après coup.
 *
 * Ce choix évite de réécrire quatre modules qui fonctionnent, et de migrer des
 * données de production. Son prix est une double écriture ; une garde
 * automatique vérifie que les deux ne divergent pas, comme pour le journal
 * de stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_orders', function (Blueprint $table) {
            $table->id();

            // NULLABLE, volontairement. Une caisse ancienne peut ne pas
            // porter de commerce, et une contrainte ici ferait ÉCHOUER la
            // vente — le client a payé, et rien ne serait enregistré. La
            // colonne de rapport ne doit jamais bloquer l'encaissement.
            $table->string('tenant_id')->nullable()->index();

            // D'où vient la commande : pos | menu | store | event | link.
            $table->string('channel', 12)->index();

            // La ligne du module d'origine. Permet de rouvrir la commande dans
            // l'écran qui la connaît vraiment, avec ses détails métier.
            $table->string('source_type', 24);
            $table->unsignedBigInteger('source_id');

            $table->string('reference', 64)->index();

            // Le client, quand il y en a un. NULL = client de passage, et c'est
            // le cas le plus fréquent au comptoir : exiger un nom pour
            // encaisser rendrait la caisse inutilisable.
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            // Nom et téléphone FIGÉS : la fiche client peut être corrigée plus
            // tard, une facture déjà émise ne doit pas changer de destinataire.
            $table->string('customer_name', 160)->nullable();
            $table->string('customer_phone', 40)->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax_base', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 10)->default('HTG');

            // Deux axes séparés : une commande peut être payée d'avance et pas
            // encore préparée, ou livrée et jamais payée.
            $table->string('status', 16)->default('pending')->index();
            $table->string('payment_status', 16)->default('unpaid')->index();

            // Qui a encaissé, quand un employé tenait le poste.
            $table->unsignedBigInteger('staff_id')->nullable()->index();

            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            // Une commande d'origine n'entre qu'UNE fois : une reprise après
            // coupure réseau ne doit pas la compter deux fois dans la recette.
            $table->unique(['source_type', 'source_id'], 'tagtoa_orders_source');

            // Les deux lectures réelles : la journée d'un commerce, et
            // l'historique d'un client.
            $table->index(['tenant_id', 'placed_at'], 'tagtoa_orders_jour');
            $table->index(['tenant_id', 'customer_id'], 'tagtoa_orders_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_orders');
    }
};
