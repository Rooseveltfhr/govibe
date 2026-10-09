<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Exhibitor;
use App\Models\Partner;
use App\Models\Registration;
use App\Models\Sponsor;
use App\Models\TicketCategory;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $registrations = Registration::where('status', '!=', 'cancelled');

        $byDay = Registration::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get()
            ->groupBy(fn ($r) => $r->created_at->toDateString())
            ->map->count();

        $days = collect(range(13, 0))->map(fn ($i) => now()->subDays($i)->toDateString());

        return view('admin.dashboard', [
            'totalRegistrations' => (clone $registrations)->count(),
            'paidRegistrations'  => (clone $registrations)->whereIn('payment_status', ['paid', 'free'])->count(),
            'checkedIn'          => (clone $registrations)->whereNotNull('checked_in_at')->count(),
            'revenue'            => (clone $registrations)->where('payment_status', 'paid')->sum('amount'),
            'pendingRevenue'     => (clone $registrations)->where('payment_status', 'pending')->sum('amount'),
            'pendingPartners'    => Partner::where('status', 'pending')->count(),
            'pendingSponsors'    => Sponsor::where('status', 'pending')->count(),
            'pendingExhibitors'  => Exhibitor::where('status', 'pending')->count(),
            'unreadMessages'     => ContactMessage::count(),
            'byCategory'         => TicketCategory::withCount(['registrations' => fn ($q) => $q->where('status', '!=', 'cancelled')])->orderBy('sort')->get(),
            'chartDays'          => $days,
            'chartValues'        => $days->map(fn ($d) => $byDay->get($d, 0)),
            'latest'             => Registration::with('category')->latest()->take(8)->get(),
        ]);
    }
}
