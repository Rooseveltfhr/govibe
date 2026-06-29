<?php

namespace Modules\Tagtoa\App\Services\Booking;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Booking\Booking;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
use Modules\Tagtoa\App\Services\Billing\RevenueService;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;

/**
 * TAGTOA BOOKING — capture & gestion des rendez-vous.
 *
 * Sécurité financière : le prix est imposé par le SERVEUR (depuis la prestation
 * active de CETTE page) — jamais le prix envoyé par le client. Idempotent via
 * client_uuid. La commission plateforme est enregistrée quand le RDV est honoré.
 */
class BookingService
{
    public function __construct(protected RevenueService $revenue, protected NotificationService $notifications)
    {
    }

    public function placeBooking(BookingPage $page, array $payload): Booking
    {
        $uuid = $payload['client_uuid'] ?? null;
        if ($uuid && $existing = Booking::where('client_uuid', $uuid)->first()) {
            return $existing; // idempotent : déjà notifié à la création
        }

        $booking = DB::transaction(function () use ($page, $payload, $uuid) {
            // Prestation autorisée : prestation active de CETTE page (prix imposé serveur).
            $service = null;
            if (! empty($payload['service_id'])) {
                $service = $page->services()->where('is_active', true)
                    ->whereKey((int) $payload['service_id'])->first();
            }

            $starts = $this->parseStart($payload['starts_at'] ?? null);
            if (! $starts) {
                throw new \RuntimeException('invalid_slot');
            }

            return $page->bookings()->create([
                'tenant_id'      => $page->tenant_id,
                'service_id'     => $service?->id,
                'reference'      => Booking::generateReference(),
                'customer_name'  => $payload['customer_name'] ?? '',
                'customer_phone' => $payload['customer_phone'] ?? null,
                'customer_email' => $payload['customer_email'] ?? null,
                'starts_at'      => $starts,
                'status'         => 'pending',
                'note'           => $payload['note'] ?? null,
                'price'          => $service ? round((float) $service->price, 2) : 0,
                'currency'       => $page->currency ?: 'HTG',
                'client_uuid'    => $uuid,
            ]);
        });

        // Notifications hors transaction (envoi tolérant, ne casse jamais le parcours).
        $this->notifications->notifyNewBooking($booking);

        return $booking;
    }

    /** Marque honoré + enregistre la commission plateforme (idempotent). */
    public function markCompleted(Booking $booking): Booking
    {
        if ($booking->status !== 'completed') {
            $booking->update(['status' => 'completed']);
            if ((float) $booking->price > 0) {
                $this->revenue->record('booking', $booking->id, 'booking', (float) $booking->price, $booking->tenant_id, $booking->currency);
            }
        }

        return $booking;
    }

    /** Normalise un créneau, refuse une date passée. */
    protected function parseStart($value): ?Carbon
    {
        if (! $value) {
            return null;
        }
        try {
            $dt = Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }

        return $dt->lt(now()->subDay()) ? null : $dt;
    }
}
