<?php

namespace Modules\Tagtoa\App\Support\Gateways;

/**
 * TAGTOA PAY — helpers PURS CoinPayments (crypto : USDT/USDC/BTC/ETH/BNB…),
 * testables sans Laravel ni réseau.
 *
 * API : POST https://www.coinpayments.net/api.php (form-urlencoded), signée par
 * HMAC-SHA512 du corps avec la clé privée, header HMAC. Flux : create_transaction
 * → checkout_url (redirection) → IPN + get_tx_info pour confirmer.
 */
class CoinPayments
{
    public const API_URL = 'https://www.coinpayments.net/api.php';

    /** Signature HMAC-SHA512 du corps (querystring) avec la clé privée. PUR. */
    public static function sign(array $payload, string $privateKey): string
    {
        return hash_hmac('sha512', http_build_query($payload), $privateKey);
    }

    /** Devise crypto par défaut à recevoir si non précisée (stablecoin). PUR. */
    public static function defaultCoin(?string $hint = null): string
    {
        $map = ['usdt' => 'USDT.TRC20', 'usdc' => 'USDC', 'btc' => 'BTC', 'eth' => 'ETH', 'bnb' => 'BNB.BSC'];

        return $map[strtolower((string) $hint)] ?? 'USDT.TRC20';
    }

    /** Montant formaté (chaîne à 2 décimales). PUR. */
    public static function amount($value): string
    {
        return number_format(max(0.01, (float) $value), 2, '.', '');
    }

    /**
     * Traduit le statut numérique CoinPayments → statut interne. PUR.
     *   >= 100 ou == 2 : payé/complété · < 0 : échec/annulé · 0/1 : en attente.
     */
    public static function mapStatus($status): string
    {
        $s = (int) $status;
        if ($s >= 100 || $s === 2) {
            return 'paid';
        }
        if ($s < 0) {
            return 'failed';
        }

        return 'pending';
    }
}
