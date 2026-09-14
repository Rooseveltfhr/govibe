<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BOUTIQUE TAGTOA — le marchand commande son matériel.
 *
 * ── Ce que ces tables ferment ────────────────────────────────────────────
 *
 * Le Smart Stand existe (M1), il mène quelque part (M2), son propriétaire se
 * le réclame (M3) et une salle entière s'active en quatre minutes (M4). Il
 * manquait le début : COMMENT un restaurant obtient-il ses quarante stands ?
 * Aujourd'hui, par WhatsApp, au jugé, sans trace — et une commande passée par
 * message se perd, se discute, et n'existe dans aucun chiffre.
 *
 * ── Trois décisions qui expliquent la forme ──────────────────────────────
 *
 * 1. LES PRIX SONT FIGÉS SUR LA LIGNE DE COMMANDE. Même principe que les
 *    ventes et les retours : TAGTOA qui augmente le prix du stand le mois
 *    prochain ne doit pas réécrire une commande passée aujourd'hui.
 *
 * 2. L'ARTICLE DE BOUTIQUE N'APPARTIENT À AUCUN COMMERCE. C'est TAGTOA qui
 *    vend ; le catalogue est celui de la plateforme, comme un lot de stands.
 *    Pas de `tenant_id`, donc — et pas de portée automatique.
 *
 * 3. UNE COMMANDE N'EST PAS UN PAIEMENT. Un marchand haïtien qui commande
 *    quarante stands en discute d'abord : le transport dépend d'où il est, le
 *    délai dépend de l'import, et il paiera par MonCash ou à la livraison.
 *    Exiger une carte au moment du clic ferait perdre la quasi-totalité des
 *    commandes. La commande est donc une DEMANDE FERME, que TAGTOA confirme
 *    avec son transport et son délai ; le paiement vient après, sur un total
 *    que les deux parties connaissent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tagtoa_shop_items')) {
            Schema::create('tagtoa_shop_items', function (Blueprint $t) {
                $t->id();
                // Référence imprimée sur le bon de commande et le carton.
                $t->string('sku', 40)->unique();
                $t->string('name', 120);
                $t->string('description', 255)->nullable();
                $t->string('image_path')->nullable();
                $t->decimal('unit_price', 14, 2)->default(0);
                // La quantité MINIMALE d'une commande. Un stand se fabrique et
                // s'expédie par lots : accepter « 1 » ferait promettre un envoi
                // qui coûte plus cher que l'objet.
                $t->unsignedInteger('min_qty')->default(1);
                // Le pas de commande : on vend par cartons de 10, pas 13.
                $t->unsignedInteger('step_qty')->default(1);
                $t->unsignedInteger('lead_time_days')->nullable();
                $t->boolean('is_active')->default(true);
                $t->unsignedInteger('sort')->default(0);
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('tagtoa_shop_orders')) {
            Schema::create('tagtoa_shop_orders', function (Blueprint $t) {
                $t->id();
                $t->string('tenant_id', 64)->index();
                $t->string('reference', 40)->unique();
                // Même vocabulaire que les commandes des marchands : une seule
                // liste de statuts pour toute la plateforme, sinon « expédiée »
                // finit par vouloir dire deux choses différentes.
                $t->string('status', 24)->default('pending');
                $t->string('currency', 10)->default('USD');
                $t->decimal('subtotal', 14, 2)->default(0);
                // Le transport n'est PAS connu au moment du clic : il dépend
                // d'où est le marchand. TAGTOA le renseigne en confirmant.
                $t->decimal('shipping', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);
                $t->string('contact_name', 120)->nullable();
                $t->string('contact_phone', 40)->nullable();
                $t->string('address', 255)->nullable();
                $t->string('city', 80)->nullable();
                $t->string('note', 255)->nullable();
                // Ce que TAGTOA écrit en retour : délai, numéro de suivi, plage
                // de stands envoyée. Visible du marchand.
                $t->string('reply', 255)->nullable();
                $t->timestamp('placed_at')->nullable();
                $t->timestamp('confirmed_at')->nullable();
                $t->timestamp('shipped_at')->nullable();
                $t->timestamp('delivered_at')->nullable();
                $t->timestamps();

                $t->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('tagtoa_shop_order_items')) {
            Schema::create('tagtoa_shop_order_items', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('order_id')->index();
                // Nullable et sans contrainte : un article retiré du catalogue
                // ne doit pas effacer la commande qui l'a acheté.
                $t->unsignedBigInteger('item_id')->nullable();
                // FIGÉS à la commande. Le nom comme le prix : un article
                // renommé ne réécrit pas un bon de commande déjà envoyé.
                $t->string('sku', 40);
                $t->string('name', 120);
                $t->decimal('unit_price', 14, 2)->default(0);
                $t->unsignedInteger('qty')->default(1);
                $t->decimal('line_total', 14, 2)->default(0);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_shop_order_items');
        Schema::dropIfExists('tagtoa_shop_orders');
        Schema::dropIfExists('tagtoa_shop_items');
    }
};
