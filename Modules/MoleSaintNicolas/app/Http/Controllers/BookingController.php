<?php

namespace App\Http\Controllers;

use App\Mail\NewBookingReceived;
use App\Models\Etablissements\Establishment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BookingController extends Controller
{
    public function store(Request $request, Establishment $establishment): RedirectResponse
    {
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_phone' => ['required', 'string', 'max:50'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['nullable', 'date', 'after:starts_on'],
            'reservation_time' => ['nullable', 'date_format:H:i'],
            'party_size' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $booking = $establishment->bookings()->create($data);

        $adminEmails = User::role(['super_admin', 'admin'])->pluck('email');
        if ($adminEmails->isNotEmpty()) {
            Mail::to($adminEmails->all())->send(new NewBookingReceived($booking));
        }

        $routeName = $establishment->type === 'hotel' ? 'hotels.show' : 'restaurants.show';

        return redirect()
            ->route($routeName, $establishment->slug)
            ->with('status', "Votre demande de réservation a bien été envoyée. {$establishment->name} vous contactera directement pour la confirmer.");
    }
}
