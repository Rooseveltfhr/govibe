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
     *
     * ⚠️ Ne regarde QUE le statut. Ne jamais l'appeler seul pour décider qu'une
     * commande est payée : passer par resolve(), qui vérifie aussi le montant.
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

    /**
     * Statut RÉEL d'une transaction, statut ET montant. PUR.
     *
     * Le statut seul ne suffit pas : un payeur peut envoyer moins que le montant
     * demandé. On compare donc ce qui a été reçu (`receivedf`) à ce qui était dû
     * (`amountf`) — les deux sont exprimés dans la MÊME crypto par CoinPayments,
     * ce qui évite toute reconversion depuis la gourde ou le dollar.
     *
     * Une sous-payée reste « pending », jamais « failed » : la crypto déjà
     * envoyée est réelle, et le payeur peut encore compléter. Si personne ne
     * complète, CoinPayments finit par expirer la transaction et renvoie un
     * statut négatif, qui devient « failed » par le chemin normal.
     *
     * Sans les champs de montant, on ne peut rien vérifier : on refuse de
     * conclure au paiement (échec fermé) plutôt que de croire le statut.
     *
     * @param  array  $result  bloc `result` de get_tx_info
     */
    public static function resolve(array $result): string
    {
        $status = self::mapStatus($result['status'] ?? null);

        if ($status !== 'paid') {
            return $status;
        }

        if (! isset($result['amountf'])) {
            return 'pending';
        }

        $due      = (float) $result['amountf'];
        $received = (float) ($result['receivedf'] ?? 0);

        // Tolérance d'un satoshi : les montants transitent en chaîne décimale et
        // un arrondi au dernier chiffre ne doit pas bloquer un paiement complet.
        return $received + 0.00000001 >= $due ? 'paid' : 'pending';
    }
}
