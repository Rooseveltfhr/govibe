<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Links\LinkPage;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — onboarding marchand (« Commencer ») : créer sa première page
 * en 30 secondes. Trois démarrages rapides (menu, pay, links) créés avec
 * des défauts sûrs, puis redirection vers le VRAI formulaire d'édition du
 * module — aucune logique métier dupliquée. Les modules à formulaire riche
 * (site, event, booking) pointent vers leur page de création existante.
 */
class OnboardingController extends Controller
{
    use EnforcesPlan;

    /** Démarrages rapides supportés (création directe avec défauts). */
    public const QUICK_KINDS = ['menu', 'pay', 'links'];

    public function index(): View
    {
        return view('tagtoa::hub.start');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kind'     => ['required', Rule::in(self::QUICK_KINDS)],
            'name'     => ['required', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
        ]);

        if ($r = $this->planGuard($data['kind'])) {
            return $r;
        }

        $tenantId = Tenant::id();
        $name = $data['name'];
        $whatsapp = $data['whatsapp'] ?? null;

        switch ($data['kind']) {
            case 'menu':
                $menu = Menu::create([
                    'tenant_id' => $tenantId,
                    'name'      => $name,
                    'alias'     => Menu::generateAlias($name),
                    'whatsapp'  => $whatsapp,
                ]);

                return redirect()->route('tagtoa.menu.dashboard.edit', $menu->id)
                    ->with('success', __('Menu créé. Ajoutez vos catégories et produits.'));

            case 'pay':
                $page = PaymentPage::create([
                    'tenant_id' => $tenantId,
                    'title'     => $name,
                    'alias'     => PaymentPage::generateAlias($name),
                ]);

                return redirect()->route('tagtoa.pay.dashboard.edit', $page->id)
                    ->with('success', __('Page de paiement créée. Ajoutez vos méthodes.'));

            case 'links':
            default:
                $page = LinkPage::create([
                    'tenant_id' => $tenantId,
                    'title'     => $name,
                    'alias'     => LinkPage::generateAlias($name),
                ]);

                return redirect()->route('tagtoa.links.dashboard.edit', $page->id)
                    ->with('success', __('Page de liens créée. Ajoutez vos liens.'));
        }
    }
}
