<?php

namespace App\Http\Controllers\ERP\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index()
    {
        $stats = [
            'total'     => Booking::count(),
            'today'     => Booking::whereDate('start_datetime', today())->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'pending'   => Booking::where('status', 'pending')->count(),
        ];

        // space chargé aussi : la liste affiche son nom, sinon une requête par ligne.
        $bookings = Booking::with(['client', 'space'])
            ->orderByDesc('start_datetime')
            ->paginate(20);

        return view('erp.booking.index', compact('stats', 'bookings'));
    }

    public function create()
    {
        $clients = Client::orderBy('name')->get();
        $spaces  = Space::where('is_active', true)->orderBy('name')->get();

        return view('erp.booking.create', compact('clients', 'spaces'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'client_id'      => 'nullable|exists:clients,id',
            'space_id'       => 'nullable|exists:spaces,id',
            'start_datetime' => 'required|date',
            'end_datetime'   => 'required|date|after:start_datetime',
            'notes'          => 'nullable|string|max:2000',
        ], [
            'title.required'           => "L'intitulé est obligatoire.",
            'start_datetime.required'  => 'La date de début est obligatoire.',
            'end_datetime.required'    => 'La date de fin est obligatoire.',
            'end_datetime.after'       => 'La fin doit venir après le début.',
        ]);

        try {
            // La vérification et l'insertion tiennent dans une transaction :
            // sans cela, deux demandes simultanées passent toutes les deux le
            // contrôle avant que l'une n'ait écrit.
            DB::transaction(function () use ($data) {
                if (! empty($data['space_id'])) {
                    $conflit = Booking::query()
                        ->chevauche((int) $data['space_id'], $data['start_datetime'], $data['end_datetime'])
                        ->lockForUpdate()
                        ->first();

                    if ($conflit) {
                        throw ValidationException::withMessages([
                            'start_datetime' => sprintf(
                                'Cet espace est déjà réservé de %s à %s (%s).',
                                $conflit->start_datetime->format('d/m/Y H:i'),
                                $conflit->end_datetime->format('H:i'),
                                $conflit->reference
                            ),
                        ]);
                    }
                }

                Booking::create($data + [
                    'reference'      => Booking::genererReference(),
                    'status'         => 'pending',
                    'payment_status' => 'unpaid',
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Le détail part au journal ; l'utilisateur reçoit un message
            // utilisable, sans fuite sur la structure de la base.
            Log::error('Création de réservation échouée', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);

            return back()
                ->withInput()
                ->with('error', "La réservation n'a pas pu être enregistrée. L'incident a été signalé.");
        }

        return redirect()
            ->route('erp.booking.index')
            ->with('success', 'Réservation créée.');
    }
}
