<?php

namespace App\Http\Controllers\ERP\Reports;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BootcampRegistration;
use App\Models\Client;
use App\Models\Formation;
use App\Models\Inscription;
use App\Models\Invoice;
use App\Models\PosTransaction;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $stats = $this->globalStats($year);
        $monthlyRevenue = $this->monthlyRevenue($year);
        $availableYears = $this->availableYears();

        return view('erp.reports.index', compact('stats', 'monthlyRevenue', 'year', 'availableYears'));
    }

    public function formations(Request $request)
    {
        $year   = (int) $request->get('year', now()->year);
        $status = $request->get('status');

        $query = Formation::withCount('inscriptions')->orderByDesc('date_debut');
        if ($year) {
            $query->whereYear('date_debut', $year);
        }
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        $formations = $query->get();
        $totalInscriptions = $this->safeCount(fn () => Inscription::whereHas('formation', fn ($q) => $q->whereYear('date_debut', $year))->count());
        $presents = $this->safeCount(fn () => Inscription::where('present', true)->whereHas('formation', fn ($q) => $q->whereYear('date_debut', $year))->count());
        $availableYears = $this->availableYears();

        return view('erp.reports.formations', compact('formations', 'year', 'totalInscriptions', 'presents', 'availableYears', 'status'));
    }

    public function bootcamp(Request $request)
    {
        $status = $request->get('status');
        $module = $request->get('module');

        $query = BootcampRegistration::latest();
        if ($status) {
            $query->where('status', $status);
        }
        if ($module) {
            $query->where('module_choice', $module);
        }

        $registrations = $query->get();

        $stats = [
            'total'    => $this->safeCount(fn () => BootcampRegistration::count()),
            'approved' => $this->safeCount(fn () => BootcampRegistration::where('status', 'approved')->count()),
            'pending'  => $this->safeCount(fn () => BootcampRegistration::where('status', 'pending')->count()),
            'rejected' => $this->safeCount(fn () => BootcampRegistration::where('status', 'rejected')->count()),
            'revenue'  => $this->safeSum(fn () => BootcampRegistration::where('status', 'approved')->where('currency', 'HTG')->sum('amount')),
            'revenue_usd' => $this->safeSum(fn () => BootcampRegistration::where('status', 'approved')->where('currency', 'USD')->sum('amount')),
        ];

        $byModule = $this->safeQuery(fn () => BootcampRegistration::where('status', 'approved')
            ->selectRaw('module_choice, COUNT(*) as total')
            ->groupBy('module_choice')
            ->pluck('total', 'module_choice')
            ->toArray(), []);

        $modules = BootcampRegistration::$MODULES;

        return view('erp.reports.bootcamp', compact('registrations', 'stats', 'byModule', 'modules', 'status', 'module'));
    }

    public function reservations(Request $request)
    {
        $year   = (int) $request->get('year', now()->year);
        $status = $request->get('status');

        $query = Booking::with(['client', 'space'])->orderByDesc('start_datetime');
        if ($year) {
            $query->whereYear('start_datetime', $year);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->get();

        $stats = [
            'total'    => $this->safeCount(fn () => Booking::whereYear('start_datetime', $year)->count()),
            'confirmed' => $this->safeCount(fn () => Booking::whereYear('start_datetime', $year)->where('status', 'confirmed')->count()),
            'pending'  => $this->safeCount(fn () => Booking::whereYear('start_datetime', $year)->where('status', 'pending')->count()),
            'revenue'  => $this->safeSum(fn () => Booking::whereYear('start_datetime', $year)->where('payment_status', 'paid')->sum('total_price')),
        ];

        $availableYears = $this->availableYears();

        return view('erp.reports.reservations', compact('bookings', 'stats', 'year', 'status', 'availableYears'));
    }

    public function pos(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', 0);

        $query = PosTransaction::with(['client', 'session'])->orderByDesc('created_at');
        if ($year) {
            $query->whereYear('created_at', $year);
        }
        if ($month) {
            $query->whereMonth('created_at', $month);
        }

        $transactions = $query->get();

        $stats = [
            'total'       => $transactions->count(),
            'completed'   => $transactions->where('status', 'completed')->count(),
            'revenue'     => $transactions->where('status', 'completed')->sum('total'),
            'avg_ticket'  => $transactions->where('status', 'completed')->avg('total') ?? 0,
        ];

        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month'  => now()->month($m)->format('M'),
                'amount' => $this->safeSum(fn () => PosTransaction::where('status', 'completed')->whereYear('created_at', $year)->whereMonth('created_at', $m)->sum('total')),
            ];
        }

        $availableYears = $this->availableYears();
        $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        return view('erp.reports.pos', compact('transactions', 'stats', 'monthly', 'year', 'month', 'availableYears', 'months'));
    }

    public function clients(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $clients = $this->safeQuery(fn () => Client::with('assignedUser')
            ->withCount(['invoices', 'bookings', 'contracts'])
            ->withSum(['invoices as total_invoiced' => fn ($q) => $q->where('status', 'paid')], 'total_amount')
            ->orderByDesc('created_at')
            ->get(), collect());

        $stats = [
            'total'    => $this->safeCount(fn () => Client::count()),
            'new_year' => $this->safeCount(fn () => Client::whereYear('created_at', $year)->count()),
            'active'   => $this->safeCount(fn () => Client::where('status', 'active')->count()),
        ];

        $byType = $this->safeQuery(fn () => Client::selectRaw('type, COUNT(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')
            ->toArray(), []);

        $availableYears = $this->availableYears();

        return view('erp.reports.clients', compact('clients', 'stats', 'byType', 'year', 'availableYears'));
    }

    // ── PDF Exports ─────────────────────────────────────────────────────────

    public function pdfFormations(Request $request)
    {
        $year       = (int) $request->get('year', now()->year);
        $formations = Formation::withCount('inscriptions')->whereYear('date_debut', $year)->orderByDesc('date_debut')->get();
        $totalInscriptions = $this->safeCount(fn () => Inscription::whereHas('formation', fn ($q) => $q->whereYear('date_debut', $year))->count());
        $presents = $this->safeCount(fn () => Inscription::where('present', true)->whereHas('formation', fn ($q) => $q->whereYear('date_debut', $year))->count());

        $pdf = Pdf::loadView('erp.reports.pdf.formations', compact('formations', 'year', 'totalInscriptions', 'presents'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("rapport-formations-{$year}.pdf");
    }

    public function pdfBootcamp(Request $request)
    {
        $status = $request->get('status');
        $query  = BootcampRegistration::latest();
        if ($status) {
            $query->where('status', $status);
        }
        $registrations = $query->get();
        $modules = BootcampRegistration::$MODULES;

        $stats = [
            'total'    => $registrations->count(),
            'approved' => $registrations->where('status', 'approved')->count(),
            'pending'  => $registrations->where('status', 'pending')->count(),
            'rejected' => $registrations->where('status', 'rejected')->count(),
            'revenue'  => $registrations->where('status', 'approved')->where('currency', 'HTG')->sum('amount'),
            'revenue_usd' => $registrations->where('status', 'approved')->where('currency', 'USD')->sum('amount'),
        ];

        $pdf = Pdf::loadView('erp.reports.pdf.bootcamp', compact('registrations', 'stats', 'modules', 'status'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('rapport-bootcamp-ai-2026.pdf');
    }

    public function pdfReservations(Request $request)
    {
        $year   = (int) $request->get('year', now()->year);
        $status = $request->get('status');

        $query = Booking::with(['client', 'space'])->orderByDesc('start_datetime')->whereYear('start_datetime', $year);
        if ($status) {
            $query->where('status', $status);
        }
        $bookings = $query->get();

        $stats = [
            'total'    => $bookings->count(),
            'confirmed' => $bookings->where('status', 'confirmed')->count(),
            'revenue'  => $bookings->where('payment_status', 'paid')->sum('total_price'),
        ];

        $pdf = Pdf::loadView('erp.reports.pdf.reservations', compact('bookings', 'stats', 'year', 'status'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("rapport-reservations-{$year}.pdf");
    }

    public function pdfPos(Request $request)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', 0);

        $query = PosTransaction::with(['client'])->orderByDesc('created_at')->whereYear('created_at', $year);
        if ($month) {
            $query->whereMonth('created_at', $month);
        }
        $transactions = $query->get();

        $stats = [
            'total'    => $transactions->count(),
            'revenue'  => $transactions->where('status', 'completed')->sum('total'),
        ];

        $months = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

        $pdf = Pdf::loadView('erp.reports.pdf.pos', compact('transactions', 'stats', 'year', 'month', 'months'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("rapport-pos-{$year}.pdf");
    }

    public function pdfClients(Request $request)
    {
        $year = (int) $request->get('year', now()->year);

        $clients = $this->safeQuery(fn () => Client::with('assignedUser')
            ->withCount(['invoices', 'bookings'])
            ->withSum(['invoices as total_invoiced' => fn ($q) => $q->where('status', 'paid')], 'total_amount')
            ->orderByDesc('created_at')
            ->get(), collect());

        $stats = [
            'total'  => $clients->count(),
            'active' => $clients->where('status', 'active')->count(),
        ];

        $pdf = Pdf::loadView('erp.reports.pdf.clients', compact('clients', 'stats', 'year'))
            ->setPaper('a4', 'landscape');

        return $pdf->download("rapport-clients-{$year}.pdf");
    }

    public function pdfGlobal(Request $request)
    {
        $year    = (int) $request->get('year', now()->year);
        $stats   = $this->globalStats($year);
        $monthly = $this->monthlyRevenue($year);

        $pdf = Pdf::loadView('erp.reports.pdf.global', compact('stats', 'monthly', 'year'))
            ->setPaper('a4', 'portrait');

        return $pdf->download("rapport-global-govibe-{$year}.pdf");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function globalStats(int $year): array
    {
        return [
            'totalRevenue'        => $this->safeSum(fn () => Invoice::where('status', 'paid')->whereYear('created_at', $year)->sum('total_amount')),
            'totalClients'        => $this->safeCount(fn () => Client::count()),
            'totalProjects'       => $this->safeCount(fn () => Project::count()),
            'pendingInvoices'     => $this->safeCount(fn () => Invoice::where('status', 'sent')->count()),
            'totalFormations'     => $this->safeCount(fn () => Formation::whereYear('date_debut', $year)->count()),
            'totalInscriptions'   => $this->safeCount(fn () => Inscription::whereYear('created_at', $year)->count()),
            'bootcampTotal'       => $this->safeCount(fn () => BootcampRegistration::count()),
            'bootcampApproved'    => $this->safeCount(fn () => BootcampRegistration::where('status', 'approved')->count()),
            'totalBookings'       => $this->safeCount(fn () => Booking::whereYear('created_at', $year)->count()),
            'posRevenue'          => $this->safeSum(fn () => PosTransaction::where('status', 'completed')->whereYear('created_at', $year)->sum('total')),
        ];
    }

    private function monthlyRevenue(int $year): array
    {
        $monthly = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthly[] = [
                'month'  => now()->month($m)->format('M'),
                'amount' => $this->safeSum(fn () => Invoice::where('status', 'paid')->whereYear('created_at', $year)->whereMonth('created_at', $m)->sum('total_amount')),
            ];
        }

        return $monthly;
    }

    private function availableYears(): array
    {
        $years = [];
        for ($y = now()->year; $y >= 2023; $y--) {
            $years[] = $y;
        }

        return $years;
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeSum(callable $fn, float $default = 0): float
    {
        try { return (float) $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, mixed $default = null): mixed
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
