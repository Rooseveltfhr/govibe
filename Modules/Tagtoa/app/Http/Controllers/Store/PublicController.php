<?php

namespace Modules\Tagtoa\App\Http\Controllers\Store;

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
        ]);

        try {
            $order = app(StoreOrderService::class)->placeOrder($store, $data);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage() === 'out_of_stock'
                ? __('Un produit est en rupture de stock. Ajustez votre panier.')
                : __('Votre panier est vide.');

            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return response()->json([
            'ok'           => true,
            'reference'    => $order->reference,
            'total'        => Money::format($order->total, $order->currency),
            'whatsapp_url' => $this->whatsappUrl($store, $order),
            'pay_url'      => $store->payPage ? url('/pay/'.$store->payPage->alias) : null,
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
