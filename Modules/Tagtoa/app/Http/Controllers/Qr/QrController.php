<?php

namespace Modules\Tagtoa\App\Http\Controllers\Qr;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Site\Site;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — QR & partage : un QR téléchargeable + affiche imprimable
 * pour chaque ressource publique du marchand.
 */
class QrController extends Controller
{
    /** type => [modèle, préfixe URL, icône, libellé]. */
    private const TYPES = [
        'site'  => [Site::class,        '/site/',  'fa-globe',                'Site web'],
        'menu'  => [Menu::class,        '/menu/',  'fa-utensils',             'Menu'],
        'pay'   => [PaymentPage::class, '/pay/',   'fa-money-bill-transfer',  'Paiement'],
        'links' => [LinkPage::class,    '/links/', 'fa-link',                 'Liens'],
        'event' => [Event::class,       '/event/', 'fa-ticket',               'Événement'],
    ];

    public function index(): View
    {
        $tid = Tenant::id();
        $items = [];
        foreach (self::TYPES as $type => [$model, $prefix, $icon, $label]) {
            try {
                $rows = $model::query()->where('tenant_id', $tid)->get();
            } catch (\Throwable $e) {
                continue;
            }
            foreach ($rows as $r) {
                $items[] = [
                    'type'  => $type,
                    'id'    => $r->id,
                    'icon'  => $icon,
                    'label' => $label,
                    'name'  => $r->name ?? $r->title ?? $r->alias,
                    'alias' => $r->alias,
                    'url'   => url($prefix.$r->alias),
                ];
            }
        }

        return view('tagtoa::qr.index', compact('items'));
    }

    public function poster(string $type, int $id): View
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$model, $prefix, $icon, $label] = self::TYPES[$type];

        $r = $model::where('tenant_id', Tenant::id())->findOrFail($id);
        $data = [
            'name'  => $r->name ?? $r->title ?? $r->alias,
            'label' => $label,
            'url'   => url($prefix.$r->alias),
        ];

        return view('tagtoa::qr.poster', $data);
    }
}
