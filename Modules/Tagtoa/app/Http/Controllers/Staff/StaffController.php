<?php

namespace Modules\Tagtoa\App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — l'équipe du commerce, vue du patron.
 *
 * Le tableau de bord EST celui du patron : il y enregistre les gens qui
 * tiennent ses caisses, leur donne un rôle et un code d'accès.
 *
 * Le code ne fait jamais le chemin du retour : on ne le réaffiche pas, on ne le
 * pré-remplit pas, et laisser le champ vide en modification le laisse inchangé.
 * Corriger le téléphone de quelqu'un ne doit pas l'obliger à réapprendre son
 * code.
 */
class StaffController extends Controller
{
    public function __construct(protected StaffService $staff)
    {
    }

    public function index(): View
    {
        $tenantId = Tenant::id();

        return view('tagtoa::staff.index', [
            'roster'    => $this->staff->roster($tenantId),
            'terminals' => Terminal::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'roles'     => StaffAccess::ROLES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $staff = $this->staff->save(Tenant::id(), $data + [
            'created_by' => optional(Tenant::user())->name,
        ]);

        app(AuditService::class)->log('staff.created', $staff, $staff->name.' — '.$staff->role_label);

        return back()->with('success', __(':nom peut maintenant ouvrir une caisse avec son code.', ['nom' => $staff->name]));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $staff = $this->own($id);
        $data  = $this->validated($request, $staff);

        $this->staff->save(Tenant::id(), $data, $staff);

        app(AuditService::class)->log('staff.updated', $staff, $staff->fresh()->name);

        return back()->with('success', __('Fiche mise à jour.'));
    }

    /**
     * Ferme l'accès sans rien perdre. C'est le geste normal quand quelqu'un
     * s'en va : la caisse se ferme immédiatement, mais ses ventes, son nom et
     * son historique restent lisibles dans les rapports.
     */
    public function toggle(int $id): RedirectResponse
    {
        $staff = $this->own($id);
        $staff->update(['is_active' => ! $staff->is_active]);

        app(AuditService::class)->log('staff.toggled', $staff,
            $staff->name.' — '.($staff->is_active ? 'actif' : 'inactif'));

        return back()->with('success', $staff->is_active
            ? __(':nom peut à nouveau ouvrir une caisse.', ['nom' => $staff->name])
            : __('Accès fermé pour :nom.', ['nom' => $staff->name]));
    }

    /**
     * Supprime la fiche. Les ventes déjà encaissées RESTENT : une recette
     * appartient au commerce, pas à la personne qui l'a encaissée. Elles
     * reviennent simplement au patron dans les rapports.
     */
    public function destroy(int $id): RedirectResponse
    {
        $staff = $this->own($id);
        $nom = $staff->name;
        $staff->delete();

        app(AuditService::class)->log('staff.deleted', null, $nom);

        return back()->with('success', __('Fiche de :nom supprimée. Ses ventes restent dans vos rapports.', ['nom' => $nom]));
    }

    /* ----------------------------------------------------------------- */

    /**
     * Règles communes. Le code n'est exigé qu'à la création : vide en
     * modification, il reste celui que l'employé connaît déjà.
     */
    protected function validated(Request $request, ?Staff $staff = null): array
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190',
                // Unicité LOCALE au commerce : deux commerces peuvent employer
                // la même personne.
                Rule::unique('tagtoa_staff', 'email')
                    ->where('tenant_id', Tenant::id())
                    ->ignore($staff?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role'  => ['required', Rule::in(array_keys(StaffAccess::ROLES))],
            'terminal_id' => ['nullable', 'integer',
                // La caisse doit être une des SIENNES.
                Rule::exists('tagtoa_pos_terminals', 'id')->where('tenant_id', Tenant::id())],
            'pin' => [
                $staff ? 'nullable' : 'required', 'string',
                // Le format est jugé par la MÊME logique que l'ouverture de
                // caisse : un seul endroit sait ce qu'est un code valide.
                function ($attribute, $value, $fail) {
                    if ((string) $value !== '' && ! StaffPinService::isValidPinFormat((string) $value)) {
                        $fail(__('Le code doit contenir 4 à 6 chiffres.'));
                    }
                },
            ],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'pin.required' => __('Choisissez un code à 4 chiffres pour cette personne.'),
        ]);

        return $data;
    }

    /** Un employé de CE commerce, ou 404. */
    protected function own(int $id): Staff
    {
        return Staff::where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
