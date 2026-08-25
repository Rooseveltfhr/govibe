<?php

namespace Modules\Tagtoa\App\Support\Gateways;

use Illuminate\Support\Facades\Http;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA PAY — driver PayPal (Orders v2). Tolérant (jamais d'exception qui casse
 * le flux). Dormant tant que les identifiants ne sont pas configurés.
 * PayPal ne gère pas la HTG → repli manuel si la devise n'est pas supportée.
 *
 * ⚠️ À tester en sandbox avant la production.
 */
class PayPalDriver implements GatewayDriver
{
    protected string $mode;
    protected ?string $clientId;
    protected ?string $secret;

    public function __construct(?string $tenantId = null)
    {
        $cfg = GatewayManager::config('paypal', $tenantId);
        $this->mode = $cfg['mode'] ?? 'sandbox';
        $this->clientId = $cfg['credentials']['client_id'] ?? null;
        $this->secret = $cfg['credentials']['secret'] ?? null;
    }

    public function createPayment(PayTransaction $txn): ?string
    {
        if (! PayPal::supportsCurrency($txn->currency)) {
            return null;
        }
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        try {
            $res = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(10)
                ->post(PayPal::apiBase($this->mode).'/v2/checkout/orders', [
                    'intent'         => 'CAPTURE',
                    'purchase_units' => [[
                        'custom_id'  => $txn->reference,
                        'invoice_id' => $txn->reference,
                        'amount'     => [
                            'currency_code' => strtoupper($txn->currency),
                            'value'         => PayPal::amount($txn->amount),
                        ],
                    ]],
                    'application_context' => [
                        'brand_name'          => 'TAGTOA',
                        'shipping_preference'  => 'NO_SHIPPING',
                        'user_action'          => 'PAY_NOW',
                        'return_url'           => route('tagtoa.pay.online.return', ['gateway' => 'paypal']).'?reference='.urlencode($txn->reference),
                        'cancel_url'           => route('tagtoa.pay.result'),
                    ],
                ]);

            $id = $res->json('id');
            if ($res->successful() && $id) {
                $txn->update(['gateway_ref' => $id]);

                return PayPal::approveLink((array) $res->json('links', []));
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return null;
    }

    public function verify(PayTransaction $txn): string
    {
        $token = $this->accessToken();
        if (! $token || ! $txn->gateway_ref) {
            return 'pending';
        }
        $base = PayPal::apiBase($this->mode);

        try {
            $res = Http::withToken($token)->acceptJson()->connectTimeout(5)->timeout(10)
                ->get($base.'/v2/checkout/orders/'.$txn->gateway_ref);
            $status = $res->json('status');

            // Approuvé par le payeur → on capture pour encaisser.
            if (strtoupper((string) $status) === 'APPROVED') {
                $cap = Http::withToken($token)->acceptJson()->asJson()->connectTimeout(5)->timeout(10)
                    ->post($base.'/v2/checkout/orders/'.$txn->gateway_ref.'/capture', new \stdClass);
                $status = $cap->json('status', $status);
            }

            if (PayPal::mapStatus($status) === 'paid' && ! $this->amountMatches($res, $txn)) {
                return 'failed';
            }

            return PayPal::mapStatus($status);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return 'pending';
    }

    /** Anti-fraude : le montant PayPal doit couvrir le montant attendu. */
    protected function amountMatches($res, PayTransaction $txn): bool
    {
        $value = $res->json('purchase_units.0.amount.value');
        if ($value === null) {
            return true; // pas d'info → on ne bloque pas (la capture a réussi)
        }

        return (float) $value + 0.001 >= (float) $txn->amount;
    }

    /** Jeton OAuth (client_credentials, Basic auth). Null si indisponible. */
    protected function accessToken(): ?string
    {
        if (! $this->clientId || ! $this->secret) {
            return null;
        }

        try {
            $res = Http::withBasicAuth($this->clientId, $this->secret)
                ->asForm()->acceptJson()->connectTimeout(5)->timeout(10)
                ->post(PayPal::apiBase($this->mode).'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ]);

            return $res->successful() ? $res->json('access_token') : null;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }

            return null;
        }
    }
}
