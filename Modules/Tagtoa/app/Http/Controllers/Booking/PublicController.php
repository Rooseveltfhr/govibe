<?php

namespace Modules\Tagtoa\App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
use Modules\Tagtoa\App\Models\Review\Review;
use Modules\Tagtoa\App\Services\Booking\BookingService;
use Modules\Tagtoa\App\Services\Review\ReviewService;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA BOOKING — page publique de réservation (NFC / QR), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $page = BookingPage::where('alias', $alias)->where('is_active', true)
            ->with(['payPage', 'activeServices'])
            ->firstOrFail();

        $page->incrementQuietly('views');

        $summary = app(ReviewService::class)->summary('booking', (int) $page->id);
        $reviews = Review::query()->forSubject('booking', (int) $page->id)->approved()->latest()->limit(20)->get();

        return view('tagtoa::booking.show', [
            'page'     => $page,
            'services' => $page->activeServices,
            'reviews'  => $reviews,
            'summary'  => $summary,
        ]);
    }

    /** Capture un rendez-vous (prix imposé côté serveur). Renvoie JSON. */
    public function reserve(Request $request, string $alias): JsonResponse
    {
        $page = BookingPage::where('alias', $alias)->where('is_active', true)->with('payPage')->firstOrFail();

        $data = $request->validate([
            'service_id'     => ['nullable', 'integer'],
            'customer_name'  => ['required', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'starts_at'      => ['required', 'string', 'max:40'],
            'note'           => ['nullable', 'string', 'max:500'],
            'client_uuid'    => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $booking = app(BookingService::class)->placeBooking($page, $data);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => __('Créneau invalide. Choisissez une date à venir.')], 422);
        }

        return response()->json([
            'ok'           => true,
            'reference'    => $booking->reference,
            'starts_at'    => $booking->starts_at?->format('d/m/Y H:i'),
            'price'        => (float) $booking->price > 0 ? Money::format($booking->price, $booking->currency) : null,
            'whatsapp_url' => $this->whatsappUrl($page, $booking),
            'pay_url'      => ($page->payPage && (float) $booking->price > 0) ? url('/pay/'.$page->payPage->alias) : null,
        ]);
    }

    /** Lien WhatsApp pré-rempli incluant la référence du rendez-vous. */
    protected function whatsappUrl(BookingPage $page, $booking): ?string
    {
        if (! $page->whatsapp_digits) {
            return null;
        }
        $lines = [__('Bonjour').' '.$page->name.', '.__('je voudrais réserver :')];
        $booking->loadMissing('service');
        if ($booking->service) {
            $lines[] = '• '.$booking->service->name;
        }
        $lines[] = __('Date').' : '.$booking->starts_at?->format('d/m/Y H:i');
        $lines[] = '';
        $lines[] = __('Référence').': '.$booking->reference;
        if ((float) $booking->price > 0) {
            $lines[] = __('Total').': '.Money::format($booking->price, $booking->currency);
        }

        return 'https://wa.me/'.$page->whatsapp_digits.'?text='.rawurlencode(implode("\n", $lines));
    }
}
