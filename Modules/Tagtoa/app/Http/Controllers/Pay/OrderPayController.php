<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Order as EventOrder;
use Modules\Tagtoa\App\Models\Menu\Order as MenuOrder;
use Modules\Tagtoa\App\Models\Store\Order as StoreOrder;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\App\Support\Pay\OnlineOptions;

/**
 * TAGTOA Pay — LA page « Payer » d'une commande (menu, boutique, billetterie).
 *
 * Une seule page, deux familles de moyens :
 *   - AUTOMATIQUE : les passerelles configurées par le fondateur (MonCash,
 *     Stripe, PayPal — sandbox ou production), proposées seulement si elles
 *     traitent la devise de la commande. Le client est redirigé, et la
 *     commande passe « Payée » toute seule quand la passerelle confirme.
 *   - MANUEL : les moyens que le MARCHAND a lui-même renseignés (numéro
 *     MonCash/NatCash, compte bancaire, instructions). Le client paie de son
 *     côté puis déclare « J'ai payé » : cela ne paie RIEN, c'est un message au
 *     marchand, qui vérifie et confirme (« Encaisser »).
 *
 * Aucune authentification : le client est un visiteur. La commande se retrouve
 * par sa référence aléatoire, comme la page de suivi.
 */
class OrderPayController extends Controller
{
    private const TYPES = ['menu' => MenuOrder::class, 'store' => StoreOrder::class, 'event' => EventOrder::class];

    public function show(string $type, string $reference): View
    {
        $order = $this->order($type, $reference);
        $methods = app(MerchantMethods::class)->active($order->tenant_id);

        return view('tagtoa::pay.order', [
            'order'    => $order,
            'type'     => $type,
            'merchant' => $this->merchantName($type, $order),
            'backUrl'  => $this->backUrl($type, $order),
            'trackUrl' => match ($type) {
                'menu'  => route('tagtoa.menu.track', $order->reference),
                'event' => route('tagtoa.event.order', $order->reference),
                default => null,
            },
            'online'   => $order->isPaid() ? [] : OnlineOptions::for($order->currency),
            'methods'  => $order->isPaid() ? collect() : $methods,
        ]);
    }

    /** « J'ai payé » — un message au marchand, jamais un paiement. */
    public function claim(Request $request, string $type, string $reference): RedirectResponse
    {
        $order = $this->order($type, $reference);
        if ($order->isPaid()) {
            return redirect()->route('tagtoa.pay.order', [$type, $reference]);
        }

        $methods = app(MerchantMethods::class)->active($order->tenant_id);
        $data = $request->validate([
            'payment_method_id' => ['required', 'integer', Rule::in($methods->pluck('id')->all())],
            'payer_name'        => ['nullable', 'string', 'max:120'],
            'payer_phone'       => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s]{6,20}$/'],
            'reference'         => ['nullable', 'string', 'max:120'],
        ]);

        $method = $methods->firstWhere('id', (int) $data['payment_method_id']);

        $order->forceFill([
            'payment_claim' => [
                'method'      => $method->display_label,
                'payer_name'  => $data['payer_name'] ?? null,
                'payer_phone' => $data['payer_phone'] ?? null,
                'reference'   => $data['reference'] ?? null,
            ],
            'payment_claimed_at' => now(),
        ])->save();

        return redirect()->route('tagtoa.pay.order', [$type, $reference])->with('claimed', true);
    }

    /**
     * L'adresse de la page « Payer » de cette commande — ou null si elle n'aurait
     * RIEN à proposer (aucune passerelle utilisable, aucun moyen du marchand) :
     * un bouton « Payer » qui ouvre une page vide est pire que pas de bouton.
     */
    public static function urlFor(string $type, MenuOrder|StoreOrder|EventOrder $order): ?string
    {
        if ($order->isPaid()) {
            return null;
        }
        if (empty(OnlineOptions::for($order->currency))
            && app(MerchantMethods::class)->active($order->tenant_id)->isEmpty()) {
            return null;
        }

        return route('tagtoa.pay.order', [$type, $order->reference]);
    }

    protected function order(string $type, string $reference): MenuOrder|StoreOrder|EventOrder
    {
        $model = self::TYPES[$type] ?? null;
        abort_unless($model, 404);

        return $model::where('reference', $reference)->firstOrFail();
    }

    protected function merchantName(string $type, MenuOrder|StoreOrder|EventOrder $order): string
    {
        return (string) match ($type) {
            'menu'  => optional($order->menu)->name,
            'event' => optional($order->event)->title,
            default => optional($order->store)->name,
        };
    }

    protected function backUrl(string $type, MenuOrder|StoreOrder|EventOrder $order): ?string
    {
        if ($type === 'menu' && $order->menu) {
            return route('tagtoa.menu.show', $order->menu->alias);
        }
        if ($type === 'store' && $order->store) {
            return route('tagtoa.store.show', $order->store->alias);
        }

        return null;
    }
}
