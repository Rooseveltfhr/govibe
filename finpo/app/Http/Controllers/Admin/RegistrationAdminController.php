<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Registration;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrationAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->filtered($request)->with('category')->latest();

        return view('admin.registrations.index', [
            'registrations' => $query->paginate(25)->withQueryString(),
            'categories'    => TicketCategory::orderBy('sort')->get(),
        ]);
    }

    public function show(Registration $registration)
    {
        return view('admin.registrations.show', [
            'registration' => $registration->load(['category', 'payments', 'checkins.user', 'certificate', 'coupon']),
        ]);
    }

    public function status(Request $request, Registration $registration)
    {
        $data = $request->validate([
            'action' => 'required|string|in:mark_paid,mark_pending,cancel,restore,checkin,undo_checkin',
        ]);

        match ($data['action']) {
            'mark_paid'    => $registration->update(['payment_status' => 'paid']) && $registration->payments()->update(['status' => 'paid', 'paid_at' => now()]),
            'mark_pending' => $registration->update(['payment_status' => 'pending']),
            'cancel'       => $registration->update(['status' => 'cancelled']),
            'restore'      => $registration->update(['status' => 'confirmed']),
            'checkin'      => $registration->update(['checked_in_at' => now()]),
            'undo_checkin' => $registration->update(['checked_in_at' => null]),
        };

        return back()->with('ok', 'Inscription mise à jour.');
    }

    public function certificate(Registration $registration)
    {
        $certificate = $registration->certificate()->firstOrCreate([], [
            'number'    => Certificate::nextNumber(),
            'issued_at' => now(),
        ]);

        return redirect()->route('certificate.show', $certificate->number);
    }

    public function export(Request $request): StreamedResponse
    {
        $registrations = $this->filtered($request)->with('category')->orderBy('id')->get();

        return response()->streamDownload(function () use ($registrations) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
            fputcsv($out, ['Numéro', 'Prénom', 'Nom', 'Email', 'Téléphone', 'Institution', 'Fonction', 'Pays', 'Catégorie', 'Billet', 'Montant', 'Devise', 'Paiement', 'Statut paiement', 'Statut', 'Check-in', 'Créé le'], ';');
            foreach ($registrations as $r) {
                fputcsv($out, [
                    $r->number, $r->first_name, $r->last_name, $r->email, $r->phone,
                    $r->institution, $r->position, $r->country, $r->audienceLabel(),
                    $r->category?->name, $r->amount, $r->currency, $r->payment_method,
                    $r->payment_status, $r->status,
                    $r->checked_in_at?->format('Y-m-d H:i'), $r->created_at->format('Y-m-d H:i'),
                ], ';');
            }
            fclose($out);
        }, 'finpo-inscriptions-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filtered(Request $request)
    {
        $query = Registration::query();

        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('number', 'like', "%{$q}%")
                    ->orWhere('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('institution', 'like', "%{$q}%");
            });
        }
        if ($cat = $request->query('categorie')) {
            $query->where('ticket_category_id', $cat);
        }
        if ($status = $request->query('paiement')) {
            $query->where('payment_status', $status);
        }
        if ($request->query('checkin') === '1') {
            $query->whereNotNull('checked_in_at');
        }

        return $query;
    }
}
