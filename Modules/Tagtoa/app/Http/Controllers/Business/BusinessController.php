<?php

namespace Modules\Tagtoa\App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Business\BusinessService;
use Modules\Tagtoa\App\Support\Menu\BusinessProfile;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — déclarer son commerce, et passer de l'un à l'autre.
 *
 * Un compte peut tenir plusieurs commerces. Le premier déclaré reprend
 * l'identifiant historique du compte, donc un marchand déjà installé retrouve
 * ses données telles quelles (voir BusinessService::nextId).
 */
class BusinessController extends Controller
{
    public function __construct(protected BusinessService $businesses)
    {
    }

    /** Mes commerces : celui sur lequel je travaille, et les autres. */
    public function index(): View
    {
        return view('tagtoa::business.index', [
            'businesses' => $this->businesses->forAccount(Tenant::account()),
            'courant'    => Tenant::id(),
        ]);
    }

    /** Formulaire : premier commerce, ou un de plus. */
    public function create(): View
    {
        return view('tagtoa::business.form', [
            'business' => new Business(['type' => 'other', 'sells_products' => true, 'currency' => 'HTG']),
            'types'    => Menu::TYPES,
            'suggestions' => BusinessProfile::PROFILES,
            'devises'  => Business::SUGGESTED_CURRENCIES,
            'premier'  => ! $this->businesses->hasAny(Tenant::account()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['logo_path'] = $this->logo($request);

        $business = $this->businesses->create(Tenant::account(), $data);

        app(AuditService::class)->log('business.created', $business, $business->name);

        return redirect()->route('tagtoa.hub')
            ->with('success', __(':nom est enregistré. Tout est prêt.', ['nom' => $business->name]));
    }

    public function edit(string $id): View
    {
        $business = $this->own($id);

        return view('tagtoa::business.form', [
            'business' => $business,
            'types'    => Menu::TYPES,
            'suggestions' => BusinessProfile::PROFILES,
            'devises'  => Business::SUGGESTED_CURRENCIES,
            'premier'  => false,
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $business = $this->own($id);
        $data = $this->validated($request, $business);

        // Un logo n'est remplacé que si un nouveau est envoyé : enregistrer une
        // correction d'adresse ne doit pas effacer l'enseigne.
        if ($logo = $this->logo($request)) {
            $data['logo_path'] = $logo;
        }

        $this->businesses->update($business, $data);

        app(AuditService::class)->log('business.updated', $business, $business->name);

        return back()->with('success', __('Commerce mis à jour.'));
    }

    /** Bascule vers un autre de MES commerces. */
    public function switch(string $id): RedirectResponse
    {
        if (! Tenant::switchTo($id)) {
            return back()->with('error', __('Ce commerce n\'est pas accessible.'));
        }

        return redirect()->route('tagtoa.hub')
            ->with('success', __('Vous travaillez maintenant sur :nom.', [
                'nom' => Business::whereKey($id)->value('name'),
            ]));
    }

    /* ----------------------------------------------------------------- */

    protected function validated(Request $request, ?Business $business = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in(array_keys(Menu::TYPES))],
            'categories' => ['nullable', 'array', 'max:30'],
            'categories.*' => ['nullable', 'string', 'max:60'],
            'sells_products' => ['nullable', 'boolean'],
            'sells_services' => ['nullable', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:40'],
            // Le marchand peut saisir une devise que nous ne listons pas.
            'currency' => ['required', 'string', 'min:3', 'max:10', 'alpha'],
            'logo'    => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
    }

    protected function logo(Request $request): ?string
    {
        return $request->hasFile('logo')
            ? $request->file('logo')->store('tagtoa/business-logos', 'public')
            : null;
    }

    /** Un commerce de MON compte, ou 404. */
    protected function own(string $id): Business
    {
        return Business::where('account_id', Tenant::account())->findOrFail($id);
    }
}
