<?php

namespace App\Http\Controllers\Admin\Etablissements;

use App\Http\Controllers\Controller;
use App\Models\Etablissements\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index()
    {
        $bookings = Booking::with('establishment')
            ->orderByRaw("status = 'pending' desc")
            ->orderBy('starts_on')
            ->get();

        return view('admin.reservations.index', compact('bookings'));
    }

    public function update(Request $request, Booking $reservation): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,confirmed,cancelled'],
        ]);

        $reservation->update($data);

        return redirect()->route('admin.reservations.index')->with('status', 'Réservation mise à jour.');
    }
}
