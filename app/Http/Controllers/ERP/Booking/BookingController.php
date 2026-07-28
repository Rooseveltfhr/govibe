<?php

namespace App\Http\Controllers\ERP\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function index()
    {
        $stats = [
            'total'     => $this->safeCount(fn() => Booking::count()),
            'today'     => $this->safeCount(fn() => Booking::whereDate('start_at', today())->count()),
            'confirmed' => $this->safeCount(fn() => Booking::where('status', 'confirmed')->count()),
            'pending'   => $this->safeCount(fn() => Booking::where('status', 'pending')->count()),
        ];

        $bookings = $this->safeQuery(
            fn() => Booking::with('client')->orderByDesc('start_at')->paginate(20),
            collect()
        );

        return view('erp.booking.index', compact('stats', 'bookings'));
    }

    public function create()
    {
        $clients = $this->safeQuery(fn() => Client::orderBy('name')->get(), collect());
        return view('erp.booking.create', compact('clients'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'   => 'nullable|exists:clients,id',
            'title'       => 'required|string|max:255',
            'start_at'    => 'required|date',
            'end_at'      => 'required|date|after:start_at',
            'space'       => 'nullable|string|max:100',
            'notes'       => 'nullable|string',
        ]);

        try {
            Booking::create($data + ['status' => 'pending']);
            return redirect()->route('erp.booking.index')->with('success', 'Réservation créée.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur: '.$e->getMessage())->withInput();
        }
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
