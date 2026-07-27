<?php

namespace Modules\Tagtoa\App\Support\Gateways;

/**
 * TAGTOA PAY — helpers PURS Stripe (Checkout Sessions), testables sans Laravel
 * ni réseau.
 *
 * Flux : Create Checkout Session (mode=payment) → redirection vers session.url →
 * au retour, Retrieve Session → payment_status=paid. Webhook signé (HMAC-SHA256).
 * Endpoint : https://api.stripe.com (form-urlencoded, clé secrète en Bearer).
 * Stripe ne traite PAS la gourde HTG → repli manuel si devise non supportée.
 */
class Stripe
{
    public const API_BASE = 'https://api.stripe.com';

    /** Devises Stripe courantes (Stripe ne gère PAS la HTG). */
    public const CURRENCIES = ['USD', 'EUR', 'CAD', 'GBP', 'AUD', 'MXN', 'BRL', 'JPY', 'CHF', 'DOP'];

    /** Devises « zéro décimale » (montant déjà en plus petite unité). */
    public const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'CLP', 'XAF', 'XOF', 'BIF', 'DJF', 'GNF', 'PYG', 'RWF', 'UGX', 'VUV', 'XPF'];

    /** Stripe ne traite pas la HTG : seules les devises supportées passent. PUR. */
    public static function supportsCurrency(?string $currency): bool
    {
        return in_array(strtoupper((string) $currency), self::CURRENCIES, true);
    }

    /** Une devise est-elle « zéro décimale » chez Stripe ? PUR. */
    public static function isZeroDecimal(?string $currency): bool
    {
        return in_array(strtoupper((string) $currency), self::ZERO_DECIMAL, true);
    }

    /**
     * Montant Stripe en plus petite unité (centimes), entier ≥ 1. PUR.
     *   USD 10.00 → 1000 · JPY 500 → 500 (zéro décimale).
     */
    public static function amount($value, ?string $currency = 'USD'): int
    {
        $v = max(0.0, (float) $value);
        $minor = self::isZeroDecimal($currency) ? round($v) : round($v * 100);

        return (int) max(1, $minor);
    }

    /**
     * Traduit l'état d'une Checkout Session → statut interne. PUR.
     *   payment_status=paid|no_payment_required → payé · session.status=expired → échec ·
     *   sinon → en attente.
     *
     * @param  string|null  $paymentStatus  session.payment_status
     * @param  string|null  $sessionStatus  session.status
     */
    public static function mapStatus(?string $paymentStatus, ?string $sessionStatus = null): string
    {
        $ps = strtolower(trim((string) $paymentStatus));
        if ($ps === 'paid' || $ps === 'no_payment_required') {
            return 'paid';
        }
        if (strtolower(trim((string) $sessionStatus)) === 'expired') {
            return 'failed';
        }

        return 'pending';
    }

    /**
     * Vérifie la signature d'un webhook Stripe (header « Stripe-Signature »). PUR.
     *
     * Format du header : « t=<timestamp>,v1=<hmac>,... ». On recalcule
     * HMAC-SHA256("<t>.<payload>") avec le secret et on compare en temps constant.
     *
     * @param  string  $payload    corps brut de la requête
     * @param  string  $header     valeur du header Stripe-Signature
     * @param  string  $secret     whsec_… (webhook signing secret)
     * @param  int     $tolerance  fenêtre temporelle autorisée en secondes (0 = ignorer)
     * @param  int|null $now       horodatage courant (injectable pour les tests)
     */
    public static function verifySignature(string $payload, string $header, string $secret, int $tolerance = 300, ?int $now = null): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                continue;
            }
            [$k, $v] = $kv;
            if ($k === 't') {
                $timestamp = $v;
            } elseif ($k === 'v1') {
                $signatures[] = $v;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $ok = false;
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                $ok = true;
                break;
            }
        }
        if (! $ok) {
            return false;
        }

        if ($tolerance > 0) {
            $current = $now ?? time();
            if (abs($current - (int) $timestamp) > $tolerance) {
                return false;
            }
        }

        return true;
    }
}
