<?php

namespace Modules\Tagtoa\App\Support\Gateways;

use Illuminate\Support\Facades\Http;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA PAY — driver CoinPayments (crypto : USDT/USDC/BTC/ETH/BNB…).
 * Tolérant (jamais d'exception). Dormant tant que les identifiants ne sont pas
 * configurés. La devise crypto reçue est déduite de meta['coin'] (défaut USDT).
 *
 * ⚠️ À tester avec un vrai compte avant la production.
 */
class CoinPaymentsDriver implements GatewayDriver
{
    protected ?string $publicKey;
    protected ?string $privateKey;

    public function __construct()
    {
        $cfg = GatewayManager::config('coinpayments');
        $this->publicKey = $cfg['credentials']['public_key'] ?? null;
        $this->privateKey = $cfg['credentials']['private_key'] ?? null;
    }

    public function createPayment(PayTransaction $txn): ?string
    {
        if (! $this->publicKey || ! $this->privateKey) {
            return null;
        }

        $coin = CoinPayments::defaultCoin($txn->meta['coin'] ?? null);
        $payload = [
            'version'     => 1,
            'cmd'         => 'create_transaction',
            'key'         => $this->publicKey,
            'format'      => 'json',
            'amount'      => CoinPayments::amount($txn->amount),
            'currency1'   => strtoupper($txn->currency),
            'currency2'   => $coin,
            'item_name'   => 'TAGTOA '.$txn->reference,
            'invoice'     => $txn->reference,
            'custom'      => $txn->reference,
            'ipn_url'     => route('tagtoa.pay.online.webhook', ['gateway' => 'coinpayments']),
            'success_url' => route('tagtoa.pay.online.return', ['gateway' => 'coinpayments']).'?reference='.urlencode($txn->reference),
            'cancel_url'  => route('tagtoa.pay.result'),
        ];

        $result = $this->call($payload);
        if ($result && ! empty($result['txn_id']) && ! empty($result['checkout_url'])) {
            $txn->update(['gateway_ref' => $result['txn_id']]);

            return $result['checkout_url'];
        }

        return null;
    }

    public function verify(PayTransaction $txn): string
    {
        if (! $txn->gateway_ref || ! $this->publicKey || ! $this->privateKey) {
            return 'pending';
        }

        $result = $this->call([
            'version' => 1,
            'cmd'     => 'get_tx_info',
            'key'     => $this->publicKey,
            'format'  => 'json',
            'txid'    => $txn->gateway_ref,
        ]);

        return $result && array_key_exists('status', $result)
            ? CoinPayments::mapStatus($result['status'])
            : 'pending';
    }

    /** Appel signé HMAC à l'API CoinPayments. Retourne result[] ou null. */
    protected function call(array $payload): ?array
    {
        try {
            $res = Http::asForm()->acceptJson()->timeout(20)
                ->withHeaders(['HMAC' => CoinPayments::sign($payload, $this->privateKey)])
                ->post(CoinPayments::API_URL, $payload);

            if ($res->successful() && $res->json('error') === 'ok') {
                return (array) $res->json('result', []);
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return null;
    }
}
