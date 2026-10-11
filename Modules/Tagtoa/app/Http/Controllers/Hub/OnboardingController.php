<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
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

    /**
     * Démarrages rapides supportés (création directe avec défauts).
     * Filtrés sur les modules mis en avant : proposer un démarrage vers un
     * module absent du menu créerait une ressource que le marchand ne
     * retrouverait plus ensuite.
     */
    public const QUICK_KINDS = ['menu', 'pay', 'links'];

    public static function quickKinds(): array
    {
        return array_values(array_filter(
            self::QUICK_KINDS,
            fn ($k) => \Modules\Tagtoa\App\Support\DashboardModules::isEnabled($k)
        ));
    }

    public function index(): View
    {
        return view('tagtoa::hub.start', [
            'business' => Business::find(Tenant::id()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = Tenant::id();

        // Un commerce n'a qu'UN menu (même règle que
        // DashboardController::menuExistant()) : ce démarrage rapide n'avait
        // pas cette garde et pouvait en empiler un second. AVANT le
        // planGuard, pour la même raison que DashboardController::store() —
        // un commerce qui a déjà son menu ne doit pas recevoir un message
        // « passez à un forfait supérieur » absurde.
        if ($request->input('kind') === 'menu' && ($existant = Menu::where('tenant_id', $tenantId)->oldest()->first())) {
            return redirect()->route('tagtoa.menu.dashboard.edit', $existant->id)
                ->with('success', __('Vous avez déjà un menu pour ce commerce — le voici.'));
        }

        // Type requis pour un menu (voir la règle plus bas) — mais un
        // commerce qui a déjà déclaré le sien ne doit pas le retaper : même
        // reprise que DashboardController::store() pour le même champ.
        if ($request->input('kind') === 'menu' && ! $request->filled('type')) {
            $request->merge(['type' => Business::find($tenantId)?->type]);
        }

        $data = $request->validate([
            'kind'     => ['required', Rule::in(self::quickKinds())],
            'name'     => ['required', 'string', 'max:120'],
            'whatsapp' => ['nullable', 'string', 'max:40'],
            // Signalé : un menu créé ici sans type perdait tout l'habillage
            // par métier (catégories, champs, exemples) — silencieusement
            // repris comme « Restaurant » au premier « Modifier ». Obligatoire
            // uniquement pour « menu » : les autres démarrages n'ont pas de
            // notion de métier.
            'type'     => ['required_if:kind,menu', 'nullable', Rule::in(array_keys(Menu::TYPES))],
        ]);

        if ($r = $this->planGuard($data['kind'])) {
            return $r;
        }

        $name = $data['name'];
        $whatsapp = $data['whatsapp'] ?? null;

        switch ($data['kind']) {
            case 'menu':
                $menu = Menu::create([
                    'tenant_id' => $tenantId,
                    'name'      => $name,
                    'alias'     => Menu::generateAlias($name),
                    'whatsapp'  => $whatsapp,
                    'type'      => $data['type'],
                ]);

                return redirect()->route('tagtoa.menu.dashboard.edit', $menu->id)
                    ->with('success', __('Menu créé. Ajoutez vos catégories et produits.'));

            case 'pay':
                $page = PaymentPage::create([
                    'tenant_id' => $tenantId,
                    'title'     => $name,
                    'alias'     => PaymentPage::generateAlias($name),
                ]);

                // Les moyens de paiement se configurent une seule fois, ailleurs :
                // on envoie donc directement sur l'écran de partage du lien.
                return redirect()->route('tagtoa.pay.dashboard.share', $page->id)
                    ->with('success', __('Lien de paiement créé. Partagez-le à vos clients.'));

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
