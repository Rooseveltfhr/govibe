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

    public function __construct()
    {
        $cfg = GatewayManager::config('stripe');
        // credentials.secret = clé secrète (sk_live_… / sk_test_…).
        $secret = $cfg['credentials']['secret'] ?? null;

        // La clé doit correspondre à l'environnement annoncé dans le
        // super-admin (voir Stripe::modeMatchesKey()) : sinon on désactive le
        // driver plutôt que de risquer de facturer un vrai client en pensant
        // tester, ou de ne jamais encaisser en pensant être en production.
        $mode = $cfg['mode'] ?? 'sandbox';
        $this->secret = Stripe::modeMatchesKey($mode, $secret) ? $secret : null;
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

            // Anti-fraude : un « payé » n'est cru que si la passerelle DIT le montant et la devise, et
            // qu'ils couvrent la transaction. Une réponse « payée » sans montant n'est PAS une preuve :
            // elle reste en attente (la prochaine vérification la tranchera) au lieu d'être acceptée.
            if ($status === 'paid') {
                $verdict = $this->amountVerdict($res, $txn);
                if ($verdict !== 'ok') {
                    return $verdict;
                }
            }

            return $status;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }

        return 'pending';
    }

    /**
     * Anti-fraude : amount_total Stripe (unité mineure) ≥ montant attendu, dans la MÊME devise.
     *
     * @return string 'ok' | 'pending' (montant ou devise absents : rien ne prouve le paiement) | 'failed' (insuffisant ou autre devise)
     */
    protected function amountVerdict($res, PayTransaction $txn): string
    {
        $total    = $res->json('amount_total');
        $currency = $res->json('currency');
        if ($total === null || $currency === null || $currency === '') {
            return 'pending';
        }
        if (strtoupper((string) $currency) !== strtoupper((string) $txn->currency)) {
            return 'failed';
        }

        return (int) $total + 1 >= Stripe::amount($txn->amount, $txn->currency) ? 'ok' : 'failed';
    }
}
