<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TAGTOA PAY — trace immuable de chaque transition d'une transaction de
 * paiement (créée → payée / échouée), pour PayTransaction (passerelles
 * Stripe/PayPal/MonCash/CoinPayments) ET ApiPayment (paiements demandés par
 * un site tiers).
 *
 * Signalé en audit : `CheckoutService::confirm()` et
 * `ApiPaymentService::markPaid()` font un `->update(['status' => …])` direct
 * sur la transaction — rien n'enregistre QUAND ni DEPUIS QUEL état le
 * paiement est passé « payé » ou « échoué », ce que CLAUDE.md §« Contraintes
 * non négociables » exige pour toute logique financière (« aucune écriture
 * directe sur les soldes »). Cette table ne REMPLACE pas la colonne
 * `status` existante (elle reste la source rapide pour l'affichage) ; elle
 * ajoute, À CÔTÉ, une ligne jamais modifiée ni supprimée par ligne de
 * code applicatif pour chaque transition PAYÉ/ÉCHOUÉ — l'historique que la
 * colonne seule ne peut pas porter.
 *
 * Note de périmètre : PAY n'a pas de « solde » à proprement parler (pas de
 * compte marchand porteur de valeur comme le wallet closed-loop de EVENT,
 * voir Support/Event/Ledger.php) — inventer des comptes fictifs pour
 * forcer un vrai grand livre en partie double serait une construction
 * spéculative. Cette table couvre ce qui existe réellement aujourd'hui :
 * la trace immuable d'un paiement passerelle, pas un bilan de comptes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagtoa_payment_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            // Morph manuel (string/int) plutôt que ->morphs() : les deux
            // tables concernées (tagtoa_pay_transactions, tagtoa_api_payments)
            // utilisent déjà des clés auto-incrémentées classiques, et un
            // morph nommé explicitement évite toute ambiguïté avec un futur
            // troisième type de paiement.
            $table->string('payable_type');
            $table->unsignedBigInteger('payable_id');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 10);
            $table->string('gateway')->nullable();
            $table->string('reference');
            $table->json('meta')->nullable();
            // created_at SEULEMENT — une ligne de grand livre ne se modifie
            // jamais après coup, donc aucun updated_at à lui donner.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'payable_type', 'payable_id']);
            $table->index(['payable_type', 'payable_id', 'to_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagtoa_payment_ledger_entries');
    }
};
