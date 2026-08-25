<?php

namespace Modules\Tagtoa\App\Support\Gateways;

use Illuminate\Support\Facades\Http;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA PAY — driver Stripe (Checkout Sessions, carte crédit/débit).
 * Tolérant (jamais d'exception qui casse le flux). Dormant tant que les
 * identifiants ne sont pas configurés. Stripe ne gère pas la HTG → repli manuel
 * si la devise n'est pas supportée.
 *
 * ⚠️ À tester en mode test (clé sk_test_…) avant la production.
 */
class StripeDriver implements GatewayDriver
{
    protected ?string $secret;

    public function __construct(?string $tenantId = null)
    {
        $cfg = GatewayManager::config('stripe', $tenantId);
        // credentials.secret = clé secrète (sk_live_… / sk_test_…).
        $this->secret = $cfg['credentials']['secret'] ?? null;
    }

    public function createPayment(PayTransaction $txn): ?string
    {
        if (! $this->secret || ! Stripe::supportsCurrency($txn->currency)) {
            return null;
        }

        $params = [
            'mode'                => 'payment',
            'client_reference_id' => $txn->reference,
            'success_url'         => route('tagtoa.pay.online.return', ['gateway' => 'stripe']).'?reference='.urlencode($txn->reference),
            'cancel_url'          => route('tagtoa.pay.result'),
            'metadata'            => ['reference' => $txn->reference],
            'payment_intent_data' => ['metadata' => ['reference' => $txn->reference]],
            'line_items'          => [[
                'quantity'   => 1,
                'price_data' => [
                    'currency'     => strtolower((string) $txn->currency),
                    'unit_amount'  => Stripe::amount($txn->amount, $txn->currency),
                    'product_data' => ['name' => 'TAGTOA — '.$txn->reference],
                ],
            ]],
        ];

        try {
            $res = Http::withToken($this->secret)->asForm()->acceptJson()->connectTimeout(5)->timeout(10)
                ->post(Stripe::API_BASE.'/v1/checkout/sessions', $params);

            $id = $res->json('id');
            $url = $res->json('url');
            if ($res->successful() && $id && $url) {
                $txn->update(['gateway_ref' => $id]);

                return $url;
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
        if (! $this->secret || ! $txn->gateway_ref) {
            return 'pending';
        }

        try {
            $res = Http::withToken($this->secret)->acceptJson()->connectTimeout(5)->timeout(10)
                ->get(Stripe::API_BASE.'/v1/checkout/sessions/'.$txn->gateway_ref);

            if (! $res->successful()) {
                return 'pending';
            }

            $status = Stripe::mapStatus($res->json('payment_status'), $res->json('status'));

            // Anti-fraude : le montant encaissé doit couvrir le montant attendu.
            if ($status === 'paid' && ! $this->amountMatches($res, $txn)) {
                return 'failed';
            }

            return $status;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return 'pending';
    }

    /** Anti-fraude : amount_total Stripe (unité mineure) ≥ montant attendu. */
    protected function amountMatches($res, PayTransaction $txn): bool
    {
        $total = $res->json('amount_total');
        if ($total === null) {
            return true; // pas d'info → on ne bloque pas
        }

        return (int) $total + 1 >= Stripe::amount($txn->amount, $txn->currency);
    }
}
