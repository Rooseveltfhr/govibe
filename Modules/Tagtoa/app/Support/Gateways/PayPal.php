<?php

namespace Modules\Tagtoa\App\Support\Gateways;

/**
 * TAGTOA PAY — helpers PURS PayPal (Orders v2 API), testables sans Laravel ni réseau.
 *
 * Flux : OAuth token (client_credentials) → Create Order (intent=CAPTURE) →
 * redirection vers le lien « approve » → au retour, Capture Order → COMPLETED.
 * Endpoints : sandbox api-m.sandbox.paypal.com · live api-m.paypal.com.
 */
class PayPal
{
    public const HOST_SANDBOX = 'https://api-m.sandbox.paypal.com';
    public const HOST_LIVE = 'https://api-m.paypal.com';

    /** Devises PayPal courantes (PayPal ne gère PAS la gourde HTG). */
    public const CURRENCIES = ['USD', 'EUR', 'CAD', 'GBP', 'AUD', 'MXN', 'BRL', 'JPY', 'CHF'];

    /** Base API selon le mode. PUR. */
    public static function apiBase(string $mode): string
    {
        return $mode === 'live' ? self::HOST_LIVE : self::HOST_SANDBOX;
    }

    /** PayPal ne traite pas la HTG : seules les devises supportées passent. PUR. */
    public static function supportsCurrency(?string $currency): bool
    {
        return in_array(strtoupper((string) $currency), self::CURRENCIES, true);
    }

    /**
     * Page d'atterrissage chez PayPal selon le moyen que le client a cliqué. PUR.
     *
     * TAGTOA propose « PayPal » et « Carte bancaire » comme deux entrées
     * distinctes, toutes deux traitées par PayPal. Sans cette distinction, les
     * deux ouvriraient l'écran de connexion PayPal — et le client qui n'a pas de
     * compte croirait qu'il ne peut pas payer, alors que PayPal accepte les
     * cartes sans compte.
     *
     * BILLING ouvre directement le formulaire carte. NO_PREFERENCE laisse PayPal
     * décider, ce qui convient à tous les autres cas.
     */
    public static function landingPage(?string $methodType): string
    {
        return $methodType === 'card' ? 'BILLING' : 'NO_PREFERENCE';
    }

    /** Montant formaté PayPal : chaîne à 2 décimales (« 10.00 »), min 0.01. PUR. */
    public static function amount($value): string
    {
        return number_format(max(0.01, (float) $value), 2, '.', '');
    }

    /**
     * Extrait le lien d'approbation (rel=approve) de la réponse Create Order. PUR.
     *
     * @param  array  $links  tableau links[] de la réponse PayPal
     */
    public static function approveLink(array $links): ?string
    {
        foreach ($links as $l) {
            if (($l['rel'] ?? null) === 'approve' && ! empty($l['href'])) {
                return $l['href'];
            }
        }

        return null;
    }

    /** Traduit le statut d'un order PayPal → statut interne. PUR. */
    public static function mapStatus(?string $status): string
    {
        $s = strtoupper(trim((string) $status));
        if ($s === 'COMPLETED') {
            return 'paid';
        }
        if (in_array($s, ['VOIDED', 'DECLINED', 'FAILED'], true)) {
            return 'failed';
        }

        // CREATED / SAVED / APPROVED / PAYER_ACTION_REQUIRED → en attente (capture requise)
        return 'pending';
    }
}
