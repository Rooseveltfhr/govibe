<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Hotel\HotelRatePlan;
use Modules\Tagtoa\App\Models\Hotel\HotelReservation;
use Modules\Tagtoa\App\Models\Hotel\HotelRoomType;
use Modules\Tagtoa\App\Support\Hotel\HotelReservationRules as R;

/**
 * TAGTOA HÔTEL (HM-3) — créer, suivre, annuler (invité) et traiter (hôtel) une demande de réservation.
 *
 * Reçoit TOUJOURS le commerce en paramètre et filtre explicitement (`allTenants()`) : le contexte public n'a pas de
 * commerce courant. L'invité n'a pas de compte : référence + secret de suivi (128 bits, seul le sha256 est stocké).
 * Aucun paiement ici. Le prix et ce qui est inclus sont lus DANS LA BASE (jamais dans la requête) et gelés.
 */
class HotelReservationService
{
    public const OK                = 'ok';
    public const HOTEL_UNAVAILABLE = 'hotel_unavailable';
    public const PLAN_UNAVAILABLE  = 'plan_unavailable';
    public const BAD_DATE          = 'bad_date';
    public const BAD_TIME          = 'bad_time';
    public const BAD_PHONE         = 'bad_phone';
    public const DUPLICATE         = 'duplicate';
    public const TOO_MANY          = 'too_many';
    public const NOT_FOUND         = 'not_found';
    public const NOT_ALLOWED       = 'not_allowed';

