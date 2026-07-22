<?php

namespace Modules\Tagtoa\App\Support\Gateways;

use Modules\Tagtoa\App\Models\Pay\PayTransaction;

/**
 * Contrat commun d'un driver de passerelle API (MonCash, Stripe…).
 */
interface GatewayDriver
{
    /** Crée un paiement chez la passerelle et retourne l'URL de redirection client (ou null si échec). */
    public function createPayment(PayTransaction $txn): ?string;

    /** Vérifie l'état d'un paiement → 'paid' | 'pending' | 'failed'. */
    public function verify(PayTransaction $txn): string;
}
