<?php

namespace App\Http\Controllers;

use App\Mail\RegistrationConfirmed;
use App\Models\Coupon;
use App\Models\Payment;
use App\Models\Registration;
use App\Models\TicketCategory;
use App\Support\Qr;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RegistrationController extends Controller
{
    public function index()
    {
        return view('pages.register', [
            'categories' => TicketCategory::where('active', true)->orderBy('sort')->get(),
        ]);
    }

    public function form(TicketCategory $category)
    {
        abort_unless($category->isOnSale(), 404);

        return view('pages.register-form', ['category' => $category]);
    }

    public function store(Request $request, TicketCategory $category)
    {
        abort_unless($category->isOnSale(), 404);

        $data = $request->validate([
            'first_name'        => 'required|string|max:120',
            'last_name'         => 'required|string|max:120',
            'email'             => 'required|email|max:190',
            'phone'             => 'nullable|string|max:60',
            'institution'       => 'nullable|string|max:190',
            'position'          => 'nullable|string|max:190',
            'country'           => 'required|string|max:120',
            'emergency_contact' => 'nullable|string|max:190',
            'payment_method'    => 'required|string|in:'.implode(',', array_keys(config('finpo.payment_methods'))),
            'coupon'            => 'nullable|string|max:60',
            'company'           => 'nullable|string|max:60', // honeypot anti-spam
        ]);

        if (! empty($data['company'])) {
            return redirect()->route('register');
        }

        // Prix imposé côté serveur — jamais depuis le formulaire.
        $amount = (int) $category->price;
        $coupon = null;

        if (! empty($data['coupon'])) {
            $coupon = Coupon::where('code', strtoupper(trim($data['coupon'])))->first();
            if (! $coupon || ! $coupon->isUsable()) {
                return back()->withInput()->withErrors(['coupon' => __('Ce code promo est invalide ou expiré.')]);
            }
            $amount = $coupon->apply($amount);
        }

        $free = $amount === 0;

        $registration = DB::transaction(function () use ($data, $category, $coupon, $amount, $free) {
            if ($coupon) {
                $coupon->increment('used');
            }

            $registration = Registration::create([
                'number'            => Registration::nextNumber(),
                'qr_token'          => Registration::newQrToken(),
                'ticket_category_id'=> $category->id,
                'coupon_id'         => $coupon?->id,
                'first_name'        => $data['first_name'],
                'last_name'         => $data['last_name'],
                'email'             => strtolower($data['email']),
                'phone'             => $data['phone'] ?? null,
                'institution'       => $data['institution'] ?? null,
                'position'          => $data['position'] ?? null,
                'country'           => $data['country'],
                'audience'          => $category->audience,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'amount'            => $amount,
                'currency'          => $category->currency,
                'payment_method'    => $free ? 'free' : $data['payment_method'],
                'payment_status'    => $free ? 'free' : 'pending',
            ]);

            if (! $free) {
                Payment::create([
                    'registration_id' => $registration->id,
                    'method'          => $data['payment_method'],
                    'amount'          => $amount,
                    'currency'        => $category->currency,
                    'status'          => 'pending',
                ]);
            }

            return $registration;
        });

        // Email de confirmation — tolérant : l'inscription n'échoue jamais
        // à cause d'un problème SMTP.
        try {
            Mail::to($registration->email)->send(new RegistrationConfirmed($registration));
        } catch (\Throwable $e) {
            Log::warning('FINPO mail non envoyé: '.$e->getMessage());
        }

        return redirect()->route('ticket.show', $registration->qr_token)
            ->with('ok', __('Inscription confirmée ! Votre billet électronique est prêt.'));
    }

    /** Vérification AJAX d'un code promo (affiche le nouveau prix). */
    public function couponCheck(Request $request)
    {
        $data = $request->validate([
            'code'        => 'required|string|max:60',
            'category_id' => 'required|integer|exists:ticket_categories,id',
        ]);

        $category = TicketCategory::findOrFail($data['category_id']);
        $coupon = Coupon::where('code', strtoupper(trim($data['code'])))->first();

        if (! $coupon || ! $coupon->isUsable()) {
            return response()->json(['valid' => false]);
        }

        return response()->json([
            'valid'  => true,
            'amount' => $coupon->apply((int) $category->price),
        ]);
    }

    public function ticket(string $token)
    {
        $registration = Registration::where('qr_token', $token)->with('category')->firstOrFail();

        return view('pages.ticket', [
            'registration' => $registration,
            'qr'           => Qr::svgDataUri(route('ticket.show', $registration->qr_token)),
        ]);
    }

    public function ticketPrint(string $token)
    {
        $registration = Registration::where('qr_token', $token)->with('category')->firstOrFail();

        return view('pages.ticket-print', [
            'registration' => $registration,
            'qr'           => Qr::svgDataUri(route('ticket.show', $registration->qr_token)),
        ]);
    }

    public function ticketIcs(string $token)
    {
        $registration = Registration::where('qr_token', $token)->firstOrFail();
        $tz = config('finpo.timezone');

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FINPO 2026//FR',
            'BEGIN:VEVENT',
            'UID:finpo-ticket-'.$registration->id.'@finpo.ht',
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.Carbon::parse(config('finpo.starts_at'), $tz)->utc()->format('Ymd\THis\Z'),
            'DTEND:'.Carbon::parse(config('finpo.ends_at'), $tz)->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.config('finpo.name'),
            'LOCATION:'.str_replace([',', ';'], ['\,', '\;'], config('finpo.venue.name').' — '.config('finpo.venue.city')),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="finpo-2026.ics"',
        ]);
    }

    public function badge(string $token)
    {
        $registration = Registration::where('qr_token', $token)->with('category')->firstOrFail();

        return view('pages.badge', [
            'registration' => $registration,
            'qr'           => Qr::svgDataUri($registration->number.'|'.$registration->qr_token, 240),
        ]);
    }
}
