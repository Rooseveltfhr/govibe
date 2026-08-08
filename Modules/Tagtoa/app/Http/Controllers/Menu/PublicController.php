<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Menu\Order;
use Modules\Tagtoa\App\Models\Review\Review;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Services\Review\ReviewService;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA MENU — page publique (NFC / QR), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)
            ->with(['payPage', 'activeCategories.availableItems.options.choices'])
            ->firstOrFail();

        $menu->incrementQuietly('views');

        // Catégories non vides uniquement.
        $categories = $menu->activeCategories->filter(fn ($c) => $c->availableItems->isNotEmpty())->values();

        $summary = app(ReviewService::class)->summary('menu', (int) $menu->id);
        $reviews = Review::query()->forSubject('menu', (int) $menu->id)->approved()->latest()->limit(20)->get();

        return view('tagtoa::menu.show', [
            'menu'       => $menu,
            'categories' => $categories,
            'reviews'    => $reviews,
            'summary'    => $summary,
        ]);
    }

    /** Capture une commande (prix imposés côté serveur). Renvoie JSON. */
    public function order(Request $request, string $alias): JsonResponse
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)->with('payPage')->firstOrFail();
        abort_unless($menu->ordering_enabled, 404);

        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.id'         => ['required', 'integer'],
            'items.*.qty'        => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.options'    => ['nullable', 'array'],
            'items.*.options.*'  => ['integer'],
            'order_type'         => ['nullable', 'string', Rule::in(Order::ORDER_TYPES)],
            'tip'                => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'customer_name'      => ['nullable', 'string', 'max:120'],
            'customer_phone'     => ['nullable', 'string', 'max:40'],
            'table_label'        => ['nullable', 'string', 'max:40'],
            'delivery_address'   => ['nullable', 'string', 'max:200'],
            'note'               => ['nullable', 'string', 'max:500'],
            'client_uuid'        => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $order = app(MenuOrderService::class)->placeOrder($menu, $data);
        } catch (\RuntimeException $e) {
            $message = match ($e->getMessage()) {
                'out_of_stock'            => __('Un article est en rupture de stock. Ajustez votre commande.'),
                'missing_required_option' => __('Choisissez une option obligatoire pour chaque article.'),
                default                   => __('Votre commande est vide.'),
            };

            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        return response()->json([
            'ok'           => true,
            'reference'    => $order->reference,
            'total'        => Money::format($order->total, $order->currency),
            'track_url'    => route('tagtoa.menu.track', $order->reference),
            'whatsapp_url' => $this->whatsappUrl($menu, $order),
            'pay_url'      => $menu->payPage ? url('/pay/'.$menu->payPage->alias) : null,
            'checkout_url' => (\Modules\Tagtoa\App\Support\GatewayManager::enabled('moncash')
                && \Modules\Tagtoa\App\Support\Gateways\MonCash::supportsCurrency($order->currency))
                ? route('tagtoa.pay.online.start', ['gateway' => 'moncash', 'type' => 'menu', 'orderId' => $order->id])
                : null,
        ]);
    }

    /** Page publique de suivi de commande (statut en temps réel, sans auth). */
    public function track(string $reference): View
    {
        $order = Order::where('reference', $reference)->with(['items', 'menu'])->firstOrFail();

        return view('tagtoa::menu.track', ['order' => $order, 'menu' => $order->menu]);
    }

    /** JSON léger pour le polling de la page de suivi. */
    public function status(string $reference): JsonResponse
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        return response()->json([
            'status'         => $order->status,
            'status_label'   => __($order->status_meta['label']),
            'payment_status' => $order->payment_status,
        ]);
    }

    /** Lien WhatsApp pré-rempli incluant la référence de commande. */
    protected function whatsappUrl(Menu $menu, $order): ?string
    {
        if (! $menu->whatsapp_digits) {
            return null;
        }
        $lines = [__('Bonjour').' '.$menu->name.', '.__('je voudrais commander :')];
        foreach ($order->items as $i) {
            $lines[] = '• '.$i->qty.'x '.$i->name;
        }
        if ($order->table_label) {
            $lines[] = __('N° table (optionnel)').': '.$order->table_label;
        }
        $lines[] = '';
        $lines[] = __('Référence').': '.$order->reference;
        $lines[] = __('Total').': '.Money::format($order->total, $order->currency);

        return 'https://wa.me/'.$menu->whatsapp_digits.'?text='.rawurlencode(implode("\n", $lines));
    }
}
