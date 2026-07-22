<?php

namespace Modules\Tagtoa\App\Support\Gateways;

/**
 * TAGTOA PAY — helpers PURS MonCash (Digicel Haïti), testables sans Laravel ni réseau.
 *
 * Endpoints (business API) :
 *   sandbox : https://sandbox.moncashbutton.digicel.com
 *   live    : https://moncashbutton.digicel.com
 * Flux : OAuth token → CreatePayment (token) → redirection gateway → RetrieveTransactionPayment.
 */
class MonCash
{
    public const HOST_SANDBOX = 'https://sandbox.moncashbutton.digicel.com';
    public const HOST_LIVE = 'https://moncashbutton.digicel.com';

    /** Base API REST selon le mode. PUR. */
    public static function apiBase(string $mode): string
    {
        return ($mode === 'live' ? self::HOST_LIVE : self::HOST_SANDBOX).'/Api';
    }

    /** URL de redirection du client vers la page de paiement MonCash. PUR. */
    public static function redirectUrl(string $mode, string $paymentToken): string
    {
        $host = $mode === 'live' ? self::HOST_LIVE : self::HOST_SANDBOX;

        return $host.'/Moncash-middleware/Payment/Redirect?token='.rawurlencode($paymentToken);
    }

    /** MonCash ne traite que la Gourde haïtienne. PUR. */
    public static function supportsCurrency(?string $currency): bool
    {
        return strtoupper((string) $currency) === 'HTG';
    }

    /** Montant formaté pour l'API (entier de gourdes, jamais négatif/zéro). PUR. */
    public static function amount($value): int
    {
        return max(1, (int) round((float) $value));
    }

    /**
     * Traduit le message de statut MonCash → statut interne.
     * MonCash renvoie message == 'successful' quand payé. PUR.
     */
    public static function mapStatus(?string $message): string
    {
        $m = strtolower(trim((string) $message));
        if ($m === 'successful') {
            return 'paid';
        }
        if (in_array($m, ['failed', 'declined', 'cancelled', 'canceled'], true)) {
            return 'failed';
        }

        return 'pending';
    }
}
