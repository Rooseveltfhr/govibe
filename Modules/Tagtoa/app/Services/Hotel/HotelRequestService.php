<?php

namespace Modules\Tagtoa\App\Services\Hotel;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Hotel\HotelRequest;
use Modules\Tagtoa\App\Models\Hotel\HotelRoom;
use Modules\Tagtoa\App\Models\Hotel\HotelService;
use Modules\Tagtoa\App\Support\Hotel\HotelRequestRules as R;

/**
 * TAGTOA HÔTEL (H5) — créer, suivre, annuler (invité) et traiter (hôtel) une demande.
 *
 * Reçoit TOUJOURS le commerce en paramètre et filtre explicitement (`allTenants()`) : le contexte
 * public n'a pas de commerce courant. L'invité n'a pas de compte : référence + secret de suivi
 * (128 bits, jamais stocké en clair).
 */
class HotelRequestService
{
    public const OK              = 'ok';
    public const ROOM_UNAVAILABLE = 'room_unavailable';
    public const SERVICE_UNAVAILABLE = 'service_unavailable';
    public const DUPLICATE       = 'duplicate';      // la même demande est déjà ouverte pour cette chambre
    public const TOO_MANY_OPEN   = 'too_many_open';
    public const NOT_FOUND       = 'not_found';
    public const NOT_ALLOWED     = 'not_allowed';    // transition ou annulation impossible

    /**
     * @return array{result:string, request:?HotelRequest, secret:?string}
     */
    public function create(string $tenantId, int $roomId, int $serviceId, ?string $note): array
    {
        try {
            return DB::transaction(function () use ($tenantId, $roomId, $serviceId, $note) {
                // Verrou de la chambre : deux demandes simultanées ne dépassent pas le plafond.
                $room = HotelRoom::allTenants()->where('tenant_id', $tenantId)->whereKey($roomId)
                    ->where('is_active', true)->lockForUpdate()->first();
                if (! $room) {
                    return $this->non(self::ROOM_UNAVAILABLE);
                }

                $service = HotelService::allTenants()->where('tenant_id', $tenantId)->whereKey($serviceId)
                    ->where('is_active', true)->whereIn('kind', R::REQUESTABLE_KINDS)->first();
                if (! $service) {
                    return $this->non(self::SERVICE_UNAVAILABLE);
                }

                $ouvertes = HotelRequest::allTenants()->where('tenant_id', $tenantId)->where('room_id', $room->id)
                    ->whereIn('status', R::OPEN);

                if ((clone $ouvertes)->where('service_id', $service->id)->exists()) {
                    return $this->non(self::DUPLICATE);
                }
                if ((clone $ouvertes)->count() >= R::OPEN_MAX_PER_ROOM) {
                    return $this->non(self::TOO_MANY_OPEN);
                }

                $secret = bin2hex(random_bytes(16));
                $req = HotelRequest::allTenants()->create([
                    'tenant_id'    => $tenantId,
                    'room_id'      => $room->id,
                    'service_id'   => $service->id,
                    'reference'    => $this->reference(),
                    'track_hash'   => hash('sha256', $secret),
                    'kind'         => $service->kind,
                    'service_name' => $service->name,
                    'team'         => $service->team,
                    'status'       => 'new',
                    'note'         => R::cleanNote($note),
                ]);

                return ['result' => self::OK, 'request' => $req, 'secret' => $secret];
            });
        } catch (UniqueConstraintViolationException) {
            // Collision de référence (1 chance sur ~10^12) : l'invité réessaie.
            return $this->non(self::ROOM_UNAVAILABLE);
        }
    }

    /** La demande d'un invité, SI référence ET secret concordent et que le lien n'a pas expiré. */
    public function forGuest(?string $reference, ?string $secret): ?HotelRequest
    {
        if (! is_string($reference) || ! is_string($secret) || $reference === '' || $secret === ''
            || strlen($reference) > 16 || strlen($secret) > 64) {
            return null;
        }
        $req = HotelRequest::allTenants()->where('reference', $reference)->first();
        if (! $req || ! hash_equals($req->track_hash, hash('sha256', $secret))) {
            return null;
        }
        if ($req->created_at && $req->created_at->lt(now()->subDays(R::TRACK_DAYS))) {
            return null;
        }

        return $req;
    }

    /** L'invité annule tant que l'hôtel n'a rien pris en compte. */
    public function guestCancel(?string $reference, ?string $secret): array
    {
        $req = $this->forGuest($reference, $secret);
        if (! $req) {
            return $this->non(self::NOT_FOUND);
        }

        return DB::transaction(function () use ($req) {
            $verrouillee = HotelRequest::allTenants()->where('tenant_id', $req->tenant_id)->whereKey($req->id)->lockForUpdate()->first();
            if (! $verrouillee || $verrouillee->status !== 'new') {
                return ['result' => self::NOT_ALLOWED, 'request' => $verrouillee, 'secret' => null];
            }
            $verrouillee->forceFill(['status' => 'cancelled', 'closed_at' => now()])->save();

            return ['result' => self::OK, 'request' => $verrouillee, 'secret' => null];
        });
    }

    /** L'hôtel (commerce passé en paramètre) fait évoluer la demande. */
    public function setStatus(string $tenantId, int $id, string $to, ?string $reason = null): array
    {
        return DB::transaction(function () use ($tenantId, $id, $to, $reason) {
            $req = HotelRequest::allTenants()->where('tenant_id', $tenantId)->whereKey($id)->lockForUpdate()->first();
            if (! $req) {
                return $this->non(self::NOT_FOUND);
            }
            if (! R::canMove($req->status, $to)) {
                return ['result' => self::NOT_ALLOWED, 'request' => $req, 'secret' => null];
            }
            $req->forceFill([
                'status'     => $to,
                'handled_at' => $req->handled_at ?? now(),
                'closed_at'  => in_array($to, ['done', 'rejected'], true) ? now() : null,
                'reason'     => $to === 'rejected' ? mb_substr(trim((string) $reason), 0, R::REASON_MAX) ?: null : null,
            ])->save();

            return ['result' => self::OK, 'request' => $req, 'secret' => null];
        });
    }

    private function reference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTVWXYZ';
        $s = '';
        for ($i = 0; $i < 8; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return 'HR-'.$s;
    }

    private function non(string $result): array
    {
        return ['result' => $result, 'request' => null, 'secret' => null];
    }
}
