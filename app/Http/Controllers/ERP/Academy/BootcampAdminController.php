<?php

namespace App\Http\Controllers\ERP\Academy;

use App\Http\Controllers\Controller;
use App\Models\BootcampRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class BootcampAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');

        $query = BootcampRegistration::latest();
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $registrations = $query->paginate(20)->withQueryString();

        $stats = [
            'total'    => BootcampRegistration::count(),
            'pending'  => BootcampRegistration::where('status', 'pending')->count(),
            'approved' => BootcampRegistration::where('status', 'approved')->count(),
            'rejected' => BootcampRegistration::where('status', 'rejected')->count(),
            'premium'  => BootcampRegistration::where('module_choice', 'premium')->count(),
            'revenue'  => BootcampRegistration::where('status', 'approved')->where('currency', 'USD')->sum('amount'),
        ];

        return view('erp.academy.bootcamp.index', compact('registrations', 'stats', 'status'));
    }

    public function show(BootcampRegistration $registration): View
    {
        return view('erp.academy.bootcamp.show', compact('registration'));
    }

    public function approve(BootcampRegistration $registration): RedirectResponse
    {
        $registration->update([
            'status'      => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);

        return back()->with('success', "Inscription de {$registration->full_name} approuvée.");
    }

    public function reject(Request $request, BootcampRegistration $registration): RedirectResponse
    {
        $registration->update([
            'status'      => 'rejected',
            'admin_notes' => $request->input('reason'),
        ]);

        return back()->with('error', "Inscription de {$registration->full_name} refusée.");
    }

    public function export(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $regs = BootcampRegistration::where('status', 'approved')->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="bootcamp_ai_2026_'.date('Y-m-d').'.csv"',
        ];

        return response()->stream(function () use ($regs) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($out, ['Nom', 'Email', 'Téléphone', 'Pays', 'Profession', 'Organisation', 'Module', 'Paiement', 'Montant', 'Devise', 'Date']);
            foreach ($regs as $r) {
                fputcsv($out, [
                    $r->full_name, $r->email, $r->phone, $r->country,
                    $r->profession, $r->organization, $r->module_label,
                    $r->payment_method, $r->amount, $r->currency,
                    $r->created_at->format('d/m/Y'),
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
