<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Menu\MenuOrderService;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA MENU — page publique (NFC / QR), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)
            ->with(['payPage', 'activeCategories.availableItems'])
            ->firstOrFail();

        $menu->incrementQuietly('views');

        // Catégories non vides uniquement.
        $categories = $menu->activeCategories->filter(fn ($c) => $c->availableItems->isNotEmpty())->values();

        return view('tagtoa::menu.show', ['menu' => $menu, 'categories' => $categories]);
    }

    /** Capture une commande (prix imposés côté serveur). Renvoie JSON. */
    public function order(Request $request, string $alias): JsonResponse
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)->with('payPage')->firstOrFail();
        abort_unless($menu->ordering_enabled, 404);

        $data = $request->validate([
            'items'          => ['required', 'array', 'min:1'],
            'items.*.id'     => ['required', 'integer'],
            'items.*.qty'    => ['required', 'integer', 'min:1', 'max:99'],
            'customer_name'  => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'table_label'    => ['nullable', 'string', 'max:40'],
            'note'           => ['nullable', 'string', 'max:500'],
            'client_uuid'    => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $order = app(MenuOrderService::class)->placeOrder($menu, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => __('Votre commande est vide.')], 422);
        }

        return response()->json([
            'ok'           => true,
            'reference'    => $order->reference,
            'total'        => Money::format($order->total, $order->currency),
            'whatsapp_url' => $this->whatsappUrl($menu, $order),
            'pay_url'      => $menu->payPage ? url('/pay/'.$menu->payPage->alias) : null,
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
