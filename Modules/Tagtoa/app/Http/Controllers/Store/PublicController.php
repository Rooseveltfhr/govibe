<?php

namespace Modules\Tagtoa\App\Http\Controllers\Store;

use Modules\Tagtoa\App\Exceptions\OrderRuleException;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Store\Order;
use Modules\Tagtoa\App\Models\Store\Store;
use Modules\Tagtoa\App\Services\Store\StoreOrderService;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA STORE — boutique publique (NFC / QR), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $store = Store::where('alias', $alias)->where('is_published', true)
            ->with(['payPage', 'availableProducts'])
            ->firstOrFail();

        $store->incrementQuietly('views');

        // Produits groupés par catégorie (les vedettes d'abord dans chaque groupe).
        $groups = $store->availableProducts
            ->sortByDesc('is_featured')
            ->groupBy(fn ($p) => $p->category ?: __('Produits'));

        return view('tagtoa::store.show', [
            'store'  => $store,
            'groups' => $groups,
            // CUR-3 : taux actifs du commerce vers la devise de la boutique (affichage ; le serveur recalcule).
            'taux'   => app(\Modules\Tagtoa\App\Services\Currency\CurrencyRateService::class)->activeFor($store->tenant_id, $store->currency ?: 'HTG'),
        ]);
    }

    /** Capture une commande boutique (prix imposés serveur). Renvoie JSON. */
    public function order(Request $request, string $alias): JsonResponse
    {
        $store = Store::where('alias', $alias)->where('is_published', true)->with('payPage')->firstOrFail();

        $data = $request->validate([
            'items'            => ['required', 'array', 'min:1'],
            'items.*.id'       => ['required', 'integer'],
            'items.*.qty'      => ['required', 'integer', 'min:1', 'max:99'],
            'customer_name'    => ['required', 'string', 'max:120'],
            'customer_phone'   => ['required', 'string', 'max:40'],
            'customer_address' => ['nullable', 'string', 'max:200'],
            'note'             => ['nullable', 'string', 'max:500'],
            'client_uuid'      => ['nullable', 'string', 'max:64'],
            'expected_total'   => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $order = app(StoreOrderService::class)->placeOrder($store, $data);
        } catch (OrderRuleException $e) {
            $message = match ($e->getMessage()) {
                'out_of_stock'          => __('Un produit est en rupture de stock. Ajustez votre panier.'),
                'amount_too_large'      => __('Le montant de la commande est trop élevé.'),
                'rate_changed'          => __('Les prix ont changé. Le nouveau total est :t. Vérifiez votre panier puis confirmez de nouveau.', ['t' => Money::format($e->newTotal ?? 0, $store->currency ?: 'HTG')]),
                'currency_rate_missing' => __('Le prix d\'un produit n\'est plus disponible dans la devise de la boutique. Rechargez la page.'),
                default                 => __('Votre panier est vide.'),
            };

            return response()->json(['ok' => false, 'message' => $message] + ($e->getMessage() === 'rate_changed' ? ['new_total' => $e->newTotal] : []), 422);
        }

        return response()->json([
            'ok'           => true,
            'reference'    => $order->reference,
            'total'        => Money::format($order->total, $order->currency),
            'whatsapp_url' => $this->whatsappUrl($store, $order),
            'pay_url'      => \Modules\Tagtoa\App\Http\Controllers\Pay\OrderPayController::urlFor('store', $order),
            'checkout_url' => null,
        ]);
    }

    /** Lien WhatsApp pré-rempli incluant la référence de commande. */
    protected function whatsappUrl(Store $store, Order $order): ?string
    {
        if (! $store->whatsapp_digits) {
            return null;
        }
        $lines = [__('Bonjour').' '.$store->name.', '.__('je voudrais commander :')];
        foreach ($order->items as $i) {
            $lines[] = '• '.$i->qty.'x '.$i->name;
        }
        if ($order->customer_address) {
            $lines[] = __('Livraison').': '.$order->customer_address;
        }
        $lines[] = '';
        $lines[] = __('Référence').': '.$order->reference;
        $lines[] = __('Total').': '.Money::format($order->total, $order->currency);

        return 'https://wa.me/'.$store->whatsapp_digits.'?text='.rawurlencode(implode("\n", $lines));
    }
}
