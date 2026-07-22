<?php

namespace Modules\Tagtoa\App\Support\Gateways;

use Illuminate\Support\Facades\Http;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA PAY — driver MonCash (Digicel Haïti), API business.
 *
 * Tolérant : toute erreur réseau/API renvoie null (createPayment) ou 'pending'
 * (verify) — jamais d'exception qui casse le flux. Dormant tant que les
 * identifiants ne sont pas configurés (GatewayManager::enabled('moncash')).
 *
 * ⚠️ À tester avec de vrais identifiants sandbox avant la production.
 */
class MonCashDriver implements GatewayDriver
{
    protected string $mode;
    protected ?string $clientId;
    protected ?string $secret;

    public function __construct()
    {
        $cfg = GatewayManager::config('moncash');
        $this->mode = $cfg['mode'] ?? 'sandbox';
        $this->clientId = $cfg['credentials']['client_id'] ?? null;
        $this->secret = $cfg['credentials']['secret'] ?? null;
    }

    public function createPayment(PayTransaction $txn): ?string
    {
        if (! MonCash::supportsCurrency($txn->currency)) {
            return null;
        }
        $token = $this->accessToken();
        if (! $token) {
            return null;
        }

        try {
            $res = Http::withToken($token)->acceptJson()->asJson()
                ->timeout(20)
                ->post(MonCash::apiBase($this->mode).'/v1/CreatePayment', [
                    'amount'  => MonCash::amount($txn->amount),
                    'orderId' => $txn->reference,
                ]);

            $payToken = $res->json('payment_token.token');
            if ($res->successful() && $payToken) {
                $txn->update(['gateway_ref' => $payToken]);

                return MonCash::redirectUrl($this->mode, $payToken);
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
        if (! $token) {
            return 'pending';
        }

        try {
            $res = Http::withToken($token)->acceptJson()->asJson()
                ->timeout(20)
                ->post(MonCash::apiBase($this->mode).'/v1/RetrieveTransactionPayment', [
                    'orderId' => $txn->reference,
                ]);

            $payment = (array) $res->json('payment', []);
            if ($res->successful() && $payment) {
                // Sécurité : le montant confirmé doit correspondre (anti-fraude).
                $paidCost = MonCash::amount($payment['cost'] ?? 0);
                if ($paidCost < MonCash::amount($txn->amount)) {
                    return 'failed';
                }

                return MonCash::mapStatus($payment['message'] ?? null);
            }
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return 'pending';
    }

    /** Jeton OAuth (client_credentials, Basic auth). Null si indisponible. */
    protected function accessToken(): ?string
    {
        if (! $this->clientId || ! $this->secret) {
            return null;
        }

        try {
            $res = Http::withBasicAuth($this->clientId, $this->secret)
                ->asForm()->acceptJson()->timeout(20)
                ->post(MonCash::apiBase($this->mode).'/oauth/token', [
                    'scope'      => 'read,write',
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