    /**
     * @param  array{plan_id:int|string,date:string,nights?:mixed,time?:?string,guests?:mixed,name:string,phone:string,note?:?string}  $d  entrée VALIDÉE
     * @return array{result:string, reservation:?HotelReservation, secret:?string}
     */
    public function create(string $tenantId, array $d, Carbon $now): array
    {
        $digits = R::cleanPhone($d['phone'] ?? null);
        if ($digits === null) {
            return $this->non(self::BAD_PHONE);
        }

        try {
            return DB::transaction(function () use ($tenantId, $d, $now, $digits) {
                // Verrou de l'hôtel : deux demandes simultanées ne dépassent pas les plafonds.
                $hotel = Business::whereKey($tenantId)->lockForUpdate()->first();
                if (! $hotel || $hotel->type !== 'hotel' || ! $hotel->is_active) {
                    return $this->non(self::HOTEL_UNAVAILABLE);
                }

                $plan = HotelRatePlan::allTenants()->where('tenant_id', $tenantId)->whereKey((int) $d['plan_id'])->where('is_active', true)->first();
                $type = $plan ? HotelRoomType::allTenants()->where('tenant_id', $tenantId)->whereKey($plan->room_type_id)->where('is_active', true)->first() : null;
                if (! $plan || ! $type) {
                    return $this->non(self::PLAN_UNAVAILABLE);
                }

                $date = Carbon::createFromFormat('Y-m-d', (string) $d['date'], $now->getTimezone());
                if (! $date || $date->format('Y-m-d') !== (string) $d['date'] || ! R::dateOk($date->startOfDay(), $now)) {
                    return $this->non(self::BAD_DATE);
                }

                $block = $plan->kind === 'block';
                $nights = 1;
                $time = null;
                if ($block) {
                    $time = (string) ($d['time'] ?? '');
                    if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
                        return $this->non(self::BAD_TIME);
                    }
                    $debut = Carbon::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$time, $now->getTimezone());
                    if ($debut->lt($now->copy()->subMinutes(5))) {
                        return $this->non(self::BAD_TIME);
                    }
                } else {
                    $nights = max(1, min(R::NIGHTS_MAX, (int) ($d['nights'] ?? 1)));
                }

                $key = R::phoneKey($digits);
                $ouvertes = HotelReservation::allTenants()->where('tenant_id', $tenantId)->where('phone_key', $key);

                // Même demande déjà ouverte (double envoi, deux onglets) : pas de doublon.
                $meme = (clone $ouvertes)->whereIn('status', R::OPEN)->where('rate_plan_id', $plan->id)
                    ->whereDate('starts_on', $date->format('Y-m-d'))
                    ->when($block, fn ($q) => $q->where('start_time', $time.':00'));
                if ($meme->exists()) {
                    return $this->non(self::DUPLICATE);
                }
                if ((clone $ouvertes)->where('status', 'new')->where('created_at', '>=', $now->copy()->subDays(R::NEW_FRESH_DAYS))->count() >= R::NEW_MAX_PER_PHONE) {
                    return $this->non(self::TOO_MANY);
                }

                $prix = (float) $plan->price;
                $secret = bin2hex(random_bytes(16));
                $resa = HotelReservation::allTenants()->create([
                    'tenant_id'      => $tenantId,
                    'reference'      => $this->reference(),
                    'track_hash'     => hash('sha256', $secret),
                    'room_type_id'   => $type->id,
                    'rate_plan_id'   => $plan->id,
                    'room_type_name' => $type->name,
                    'plan_kind'      => $block ? 'block' : 'night',
                    'plan_hours'     => $block ? $plan->hours : null,
                    'price'          => $prix,
                    'currency'       => strtoupper((string) $plan->currency),
                    'nights'         => $nights,
                    'total'          => R::total($block ? 'block' : 'night', $prix, $nights),
                    'inclusions'     => is_array($plan->inclusions) ? $plan->inclusions : null,
                    'starts_on'      => $date->format('Y-m-d'),
                    'start_time'     => $block ? $time.':00' : null,
                    'guests'         => isset($d['guests']) && $d['guests'] !== '' ? max(1, min(R::GUESTS_MAX, (int) $d['guests'])) : null,
                    'guest_name'     => R::cleanText($d['name'] ?? null, R::NAME_MAX),
                    'guest_phone'    => $digits,
                    'phone_key'      => $key,
                    'note'           => R::cleanText($d['note'] ?? null, R::NOTE_MAX),
                    'status'         => 'new',
                ]);

                return ['result' => self::OK, 'reservation' => $resa, 'secret' => $secret];
            });
        } catch (UniqueConstraintViolationException) {
            // Collision de référence (1 chance sur ~10^12) : l'invité réessaie.
            return $this->non(self::PLAN_UNAVAILABLE);
        }
    }

    /** La demande d'un invité, SI référence ET secret concordent et que le lien n'a pas expiré. */
    public function forGuest(?string $reference, ?string $secret): ?HotelReservation
    {
        if (! is_string($reference) || ! is_string($secret) || $reference === '' || $secret === ''
            || strlen($reference) > 16 || strlen($secret) > 64) {
            return null;
        }
        $r = HotelReservation::allTenants()->where('reference', $reference)->first();
        if (! $r || ! hash_equals($r->track_hash, hash('sha256', $secret))) {
            return null;
        }
        // Le lien vit TRACK_DAYS après la dernière des deux dates : création ou début du séjour (séjour lointain).
        $depuis = $r->created_at;
        if ($r->starts_on && (! $depuis || $r->starts_on->gt($depuis))) {
            $depuis = $r->starts_on;
        }
        if ($depuis && $depuis->lt(now()->subDays(R::TRACK_DAYS))) {
            return null;
        }

        return $r;
    }

    /** Les réservations d'un hôtel visibles par un employé : vide pour ménage et maintenance. */
    public function queryFor(string $tenantId, \Modules\Tagtoa\App\Models\Staff\Staff $staff)
    {
        $q = HotelReservation::allTenants()->where('tenant_id', $tenantId);

        return R::staffMaySee($staff->role, $staff->hotel_team) ? $q : $q->whereRaw('1 = 0');
    }

    /** L'invité annule tant que la demande est ouverte (nouvelle ou confirmée). */
    public function guestCancel(?string $reference, ?string $secret): array
    {
        $r = $this->forGuest($reference, $secret);
        if (! $r) {
            return $this->non(self::NOT_FOUND);
        }

        return DB::transaction(function () use ($r) {
            $v = HotelReservation::allTenants()->where('tenant_id', $r->tenant_id)->whereKey($r->id)->lockForUpdate()->first();
            if (! $v || ! $v->isOpen()) {
                return ['result' => self::NOT_ALLOWED, 'reservation' => $v, 'secret' => null];
            }
            $v->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();

            return ['result' => self::OK, 'reservation' => $v, 'secret' => null];
        });
    }

    /**
     * L'hôtel (commerce passé en paramètre) confirme ou refuse. `changed` dit si l'état a VRAIMENT changé (un double clic
     * répond OK sans rien changer : ne pas le journaliser deux fois). `$onlyFrom` : l'écran du personnel n'agit que sur
     * les « nouvelles » — une page périmée ne peut pas refuser une réservation qu'un collègue vient de confirmer.
     */
    public function setStatus(string $tenantId, int $id, string $to, ?string $reason = null, ?string $onlyFrom = null): array
    {
        return DB::transaction(function () use ($tenantId, $id, $to, $reason, $onlyFrom) {
            $r = HotelReservation::allTenants()->where('tenant_id', $tenantId)->whereKey($id)->lockForUpdate()->first();
            if (! $r) {
                return $this->non(self::NOT_FOUND);
            }
            if ($r->status === $to) {
                return ['result' => self::OK, 'reservation' => $r, 'secret' => null, 'changed' => false]; // double clic : déjà fait
            }
            if ($onlyFrom !== null && $r->status !== $onlyFrom) {
                return ['result' => self::NOT_ALLOWED, 'reservation' => $r, 'secret' => null];
            }
            if (! R::canMove($r->status, $to)) {
                return ['result' => self::NOT_ALLOWED, 'reservation' => $r, 'secret' => null];
            }
            $r->forceFill([
                'status'     => $to,
                'handled_at' => $r->handled_at ?? now(),
                'closed_at'  => $to === 'refused' ? now() : null,
                'reason'     => $to === 'refused' ? R::cleanText($reason, R::REASON_MAX) : null,
            ])->save();

            return ['result' => self::OK, 'reservation' => $r, 'secret' => null, 'changed' => true];
        });
    }

    private function reference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'HV-'.$s;
    }

    private function non(string $result): array
    {
        return ['result' => $result, 'reservation' => null, 'secret' => null];
    }
}
