<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Modules\Tagtoa\App\Actions\Event\Wallet\EncodeParticipantCard;
use Modules\Tagtoa\App\Models\Event\Checkin;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\Staff;
use Modules\Tagtoa\App\Models\Event\SyncConflict;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Services\Event\SyncReconciler;

/**
 * TAGTOA EVENT — terminal STAFF (terrain, auth par PIN, offline-first).
 *
 * Public (pas de login Laravel) mais protégé par session staff scopée
 * événement + rate-limit sur le PIN. Le rôle décide des écrans :
 * vente = activation de cartes/billets sur place ; checkin = portes.
 * Aucune action financière ni de configuration n'est accessible ici.
 */
class StaffTerminalController extends Controller
{
    /* ---------------- Écran principal (login OU panneau selon session) ---------------- */

    public function terminal(string $alias): View
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->currentStaff($event);

        // Pour l'écran de login : noms actifs seulement (jamais les hashes).
        $roster = Staff::where('event_id', $event->id)->where('active', true)
            ->orderBy('name')->get(['id', 'name', 'role']);

        $ticketTypes = $event->ticketTypes()->where('is_active', true)->get();

        $pendingConflicts = $staff && $staff->role === 'admin'
            ? SyncConflict::where('event_id', $event->id)->where('resolved', false)->count()
            : 0;

