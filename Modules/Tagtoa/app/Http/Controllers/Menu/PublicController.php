<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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
    /**
     * Durée de cache des DONNÉES de la page (secondes) — jamais le HTML rendu :
     * la page contient un jeton CSRF lié à la session du visiteur (formulaire de
     * commande) ; mettre en cache le rendu figerait le jeton du premier visiteur
     * et casserait la commande de tous les suivants pendant la fenêtre de cache.
     * On cache donc uniquement les requêtes BD (coûteuses), le rendu Blade
     * (rapide, déjà compilé) reste frais à chaque requête.
     */
    private const PUBLIC_CACHE_TTL = 20;

    public function show(string $alias): View
    {
        $data = Cache::remember("tagtoa:menu:show:$alias", self::PUBLIC_CACHE_TTL, function () use ($alias) {
            $menu = Menu::where('alias', $alias)->where('is_active', true)
                ->with(['payPage', 'activeCategories.availableItems.options.choices'])
                ->firstOrFail();

            $menu->incrementQuietly('views');

            // Catégories non vides uniquement.
            $categories = $menu->activeCategories->filter(fn ($c) => $c->availableItems->isNotEmpty())->values();

            $summary = app(ReviewService::class)->summary('menu', (int) $menu->id);
            $reviews = Review::query()->forSubject('menu', (int) $menu->id)->approved()->latest()->limit(20)->get();

            return compact('menu', 'categories', 'reviews', 'summary');
        });

        return view('tagtoa::menu.show', $data);
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
                'closed'                  => __('Ce commerce est fermé pour le moment. Revenez pendant les heures d\'ouverture.'),
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

    /* ---------- PWA (installable + hors ligne) ---------------------------
       Même schéma que le POS (PosController::manifest/icon/serviceWorker) :
       manifeste JSON, icône SVG vectorielle (aucun fichier binaire à
       publier), service worker « app shell » — network-first pour la page,
       cache-first pour le reste, GET seulement.

       La connexion en Haïti (et ailleurs) est souvent lente ou coupée : un
       client qui a déjà ouvert une carte doit pouvoir la rouvrir sans
       réseau. Chaque menu a SON PROPRE service worker (scope = /menu/{alias}
       exactement) : la carte du restaurant d'à côté n'est jamais mêlée à la
       sienne, et désinstaller un menu ne touche jamais les autres. -------- */

    /** Manifeste Web App de CE menu — nom, couleur et icône lui appartiennent. */
    public function manifest(string $alias): JsonResponse
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)->firstOrFail();
        $scope = url('/menu/'.$alias);
        $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $menu->accent_color) ? $menu->accent_color : '#2cb809';

        return response()->json([
            'name'             => $menu->name.' — TAGTOA Menu',
            'short_name'       => Str::limit($menu->name, 12, ''),
            'start_url'        => $scope,
            'scope'            => $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => $menu->theme === 'dark' ? '#0A0A0A' : '#F5F5F3',
            'theme_color'      => $accent,
            'lang'             => app()->getLocale(),
            'icons'            => [
                ['src' => route('tagtoa.menu.icon', $alias), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ]);
    }

    /** Icône SVG (vectorielle) — initiale du menu sur sa couleur d'accent. */
    public function icon(string $alias)
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)->firstOrFail();
        $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $menu->accent_color) ? $menu->accent_color : '#2cb809';
        $lettre = e(Str::upper(Str::substr($menu->name, 0, 1)) ?: 'T');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">'
            .'<rect width="512" height="512" rx="96" fill="'.$accent.'"/>'
            .'<text x="256" y="256" font-family="sans-serif" font-size="260" font-weight="700" '
            .'fill="#fff" text-anchor="middle" dominant-baseline="central">'.$lettre.'</text>'
            .'</svg>';

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Service worker : cache l'enveloppe (app shell) pour un usage hors
     * ligne. Ne touche jamais aux requêtes non-GET : une commande (POST) doit
     * échouer nettement hors ligne pour que la file d'attente côté client
     * (voir show.blade.php) prenne le relais, jamais une réponse mise en
     * cache par erreur qui ferait croire qu'elle est passée.
     */
    public function serviceWorker(string $alias)
    {
        $scope = url('/menu/'.$alias);
        $js = <<<JS
const CACHE = 'tagtoa-menu-{$alias}-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
// Network-first pour la navigation (HTML : toujours la carte à jour quand la
// connexion le permet), cache-first pour le reste. GET seulement — une
// commande (POST) n'est jamais interceptée.
self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then(res => { const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c)); return res; })
                      .catch(() => caches.match(req))
        );
        return;
    }
    e.respondWith(
        caches.match(req).then(hit => hit || fetch(req).then(res => {
            if (res && res.status === 200 && (res.type === 'basic' || res.type === 'cors')) {
                const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c));
            }
            return res;
        }).catch(() => hit))
    );
});
JS;

        return response($js, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', $scope);
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
