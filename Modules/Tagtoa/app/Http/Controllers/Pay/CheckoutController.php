<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pay\PayTransaction;
use Modules\Tagtoa\App\Services\Pay\CheckoutService;

/**
 * TAGTOA PAY — checkout en ligne (passerelle API). Public, pas d'auth.
 *
 * start   : crée le paiement et redirige vers la passerelle.
 * return  : la passerelle renvoie le client ici → on confirme.
 * webhook : notification serveur-à-serveur (idempotente) — best-effort.
 */
class CheckoutController extends Controller
{
    /** Types de commande acceptés (anti-injection). */
    protected const TYPES = ['store', 'menu', 'event'];

    public function start(Request $request, string $gateway, string $type, int $orderId): RedirectResponse
    {
        abort_unless(in_array($type, self::TYPES, true), 404);

        $url = app(CheckoutService::class)->start($type, $orderId, $gateway);

        if (! $url) {
            // Passerelle indisponible → repli : on affiche l'écran de résultat "en attente".
            return redirect()->route('tagtoa.pay.result')->with('pay_status', 'unavailable');
        }

        // Corrélation du retour via la session (même navigateur).
        session(['tagtoa_pay_ref' => $this->lastRef($type, $orderId, $gateway)]);

        return redirect()->away($url);
    }

    public function return(Request $request, string $gateway): RedirectResponse
    {
        $ref = $request->query('reference') ?: session('tagtoa_pay_ref');
        $paid = $ref ? app(CheckoutService::class)->confirm($ref) : false;
        session()->forget('tagtoa_pay_ref');

        return redirect()->route('tagtoa.pay.result')->with('pay_status', $paid ? 'paid' : 'pending');
    }

    /** Webhook/IPN passerelle (idempotent). Répond toujours 200. */
    public function webhook(Request $request, string $gateway)
    {
        $ref = $request->input('reference') ?: $request->input('orderId');
        if ($ref) {
            app(CheckoutService::class)->confirm($ref);
        }

        return response()->json(['ok' => true]);
    }

    public function result(): View
    {
        return view('tagtoa::pay.result', ['status' => session('pay_status', 'pending')]);
    }

    protected function lastRef(string $type, int $orderId, string $gateway): ?string
    {
        return PayTransaction::where('order_type', $type)->where('order_id', $orderId)
            ->where('gateway', $gateway)->latest()->value('reference');
    }
}
