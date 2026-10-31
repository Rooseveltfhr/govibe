<?php

namespace Modules\Tagtoa\App\Http\Controllers\Event;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;

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
    /** Scans traités par requête de reprise. */
    private const SYNC_BATCH_MAX = 200;

    /* ---------------- Écran principal (login OU panneau selon session) ---------------- */

    public function terminal(string $alias): View
    {
        // Le terminal staff terrain est en FRANÇAIS uniquement (personnel sur place),
        // même si le reste de la plateforme est multilingue.
        app()->setLocale('fr');

        $event = $this->eventByAlias($alias);
        $staff = $this->currentStaff($event);

        // Pour l'écran de login : noms actifs seulement (jamais les hashes).
        $roster = Staff::where('event_id', $event->id)->where('active', true)
            ->orderBy('name')->get(['id', 'name', 'role']);

        $ticketTypes = $event->ticketTypes()->where('is_active', true)->get();

        // Moyens de paiement du MARCHAND (configurés une fois dans TAGTOA Pay) :
        // plus besoin de lier une page de paiement à l'événement pour les voir.
        $payMethods = app(MerchantMethods::class)->active($event->tenant_id);

        $pendingConflicts = $staff && $staff->role === 'admin'
            ? SyncConflict::where('event_id', $event->id)->where('resolved', false)->count()
            : 0;

        return view('tagtoa::event.staff-terminal', compact('event', 'staff', 'roster', 'ticketTypes', 'payMethods', 'pendingConflicts'));
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
            // `ticket` est un TABLEAU (voir CheckinService::r()) : optional()->prop
            // sur un tableau rendait toujours null et l'écran n'affichait jamais le nom.
            'name'    => $res['ticket']['holder'] ?? null,
        ]);
    }

    /* ---------------- Sync offline par lot (IndexedDB → serveur) ---------------- */

    /**
     * Reprise des scans faits HORS LIGNE (IndexedDB → serveur).
     *
     * Le contrat avec le terminal : une entrée de `results` par scan reçu, et il
     * ne retire de sa file QUE ceux marqués `ack:true` — c'est-à-dire ceux dont
     * le serveur a gardé une trace durable :
     *   - admitted         : entrée enregistrée (ligne de check-in) ;
     *   - duplicate_replay : ce `client_uuid` était déjà connu (rejeu) ;
     *   - refused          : scan lu et refusé (billet annulé, porte, double
     *                        entrée…) — consigné dans les conflits à vérifier ;
     *   - unresolved       : code ou carte NFC non reconnu — consigné aussi, il
     *                        ne se perd donc plus sans trace.
     * Seul `error` (échec inattendu) reste `ack:false` : le scan reste sur
     * l'appareil et sera rejoué. Aucun texte d'exception ne sort : il est
     * journalisé sous un numéro de suivi (`ref`).
     *
     * Avant, cette méthode lisait `->checked_in` sur un TABLEAU : l'avertissement
     * PHP devenait une exception, donc un HTTP 500 pour tout le lot dès qu'un
     * scan était un doublon — et le terminal rejouait ce même lot toutes les
     * 30 s. Et l'heure du scan envoyée par l'appareil était ignorée.
     */
    public function sync(Request $request, string $alias, CheckinService $svc): JsonResponse
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->requireRole($event, 'checkin');
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $all = (array) $request->input('records', []);
        $overCap = count($all) > self::SYNC_BATCH_MAX;
        $records = array_slice($all, 0, self::SYNC_BATCH_MAX); // borne de sécurité par lot

        // Dédup idempotente : uuids déjà connus du serveur + intra-lot (logique pure).
        $uuids = array_values(array_filter(array_map(fn ($r) => is_array($r) ? ($r['client_uuid'] ?? null) : null, $records), 'is_string'));
        $seen = Checkin::where('event_id', $event->id)->whereIn('client_uuid', $uuids)->pluck('client_uuid')->all();
        $parts = SyncReconciler::partition($records, $seen);

        $results = [];
        $ok = 0;
        $conflicts = 0;

        $fresh = $parts['fresh'];
        $dupCount = count($parts['duplicates']);
        // Les doublons (déjà connus) sont acquittés d'emblée : rien à refaire.
        foreach ($parts['duplicates'] as $d) {
            $u = is_array($d) ? ($d['client_uuid'] ?? null) : null;
            $results[] = ['client_uuid' => $u, 'status' => 'duplicate_replay', 'ack' => true];
        }

        foreach ($fresh as $r) {
            $r = $this->cleanRecord($r);
            $uuid = $r['client_uuid'];
            if ($uuid === null) {
                // Sans clé, le terminal ne saurait jamais que le scan est passé.
                $results[] = ['client_uuid' => null, 'status' => 'invalid', 'ack' => false];

                continue;
            }

            try {
                [$scannedAt, $flag] = $svc->resolveScanTime($r['at'], $event);
                [$code, $method] = $this->resolveCode($event, $r, $svc);

                if (! $code) {
                    $this->conflict($event, $staff, 'unresolved_scan', $uuid, null, $r, ['reason' => 'unresolved']);
                    $conflicts++;
                    $results[] = ['client_uuid' => $uuid, 'status' => 'unresolved', 'ack' => true];

                    continue;
                }

                $res = $svc->processScan($event, $code, 'in', $method, 'staff-terminal', $uuid, $staff->id, $scannedAt);

                if ($flag !== null) {
                    $this->conflict($event, $staff, 'time_flagged', $uuid, $res['ticket_id'] ?? null, $r, $flag);
                    $conflicts++;
                }

                if ($res['valid']) {
                    $ok++;
                    $results[] = ['client_uuid' => $uuid, 'status' => 'admitted', 'ack' => true];

                    continue;
                }

                // Double entrée offline (2 appareils) : premier gagne, second journalisé.
                $kind = ($res['reason'] ?? null) === 'duplicate_today' ? 'duplicate_checkin' : 'refused_scan';
                $this->conflict($event, $staff, $kind, $uuid, $res['ticket_id'] ?? null, $r, ['reason' => $res['reason'] ?? 'refused', 'message' => $res['message']]);
                $conflicts++;
                $results[] = ['client_uuid' => $uuid, 'status' => 'refused', 'ack' => true, 'reason' => $res['reason'] ?? null, 'message' => $res['message']];
            } catch (\Throwable $e) {
                // JAMAIS getMessage() : une QueryException contient le SQL.
                $ref = Str::upper(Str::random(8));
                report($e);
                Log::error('event.staff.sync.error', ['ref' => $ref, 'event' => $event->id, 'client_uuid' => $uuid, 'exception' => $e::class]);
                $results[] = ['client_uuid' => $uuid, 'status' => 'error', 'ack' => false, 'ref' => $ref];
            }
        }

        // `ok` reste vrai seulement si TOUT a été acquitté et que le lot n'a pas
        // été tronqué : un terminal déjà installé, qui vide sa file sur `ok`, ne
        // doit rien perdre quand le serveur n'a pas tout pris en charge.
        $allAcked = ! in_array(false, array_column($results, 'ack'), true);

        return response()->json([
            'ok'         => $allAcked && ! $overCap,
            'synced'     => $ok,
            'duplicates' => $dupCount,
            'conflicts'  => $conflicts,
            'results'    => $results,
        ]);
    }

    /**
     * Un enregistrement venu du terminal, réduit à ce que la reprise lit — et
     * BORNÉ : le client décide de la taille de ses chaînes, et chaque scan
     * refusé ou non reconnu écrit maintenant une ligne de conflit. Valeurs non
     * scalaires jetées, chaînes tronquées aux limites de checkin().
     *
     * @return array{client_uuid:?string, code:?string, uid:?string, at:?string}
     */
    protected function cleanRecord(mixed $r): array
    {
        $r = is_array($r) ? $r : [];
        $str = fn ($v, int $max) => is_scalar($v) && (string) $v !== '' ? mb_substr((string) $v, 0, $max) : null;
        $uuid = $str($r['client_uuid'] ?? null, 65);

        return [
            // Plus de 64 caractères : pas une clé que ce terminal a produite.
            'client_uuid' => $uuid !== null && mb_strlen($uuid) <= 64 ? $uuid : null,
            'code'        => $str($r['code'] ?? null, 64),
            'uid'         => $str($r['uid'] ?? null, 120),
            'at'          => $str($r['at'] ?? null, 40),
        ];
    }

    /** Consigne un conflit à vérifier — idempotent par (événement, client_uuid, type). */
    protected function conflict(Event $event, Staff $staff, string $kind, string $uuid, ?int $ticketId, array $record, array $detail): void
    {
        SyncConflict::firstOrCreate(
            ['event_id' => $event->id, 'client_uuid' => $uuid, 'kind' => $kind],
            ['ticket_id' => $ticketId, 'staff_id' => $staff->id, 'payload' => ['record' => array_intersect_key($record, array_flip(['code', 'uid', 'at', 'client_uuid'])), 'detail' => $detail]]
        );
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
            'printed_code'   => ['nullable', 'string', 'max:64'], // billet PRÉ-IMPRIMÉ (hors système), scanné/saisi
            'ticket_type_id' => ['nullable', 'integer'],
            'credit'         => ['nullable', 'numeric', 'min:0', 'max:1000000'], // crédit initial du wallet
            'payment_method' => ['nullable', 'string', 'max:60'],               // moyen de paiement (TAGTOA Pay)
            'pay_card_uid'   => ['nullable', 'string', 'max:120'],              // Carte TAGTOA : UID (tap)
            'pay_card_code'  => ['nullable', 'string', 'max:40'],              // Carte TAGTOA : code imprimé
            'pay_card_pin'   => ['nullable', 'string', 'max:6'],
            'client_uuid'    => ['nullable', 'string', 'max:80'],
        ]);

        if (! empty($data['ticket_type_id'])) {
            $okType = $event->ticketTypes()->whereKey($data['ticket_type_id'])->exists();
            if (! $okType) {
                $data['ticket_type_id'] = null;
            }
        }

        // Le moyen de paiement doit provenir de TAGTOA Pay (page liée), « Espèces »
        // ou « Carte TAGTOA » (closed-loop).
        $method = trim((string) ($data['payment_method'] ?? ''));
        if ($method !== '' && $method !== 'Espèces' && $method !== 'Carte TAGTOA') {
            $allowed = app(MerchantMethods::class)->active($event->tenant_id)
                ->map->display_label->all();
            if (! in_array($method, $allowed, true)) {
                $method = ''; // valeur inconnue ignorée (anti-spoof)
            }
        }

        // Billet PRÉ-IMPRIMÉ (hors système, ex. stock physique d'un festival) : on
        // ACTIVE le billet déjà enregistré, jamais on n'en crée un nouveau — le
        // code scanné doit exister et ne pas avoir déjà été vendu (anti-fraude).
        // Vérifié AVANT tout débit : refuser la vente APRÈS avoir débité la carte
        // laissait le client payé sans billet.
        $printedCode = trim((string) ($data['printed_code'] ?? ''));
        $printed = null;
        if ($printedCode !== '') {
            $printed = Ticket::where('event_id', $event->id)
                ->where(function ($q) use ($printedCode) {
                    $q->where('code', $printedCode)->orWhere('serial', $printedCode);
                })->first();

            if (! $printed) {
                return response()->json(['ok' => false, 'message' => __('Billet introuvable — non enregistré dans le système.')], 422);
            }
            if (! $printed->isUnsold()) {
                return response()->json(['ok' => false, 'message' => __('Ce billet a déjà été vendu ou n\'est plus disponible.')], 422);
            }
        }

        // Prix du billet : imposé côté serveur depuis le type EFFECTIF — celui choisi, sinon
        // celui du billet pré-imprimé. Sans cela, un type absent ou d'un autre événement
        // donnait un prix de 0 : le billet pré-imprimé sortait sans débit ni commission.
        $effectiveTypeId = $data['ticket_type_id'] ?? $printed?->ticket_type_id;
        $ticketPrice = 0.0;
        if (! empty($effectiveTypeId)) {
            $tt = $event->ticketTypes()->whereKey($effectiveTypeId)->first();
            $ticketPrice = $tt ? (float) $tt->price : 0.0;
        }

        // Paiement par CARTE TAGTOA : on débite AVANT d'émettre le billet — un
        // débit refusé annule la vente (pas de billet fantôme). Si l'émission
        // échoue ENSUITE, le débit est annulé (voir refundSale).
        $charged = null;
        if ($method === 'Carte TAGTOA' && $ticketPrice > 0) {
            $svc = app(\Modules\Tagtoa\App\Services\Card\CardWalletService::class);
            $card = ! empty($data['pay_card_uid'])
                ? $svc->resolveByUid($data['pay_card_uid'])
                : $svc->resolveByCode((string) ($data['pay_card_code'] ?? ''));
            if (! $card) {
                return response()->json(['ok' => false, 'message' => __('Carte TAGTOA introuvable.')], 422);
            }
            // Référence liée à (événement, carte, type, billet pré-imprimé, client_uuid) et
            // bornée (colonne de 40 caractères). Liée : rejouer un client_uuid avec un autre
            // billet, une autre carte ou un autre événement ne retombe PAS sur « déjà appliqué »
            // (ce qui émettait un billet sans débit).
            $key = ! empty($data['client_uuid'])
                ? substr(sha1(implode('|', [$event->id, $card->id, $effectiveTypeId, $printedCode, $data['client_uuid']])), 0, 24)
                : null;
            $ref = $key ? 'evsale-'.$key : \Modules\Tagtoa\App\Support\Card\CardWallet::reference();

            $existing = $key ? \Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', $ref)->first() : null;
            if ($existing) {
                // REJEU d'une vente déjà débitée. Jamais un second billet pour un seul paiement.
                if (\Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', 'evrefund-'.$key)->exists()) {
                    return response()->json(['ok' => false, 'message' => __('Cette vente a été annulée et la carte remboursée. Recommencez la vente.')], 422);
                }
                $issued = ! empty($existing->meta['ticket_id'])
                    ? Ticket::where('event_id', $event->id)->find($existing->meta['ticket_id']) : null;
                if ($issued) {
                    // Déjà vendue : on répond comme la première fois, sans rien refaire.
                    return response()->json(['ok' => true, 'code' => $issued->code, 'name' => $issued->holder_name]);
                }
                // Débitée mais jamais émise (coupure entre les deux) : on émet, SANS re-débiter.
                $charged = ['card' => $card, 'amount' => $ticketPrice, 'ref' => (string) $existing->reference, 'key' => $key];
            } else {
                // Verrou anti-devinette du PIN par carte (le débit n'avait aucune limite par carte).
                $pinKey = 'ev-card-pin:'.$card->id;
                if (RateLimiter::tooManyAttempts($pinKey, 5)) {
                    return response()->json(['ok' => false, 'message' => __('Trop d\'essais de PIN sur cette carte. Réessayez dans quelques minutes.')], 429);
                }
                $res = $svc->charge($card, $ticketPrice, $data['pay_card_pin'] ?? null, [
                    'tenant_id'       => $event->tenant_id,
                    'context_type'    => 'event',
                    'context_id'      => $event->id,
                    'module'          => 'event',
                    'reference'       => $ref,
                    'skip_commission' => true, // la commission est prise sur le billet (event_ticket)
                ]);
                if (! $res['ok']) {
                    if (($res['code'] ?? '') === 'pin') {
                        RateLimiter::hit($pinKey, 900);
                    }

                    return response()->json(['ok' => false, 'message' => $res['message']], 422);
                }
                $charged = ['card' => $card, 'amount' => $ticketPrice, 'ref' => (string) ($res['txn']?->reference ?? $ref), 'key' => $key];
            }
        }

        // Émission du billet : UNE transaction. Le quota (vendu +1) se prend sous le
        // verrou du type, la réclamation d'un billet pré-imprimé est atomique
        // (UPDATE … WHERE statut = invendu, lignes touchées vérifiées) : deux
        // vendeurs qui scannent le même billet ne peuvent plus le vendre tous deux.
        try {
            $ticket = \Illuminate\Support\Facades\DB::transaction(function () use ($event, $staff, $data, $method, $printed, $charged) {
                $typeId = $data['ticket_type_id'] ?? $printed?->ticket_type_id;
                if (! empty($typeId)) {
                    $type = \Modules\Tagtoa\App\Models\Event\TicketType::where('event_id', $event->id)->lockForUpdate()->find($typeId);
                    if ($type) {
                        if ($type->remaining !== null && $type->remaining < 1) {
                            throw new \Modules\Tagtoa\App\Exceptions\TicketRuleException(__('Plus de place disponible pour « :n ».', ['n' => $type->name]));
                        }
                        $type->sold += 1;
                        $type->save();
                    }
                }

                $sale = [
                    'payment_method'   => $method !== '' ? $method : null,
                    'sold_by_staff_id' => $staff->id,
                    'sold_at'          => now(),
                ];

                if ($printed) {
                    $claimed = Ticket::whereKey($printed->id)->where('event_id', $event->id)
                        ->where('status', Ticket::STATUS_UNSOLD)
                        ->update($sale + [
                            'ticket_type_id' => $data['ticket_type_id'] ?? $printed->ticket_type_id,
                            'holder_name'    => $data['name'],
                            'holder_phone'   => $data['phone'],
                            'status'         => Ticket::STATUS_VALID,
                        ]);
                    if ($claimed !== 1) {
                        throw new \Modules\Tagtoa\App\Exceptions\TicketRuleException(__('Ce billet a déjà été vendu ou n\'est plus disponible.'));
                    }

                    $ticket = Ticket::findOrFail($printed->id);
                    if ($charged) {
                        $txn = \Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', $charged['ref'])->first();
                        $txn?->update(['meta' => array_merge((array) $txn->meta, ['ticket_id' => $ticket->id])]);
                    }

                    return $ticket;
                }

                if (! empty($data['uid'])) {
                    // Carte NFC physique : billet + tag liés (entrée par tap).
                    $res = app(EncodeParticipantCard::class)->handle($event, $data);
                    $ticket = $res['ticket'];

                    // Crédit initial optionnel : recharge le wallet de la carte (comme
                    // l'encodage du tableau de bord). Prix côté serveur, idempotent.
                    $credit = (float) ($data['credit'] ?? 0);
                    if ($credit > 0 && $res['tag']->walletAccount) {
                        $acct = $res['tag']->walletAccount;
                        app(\Modules\Tagtoa\App\Actions\Event\Wallet\TopUpWallet::class)->handle(
                            $acct,
                            \Modules\Tagtoa\App\Support\Money::toMinor($credit, $acct->currency),
                            ['idempotency_key' => 'staff-encode-'.$ticket->id, 'payment_ref' => 'ENCODAGE-STAFF']
                        );
                    }
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

                // Moyen de paiement + vendeur/date (historique de vente) dans TAGTOA.
                $ticket->update($sale);

                // Le débit connaît désormais son billet : un rejeu du même identifiant répond
                // « déjà vendu » au lieu d'émettre un second billet (ou de rembourser à tort).
                if ($charged) {
                    $txn = \Modules\Tagtoa\App\Models\Card\CardTxn::where('reference', $charged['ref'])->first();
                    $txn?->update(['meta' => array_merge((array) $txn->meta, ['ticket_id' => $ticket->id])]);
                }

                return $ticket;
            });
        } catch (\Throwable $e) {
            $refunded = $this->refundSale($event, $staff, $charged, $e);
            $known = $e instanceof \Modules\Tagtoa\App\Exceptions\TicketRuleException;
            if (! $known) {
                report($e);
            }
            $message = $known ? $e->getMessage() : __('Réessayez.');
            if ($charged && $refunded) {
                $message .= ' '.__('La carte a été remboursée.');
            } elseif ($charged) {
                $message .= ' '.__('ATTENTION : la carte a été débitée et le remboursement automatique a échoué — voir l\'organisateur.');
            }

            return response()->json(['ok' => false, 'message' => $message], 422);
        }

        // Commission : APRÈS la validation de la vente — un échec ici ne doit ni annuler le
        // billet ni renvoyer une erreur (le client rejouerait une vente déjà faite).
        try {
            if ($ticketPrice > 0) {
                app(\Modules\Tagtoa\App\Services\Billing\RevenueService::class)
                    ->record('event_ticket', $ticket->id, 'event', $ticketPrice, $event->tenant_id, $event->currency);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['ok' => true, 'code' => $ticket->code, 'name' => $ticket->holder_name]);
    }

    /**
     * Annule le débit d'une vente au guichet dont le billet n'a pas pu être émis
     * (D2 : remboursement automatique, avec trace d'audit). Idempotent : la
     * référence du remboursement est dérivée de celle du débit. Ne lève jamais.
     */
    private function refundSale(Event $event, Staff $staff, ?array $charged, \Throwable $cause): bool
    {
        if (! $charged) {
            return false;
        }

        $audit = app(\Modules\Tagtoa\App\Services\Audit\AuditService::class);
        try {
            $txn = app(\Modules\Tagtoa\App\Services\Card\CardWalletService::class)->refund($charged['card'], (float) $charged['amount'], [
                'tenant_id'    => $event->tenant_id,
                'context_type' => 'event',
                'context_id'   => $event->id,
                // Dérivée du débit (même clé) : un second passage ne rembourse pas deux fois.
                'reference'    => 'evrefund-'.($charged['key'] ?? substr(sha1($charged['ref']), 0, 24)),
                'meta'         => ['reason' => 'door_sale_failed', 'charge_ref' => $charged['ref']],
            ]);
            $ok = $txn !== null;
        } catch (\Throwable $e) {
            report($e);
            $ok = false;
        }

        $audit->log($ok ? 'event_door_sale_refunded' : 'event_door_sale_refund_failed', $event,
            'Vente au guichet échouée ('.class_basename($cause).') — carte '.($charged['card']->code ?? '#'.$charged['card']->id)
            .', '.$charged['amount'].' '.$event->currency.', débit '.$charged['ref'].', vendeur #'.$staff->id);

        return $ok;
    }

    /* ---------------- Retrait carte NFC (billet acheté en ligne → UID) ---------------- */

    public function pickup(Request $request, string $alias): JsonResponse
    {
        $event = $this->eventByAlias($alias);
        $staff = $this->requireRole($event, 'pickup');
        if ($staff instanceof JsonResponse) {
            return $staff;
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'uid'  => ['required', 'string', 'max:120'],
        ]);

        $ticket = Ticket::where('event_id', $event->id)->where('code', trim($data['code']))->first();
        if (! $ticket || ! $ticket->isValid()) {
            return response()->json(['ok' => false, 'message' => __('Billet introuvable.')], 200);
        }
        // Billet en ligne : payé seulement (une commande en attente ne donne pas accès).
        if ($ticket->order_id) {
            $order = \Modules\Tagtoa\App\Models\Event\Order::find($ticket->order_id);
            if ($order && ! $order->isPaid()) {
                return response()->json(['ok' => false, 'message' => __('Commande non payée.')], 200);
            }
        }

        try {
            app(\Modules\Tagtoa\App\Actions\Event\Wallet\IssueNfcTag::class)->handle($event, $data['uid'], [
                'label'     => $ticket->holder_name,
                'phone'     => $ticket->holder_phone,
                'ticket_id' => $ticket->id,
                'kind'      => 'card',
            ]);
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
