<?php

namespace Modules\Tagtoa\App\Http\Controllers\Card;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Card\CardAccount;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;
use Modules\Tagtoa\App\Support\GatewayManager;

/**
 * TAGTOA CARD — recharge EN LIGNE par le titulaire (public, pas d'auth).
 * Le titulaire saisit le code de sa carte + un montant, paie via une passerelle,
 * et son solde est crédité à la confirmation (idempotent).
 */
class PublicController extends Controller
{
    public function recharge(Request $request): View
    {
        $code = strtoupper(trim((string) $request->query('code', '')));
        $card = $code !== '' ? app(CardWalletService::class)->resolveByCode($code) : null;
        $currency = $card?->currency ?: 'HTG';

        return view('tagtoa::card.recharge', [
            'code'     => $code,
            'card'     => $card,
            'gateways' => $this->availableGateways($currency),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code'    => ['required', 'string', 'max:40'],
            'amount'  => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'gateway' => ['required', 'string', 'max:20'],
        ]);

        $card = app(CardWalletService::class)->resolveByCode($data['code']);
        if (! $card) {
            return back()->withInput()->with('error', __('Carte TAGTOA introuvable.'));
        }
        if (! $card->isSpendable()) {
            return back()->withInput()->with('error', __('Carte inactive (bloquée ou perdue).'));
        }

        $gateways = $this->availableGateways($card->currency);
        if (! array_key_exists($data['gateway'], $gateways)) {
            return back()->withInput()->with('error', __('Moyen de recharge indisponible pour cette devise.'));
        }

        $url = app(CheckoutService::class)->startCardTopup($card, (float) $data['amount'], $data['gateway']);
        if (! $url) {
            return back()->withInput()->with('error', __('La recharge en ligne n\'a pas pu démarrer. Réessayez.'));
        }

        return redirect()->away($url);
    }

    /** Passerelles activées qui acceptent la devise de la carte. */
    protected function availableGateways(string $currency): array
    {
        $c = strtoupper($currency);
        $support = [
            'moncash'      => fn () => \Modules\Tagtoa\App\Support\Gateways\MonCash::supportsCurrency($c),
            'stripe'       => fn () => \Modules\Tagtoa\App\Support\Gateways\Stripe::supportsCurrency($c),
            'paypal'       => fn () => \Modules\Tagtoa\App\Support\Gateways\PayPal::supportsCurrency($c),
            'coinpayments' => fn () => $c !== 'HTG', // crypto : uniquement devises fortes
        ];
        $labels = [
            'moncash' => 'MonCash', 'stripe' => __('Carte (VISA/Mastercard)'),
            'paypal' => 'PayPal', 'coinpayments' => __('Crypto (USDT/BTC…)'),
        ];

        $out = [];
        foreach ($support as $driver => $ok) {
            if (GatewayManager::enabled($driver) && $ok()) {
                $out[$driver] = $labels[$driver] ?? ucfirst($driver);
            }
        }

        return $out;
    }
}