        return view('tagtoa::event.staff-terminal', compact('event', 'staff', 'roster', 'ticketTypes', 'pendingConflicts'));
    }

    /* ---------------- Session PIN ---------------- */

    public function login(Request $request, string $alias): RedirectResponse
    {
        $event = $this->eventByAlias($alias);
        $data = $request->validate([
            'staff_id' => ['required', 'integer'],
            'pin'      => ['required', 'string', 'max:12'],
        ]);

        // Rate-limit : 8 essais / minute par événement + IP (anti brute-force PIN).
        $key = 'evstaff:'.$event->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            return back()->with('error', __('Trop d\'essais. Réessayez dans une minute.'));
        }
        RateLimiter::hit($key, 60);

        $staff = Staff::where('event_id', $event->id)->where('active', true)->find($data['staff_id']);

        if (! $staff || ! StaffPinService::verifyPin($data['pin'], $staff->pin_hash)) {
            return back()->with('error', __('PIN incorrect.'));
        }

        RateLimiter::clear($key);
        $staff->update(['last_login_at' => now()]);

        // Session scopée par événement : un appareil peut servir plusieurs événements.
        session()->put($this->sessionKey($event), ['id' => $staff->id, 'name' => $staff->name, 'role' => $staff->role]);

        return redirect()->route('tagtoa.event.staff.terminal', $event->alias);
    }

    public function logout(string $alias): RedirectResponse
    {
        $event = $this->eventByAlias($alias);
        session()->forget($this->sessionKey($event));

        return redirect()->route('tagtoa.event.staff.terminal', $event->alias);
    }

    /* ---------------- Check-in (rôle checkin|admin) ---------------- */

    public function checkin(Request $request, string $alias, CheckinService $svc): JsonResponse
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->requireRole($event, 'checkin');
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $data = $request->validate([
            'code'        => ['nullable', 'string', 'max:64'],
            'uid'         => ['nullable', 'string', 'max:120'],
            'client_uuid' => ['nullable', 'string', 'max:64'],
        ]);

        [$code, $method] = $this->resolveCode($event, $data, $svc);
        if (! $code) {
            return response()->json(['valid' => false, 'color' => 'red', 'message' => __('Billet introuvable.')], 200);
        }

        $res = $svc->processScan($event, $code, 'in', $method, 'staff-terminal', $data['client_uuid'] ?? null, $staff->id);

        return response()->json([
            'valid'   => $res['valid'],
            'color'   => $res['color'],
            'message' => $res['message'],
            'name'    => optional($res['ticket'] ?? null)->holder_name,
        ]);
    }

    /* ---------------- Sync offline par lot (IndexedDB → serveur) ---------------- */

    public function sync(Request $request, string $alias, CheckinService $svc): JsonResponse
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->requireRole($event, 'checkin');
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $records = (array) $request->input('records', []);
        $records = array_slice($records, 0, 200); // borne de sécurité par lot

        // Dédup idempotente : uuids déjà connus du serveur + intra-lot (logique pure).
        $uuids = array_values(array_filter(array_map(fn ($r) => $r['client_uuid'] ?? null, $records)));
        $seen = Checkin::where('event_id', $event->id)->whereIn('client_uuid', $uuids)->pluck('client_uuid')->all();
        $parts = SyncReconciler::partition($records, $seen);

        $ok = 0;
        $conflicts = 0;
        foreach ($parts['fresh'] as $r) {
            [$code, $method] = $this->resolveCode($event, (array) $r, $svc);
            if (! $code) {
                continue;
            }

            $res = $svc->processScan($event, $code, 'in', $method, 'staff-terminal', $r['client_uuid'] ?? null, $staff->id);

            if ($res['valid']) {
                $ok++;

                continue;
            }

            // Double entrée offline (2 appareils) : premier gagne, second journalisé.
            $ticket = $res['ticket'] ?? null;
            if ($ticket && SyncReconciler::isDuplicateEntry((bool) $ticket->checked_in, 'in')) {
                SyncConflict::firstOrCreate(
                    ['event_id' => $event->id, 'client_uuid' => $r['client_uuid'] ?? null, 'kind' => 'duplicate_checkin'],
                    ['ticket_id' => $ticket->id, 'staff_id' => $staff->id, 'payload' => $r]
                );
                $conflicts++;
            }
        }

        return response()->json([
            'ok'         => true,
            'synced'     => $ok,
            'duplicates' => count($parts['duplicates']),
            'conflicts'  => $conflicts,
        ]);
    }

    /* ---------------- Vente / activation de carte (rôle vente|admin) ---------------- */

    public function sell(Request $request, string $alias): JsonResponse
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->requireRole($event, 'sales');
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'phone'          => ['required', 'string', 'max:40'], // WhatsApp obligatoire (spec)
            'email'          => ['nullable', 'email', 'max:160'],
            'uid'            => ['nullable', 'string', 'max:120'],
            'ticket_type_id' => ['nullable', 'integer'],
        ]);

        if (! empty($data['ticket_type_id'])) {
            $okType = $event->ticketTypes()->whereKey($data['ticket_type_id'])->exists();
            if (! $okType) {
                $data['ticket_type_id'] = null;
            }
        }

        try {
            if (! empty($data['uid'])) {
                // Carte NFC physique : billet + tag liés (entrée par tap).
                $res = app(EncodeParticipantCard::class)->handle($event, $data);
                $ticket = $res['ticket'];
            } else {
                // Mode QR : e-billet simple, imprimable/partageable (badges).
                $ticket = Ticket::create([
                    'event_id'       => $event->id,
                    'ticket_type_id' => $data['ticket_type_id'] ?? null,
                    'code'           => Ticket::generateCode(),
                    'holder_name'    => $data['name'],
                    'holder_phone'   => $data['phone'],
                    'status'         => Ticket::STATUS_VALID,
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => __('Réessayez.')], 422);
        }

        return response()->json(['ok' => true, 'code' => $ticket->code, 'name' => $ticket->holder_name]);
    }

    /* ---------------- Helpers ---------------- */

    protected function eventByAlias(string $alias): Event
    {
        return Event::where('alias', $alias)->firstOrFail();
    }

    protected function sessionKey(Event $event): string
    {
        return 'tagtoa_ev_staff.'.$event->id;
    }

    protected function currentStaff(Event $event): ?Staff
    {
        $s = session($this->sessionKey($event));
        if (! is_array($s) || empty($s['id'])) {
            return null;
        }

        // Revérifié en base à chaque requête : un staff désactivé perd l'accès immédiatement.
        return Staff::where('event_id', $event->id)->where('active', true)->find($s['id']);
    }

    /** Exige une session staff avec accès à l'écran donné, sinon 401 JSON. */
    protected function requireRole(Event $event, string $screen): Staff|JsonResponse
    {
        $staff = $this->currentStaff($event);
        if (! $staff || ! StaffPinService::canAccess($staff->role, $screen)) {
            return response()->json(['ok' => false, 'valid' => false, 'message' => __('Session expirée — reconnectez-vous.')], 401);
        }

        return $staff;
    }

    /** Résout code direct ou UID NFC → code billet. Retourne [code|null, method]. */
    protected function resolveCode(Event $event, array $data, CheckinService $svc): array
    {
        $code = trim((string) ($data['code'] ?? ''));
        if ($code !== '') {
            return [$code, 'qr'];
        }

        $uid = trim((string) ($data['uid'] ?? ''));
        if ($uid !== '') {
            return [$svc->resolveNfcCode($event, $uid), 'nfc'];
        }

        return [null, 'qr'];
    }
}
