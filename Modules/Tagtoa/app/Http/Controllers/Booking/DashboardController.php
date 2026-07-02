<?php

namespace Modules\Tagtoa\App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Booking\Booking;
use Modules\Tagtoa\App\Models\Booking\BookingPage;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Booking\BookingService;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Locale;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA BOOKING — dashboard propriétaire (CRUD page + prestations + rendez-vous).
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(): View
    {
        $pages = BookingPage::where('tenant_id', Tenant::id())
            ->withCount(['services', 'bookings'])
            ->latest()->paginate(12);

        return view('tagtoa::booking.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa::booking.form', [
            'page'     => new BookingPage(['theme' => 'light', 'accent_color' => '#2cb809', 'currency' => Locale::currencyFor()]),
            'vcards'   => $this->vcards(),
            'payPages' => $this->payPages(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($r = $this->planGuard('booking')) {
            return $r;
        }
        $data = $this->validatePage($request);
        $page = new BookingPage($data);
        $page->tenant_id = Tenant::id();
        $page->alias = $data['alias'] ?: BookingPage::generateAlias($data['name'] ?? 'booking');
        $this->handleUploads($page, $request);
        $page->save();
        $this->syncServices($page, $request);

        return redirect()->route('tagtoa.booking.dashboard.edit', $page->id)
            ->with('success', __('Page de réservation créée. Ajoutez vos prestations.'));
    }

    public function edit(int $id): View
    {
        $page = $this->own($id, ['services']);

        return view('tagtoa::booking.form', [
            'page'     => $page,
            'vcards'   => $this->vcards(),
            'payPages' => $this->payPages(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = $this->own($id);
        $data = $this->validatePage($request, $page->id);
        $data['alias'] = $data['alias'] ?: $page->alias;
        $page->fill($data);
        $this->handleUploads($page, $request);
        $page->save();
        $this->syncServices($page, $request);

        return back()->with('success', __('Page de réservation mise à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->own($id)->delete();

        return redirect()->route('tagtoa.booking.dashboard.index')->with('success', __('Page supprimée.'));
    }

    /* ---------- rendez-vous ---------- */

    public function bookings(int $id): View
    {
        $page = $this->own($id);
        $bookings = $page->bookings()->with('service')->paginate(20);
        $pending = $page->bookings()->where('status', 'pending')->count();

        return view('tagtoa::booking.appointments', compact('page', 'bookings', 'pending'));
    }

    public function setStatus(Request $request, int $bookingId): RedirectResponse
    {
        $booking = $this->ownBooking($bookingId);
        $data = $request->validate(['status' => ['required', Rule::in(Booking::STATUSES)]]);

        if ($data['status'] === 'completed') {
            app(BookingService::class)->markCompleted($booking);
        } else {
            $booking->update(['status' => $data['status']]);
        }

        if (in_array($data['status'], ['completed', 'cancelled', 'confirmed'], true)) {
            app(AuditService::class)->log('booking.'.$data['status'], $booking, $booking->reference);
        }

        return back()->with('success', __('Rendez-vous mis à jour.'));
    }

    protected function ownBooking(int $id): Booking
    {
        return Booking::whereHas('page', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
    }

    /* ---------- helpers ---------- */

    protected function own(int $id, array $with = []): BookingPage
    {
        return BookingPage::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function handleUploads(BookingPage $page, Request $request): void
    {
        if ($request->hasFile('logo')) {
            $page->logo_path = $request->file('logo')->store('tagtoa/booking-logos', 'public');
        }
        if ($request->hasFile('cover')) {
            $page->cover_path = $request->file('cover')->store('tagtoa/booking-covers', 'public');
        }
    }

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        $ownVcardIds = $this->vcards()->pluck('id')->all();
        $ownPayIds   = $this->payPages()->pluck('id')->all();

        return $request->validate([
            'vcard_id'     => ['nullable', 'integer', Rule::in($ownVcardIds)],
            'name'         => ['required', 'string', 'max:160'],
            'alias'        => ['nullable', 'string', 'max:120', 'alpha_dash', 'unique:tagtoa_booking_pages,alias'.($ignoreId ? ','.$ignoreId : '')],
            'tagline'      => ['nullable', 'string', 'max:160'],
            'about'        => ['nullable', 'string', 'max:1000'],
            'currency'     => ['nullable', Rule::in(array_keys((array) config('tagtoa.currencies', [])))],
            'whatsapp'     => ['nullable', 'string', 'max:40'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'email'        => ['nullable', 'email', 'max:160'],
            'address'      => ['nullable', 'string', 'max:200'],
            'pay_page_id'  => ['nullable', 'integer', Rule::in($ownPayIds)],
            'accent_color' => ['nullable', 'string', 'max:16'],
            'theme'        => ['nullable', Rule::in(BookingPage::THEMES)],
            'is_active'    => ['nullable', 'boolean'],
            'logo'         => ['nullable', 'image', 'max:2048'],
            'cover'        => ['nullable', 'image', 'max:4096'],
        ]);
    }

    /** Synchronise les prestations depuis le formulaire répétable (services[]). */
    protected function syncServices(BookingPage $page, Request $request): void
    {
        $services = $request->input('services', []);
        $keep = [];

        DB::transaction(function () use ($page, $services, &$keep) {
            foreach (array_values($services) as $si => $s) {
                if (empty($s['name'])) {
                    continue;
                }
                $attrs = [
                    'name'         => $s['name'],
                    'description'  => $s['description'] ?? null,
                    'duration_min' => max(5, (int) ($s['duration_min'] ?? 30)),
                    'price'        => round((float) ($s['price'] ?? 0), 2),
                    'is_active'    => ! isset($s['is_active']) ? true : (bool) $s['is_active'],
                    'sort'         => $si,
                ];
                $svc = ! empty($s['id']) ? $page->services()->whereKey($s['id'])->first() : null;
                $svc ? $svc->update($attrs) : $svc = $page->services()->create($attrs);
                $keep[] = $svc->id;
            }
            $page->services()->whereNotIn('id', $keep ?: [0])->delete();
        });
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->where('tenant_id', Tenant::id())->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    protected function payPages()
    {
        return PaymentPage::where('tenant_id', Tenant::id())->get(['id', 'title', 'alias']);
    }
}
