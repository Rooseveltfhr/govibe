<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\SyncConflict;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Billing\PlanService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Support\Tenant;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * TAGTOA EVENT — gestion du staff terrain (côté organisateur, vrai login).
 *
 * Seul le propriétaire (login tenant) crée/désactive les comptes staff.
 * Le PIN ne donne accès qu'au terminal terrain (vente/check-in), jamais ici.
 */
class StaffController extends Controller
{
    /** Liste staff + conflits de sync + activité. */
    public function index(int $id): View
    {
        $event = $this->own($id);
        $staff = Staff::where('event_id', $event->id)->orderByDesc('active')->orderBy('name')->get();

        // Activité : nombre de check-ins réalisés par chaque staff.
        $activity = Checkin::where('event_id', $event->id)
            ->whereNotNull('staff_id')
            ->selectRaw('staff_id, count(*) as n')->groupBy('staff_id')->pluck('n', 'staff_id');

        $conflicts = SyncConflict::where('event_id', $event->id)
            ->with(['ticket', 'staff'])->orderByDesc('id')->limit(50)->get();

        $limit = app(PlanService::class)->limit(Tenant::id(), 'staff');
        $canAdd = $limit === null || $staff->count() < (int) $limit;

        return view('tagtoa::event.staff', compact('event', 'staff', 'activity', 'conflicts', 'limit', 'canAdd'));
    }

    /** Crée un compte staff (PIN haché, jamais stocké en clair). */
    public function store(Request $request, int $id): RedirectResponse
    {
        $event = $this->own($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in(StaffPinService::ROLES)],
            'pin'  => ['required', 'string', 'max:12'],
        ]);

        if (! StaffPinService::isValidPinFormat($data['pin'])) {
            return back()->with('error', __('Le PIN doit contenir 4 à 6 chiffres.'));
        }

        // Plan gating : nombre de staff PAR ÉVÉNEMENT selon le forfait.
        $limit = app(PlanService::class)->limit(Tenant::id(), 'staff');
        if ($limit !== null && Staff::where('event_id', $event->id)->count() >= (int) $limit) {
            return back()->with('error', __('Limite de staff atteinte pour votre forfait.'));
        }

        $staff = Staff::create([
            'event_id'   => $event->id,
            'name'       => $data['name'],
            'role'       => $data['role'],
            'pin_hash'   => StaffPinService::hashPin($data['pin']),
            'active'     => true,
            'created_by' => optional(auth()->user())->id,
        ]);

        app(AuditService::class)->log('event_staff_created', $staff, $staff->name.' ('.$staff->role.')');

        return back()->with('success', __('Compte staff créé.'));
    }

    /** Active/désactive un staff (jamais de suppression : traçabilité). */
    public function toggle(int $id, int $staffId): RedirectResponse
    {
        $event = $this->own($id);
        $staff = Staff::where('event_id', $event->id)->findOrFail($staffId);
        $staff->update(['active' => ! $staff->active]);

        app(AuditService::class)->log('event_staff_toggled', $staff, $staff->name.' → '.($staff->active ? 'actif' : 'inactif'));

        return back()->with('success', $staff->active ? __('Staff activé.') : __('Staff désactivé.'));
    }

    /** Réinitialise le PIN d'un staff. */
    public function resetPin(Request $request, int $id, int $staffId): RedirectResponse
    {
        $event = $this->own($id);
        $staff = Staff::where('event_id', $event->id)->findOrFail($staffId);

        $data = $request->validate(['pin' => ['required', 'string', 'max:12']]);
        if (! StaffPinService::isValidPinFormat($data['pin'])) {
            return back()->with('error', __('Le PIN doit contenir 4 à 6 chiffres.'));
        }

        $staff->update(['pin_hash' => StaffPinService::hashPin($data['pin'])]);
        app(AuditService::class)->log('event_staff_pin_reset', $staff, $staff->name);

        return back()->with('success', __('PIN réinitialisé.'));
    }

    /** Marque un conflit de sync comme résolu. */
    public function resolveConflict(int $id, int $conflictId): RedirectResponse
    {
        $event = $this->own($id);
        SyncConflict::where('event_id', $event->id)->findOrFail($conflictId)->update(['resolved' => true]);

        return back()->with('success', __('Conflit marqué comme résolu.'));
    }

    /** Export CSV de l'activité check-in par staff (règlement post-événement). */
    public function export(int $id): StreamedResponse
    {
        $event = $this->own($id);
        $rows = Checkin::where('event_id', $event->id)
            ->with(['ticket', 'staff'])->orderBy('id')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'heure', 'billet', 'participant', 'direction', 'methode', 'porte', 'staff']);
            foreach ($rows as $c) {
                fputcsv($out, [
                    optional($c->scanned_at)->format('Y-m-d'),
                    optional($c->scanned_at)->format('H:i:s'),
                    optional($c->ticket)->code,
                    optional($c->ticket)->holder_name,
                    $c->direction,
                    $c->method,
                    $c->gate,
                    optional($c->staff)->name,
                ]);
            }
            fclose($out);
        }, 'checkins-'.$event->alias.'.csv', ['Content-Type' => 'text/csv']);
    }

    protected function own(int $id): Event
    {
        return Event::where('tenant_id', Tenant::id())->findOrFail($id);
    }
}
