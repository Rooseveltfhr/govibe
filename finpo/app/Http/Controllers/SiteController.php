<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\Exhibitor;
use App\Models\GalleryItem;
use App\Models\NewsletterSubscriber;
use App\Models\NewsPost;
use App\Models\Partner;
use App\Models\ProgramSession;
use App\Models\Registration;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SiteController extends Controller
{
    public function home()
    {
        return view('pages.home', [
            'speakers'   => Speaker::where('active', true)->where('featured', true)->orderBy('sort')->take(8)->get(),
            'sponsors'   => Sponsor::where('status', 'approved')->orderBy('sort')->get()->groupBy('level'),
            'partners'   => Partner::where('status', 'approved')->orderBy('sort')->take(12)->get(),
            'posts'      => NewsPost::published()->latest('published_at')->take(3)->get(),
            'sessions'   => ProgramSession::where('active', true)->where('featured', true)->orderBy('day')->orderBy('starts_at')->take(4)->get(),
            'categories' => TicketCategory::where('active', true)->orderBy('sort')->take(4)->get(),
            'stats'      => $this->stats(),
        ]);
    }

    public function about()
    {
        return view('pages.about', ['stats' => $this->stats()]);
    }

    public function forum()
    {
        return view('pages.forum', [
            'sessions' => ProgramSession::where('active', true)
                ->whereIn('type', ['keynote', 'panel'])
                ->orderBy('day')->orderBy('starts_at')->with(['room', 'speakers'])->get(),
        ]);
    }

    public function expo(Request $request)
    {
        $query = Exhibitor::where('status', 'approved')->with('booth');

        if ($sector = $request->query('secteur')) {
            $query->where('sector', $sector);
        }
        if ($q = trim((string) $request->query('q'))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('company', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return view('pages.expo', [
            'exhibitors' => $query->orderByDesc('featured')->orderBy('sort')->get(),
            'sectors'    => Exhibitor::where('status', 'approved')->whereNotNull('sector')
                ->distinct()->orderBy('sector')->pluck('sector'),
        ]);
    }

    public function exhibitor(Exhibitor $exhibitor)
    {
        abort_unless($exhibitor->status === 'approved', 404);

        return view('pages.expo-show', ['exhibitor' => $exhibitor->load('booth')]);
    }

    public function programme(Request $request)
    {
        $sessions = ProgramSession::where('active', true)
            ->with(['room', 'speakers'])
            ->orderBy('day')->orderBy('starts_at')->get();

        return view('pages.programme', [
            'days'   => $sessions->groupBy(fn ($s) => $s->day->toDateString()),
            'rooms'  => \App\Models\Room::orderBy('sort')->get(),
            'tracks' => $sessions->pluck('track')->filter()->unique()->values(),
        ]);
    }

    public function sessionIcs(ProgramSession $session)
    {
        $tz = config('finpo.timezone');
        $start = Carbon::parse($session->day->toDateString().' '.$session->starts_at, $tz);
        $end   = Carbon::parse($session->day->toDateString().' '.$session->ends_at, $tz);

        $ics = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//FINPO 2026//FR',
            'BEGIN:VEVENT',
            'UID:finpo-session-'.$session->id.'@finpo.ht',
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$start->clone()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$end->clone()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.str_replace([',', ';'], ['\,', '\;'], $session->title),
            'LOCATION:'.str_replace([',', ';'], ['\,', '\;'], ($session->room?->name ? $session->room->name.' — ' : '').config('finpo.venue.name')),
            'END:VEVENT',
            'END:VCALENDAR',
        ]);

        return response($ics, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="finpo-session-'.$session->id.'.ics"',
        ]);
    }

    public function speakers(Request $request)
    {
        $query = Speaker::where('active', true);

        if ($cat = $request->query('categorie')) {
            $query->where('category', $cat);
        }

        return view('pages.speakers', [
            'speakers' => $query->orderByDesc('featured')->orderBy('sort')->orderBy('name')->get(),
            'current'  => $request->query('categorie'),
        ]);
    }

    public function speaker(Speaker $speaker)
    {
        abort_unless($speaker->active, 404);

        return view('pages.speaker-show', [
            'speaker' => $speaker->load(['sessions.room']),
            'others'  => Speaker::where('active', true)->where('id', '!=', $speaker->id)->inRandomOrder()->take(4)->get(),
        ]);
    }

    public function partners()
    {
        return view('pages.partners', [
            'partners' => Partner::where('status', 'approved')->orderBy('sort')->get()->groupBy('category'),
        ]);
    }

    public function partnerApply(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:190',
            'category'      => 'required|string|in:'.implode(',', array_keys(config('finpo.partner_categories'))),
            'website'       => 'nullable|url|max:190',
            'contact_name'  => 'required|string|max:190',
            'contact_email' => 'required|email|max:190',
            'contact_phone' => 'nullable|string|max:60',
            'message'       => 'nullable|string|max:3000',
            'company'       => 'nullable|string|max:60', // honeypot anti-spam
        ]);

        if (! empty($data['company'])) {
            return back()->with('ok', __('Merci ! Votre candidature de partenariat a bien été reçue.'));
        }
        unset($data['company']);

        Partner::create($data + ['status' => 'pending']);

        return back()->with('ok', __('Merci ! Votre candidature de partenariat a bien été reçue. Notre équipe vous contactera sous 72 heures.'));
    }

    public function sponsors()
    {
        return view('pages.sponsors', [
            'sponsors' => Sponsor::where('status', 'approved')->orderBy('sort')->get()->groupBy('level'),
        ]);
    }

    public function sponsorApply(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:190',
            'level'         => 'required|string|in:'.implode(',', array_keys(config('finpo.sponsor_levels'))),
            'website'       => 'nullable|url|max:190',
            'contact_name'  => 'required|string|max:190',
            'contact_email' => 'required|email|max:190',
            'contact_phone' => 'nullable|string|max:60',
            'message'       => 'nullable|string|max:3000',
            'company'       => 'nullable|string|max:60', // honeypot
        ]);

        if (! empty($data['company'])) {
            return back()->with('ok', __('Merci ! Votre demande de sponsoring a bien été reçue.'));
        }
        unset($data['company']);

        Sponsor::create($data + ['status' => 'pending']);

        return back()->with('ok', __('Merci ! Votre demande de sponsoring a bien été reçue. Notre équipe commerciale vous contactera rapidement.'));
    }

    public function exhibitors()
    {
        return view('pages.exhibitors', [
            'booths' => \App\Models\Booth::orderBy('zone')->orderBy('code')->get()->groupBy('zone'),
        ]);
    }

    public function exhibitorApply(Request $request)
    {
        $data = $request->validate([
            'company'       => 'required|string|max:190',
            'sector'        => 'nullable|string|max:190',
            'website'       => 'nullable|url|max:190',
            'booth_id'      => 'nullable|integer|exists:booths,id',
            'contact_name'  => 'required|string|max:190',
            'contact_email' => 'required|email|max:190',
            'contact_phone' => 'nullable|string|max:60',
            'description'   => 'nullable|string|max:3000',
            'website_hp'    => 'nullable|string|max:60', // honeypot
        ]);

        if (! empty($data['website_hp'])) {
            return back()->with('ok', __('Merci ! Votre demande de stand a bien été reçue.'));
        }
        unset($data['website_hp']);

        $boothId = $data['booth_id'] ?? null;
        unset($data['booth_id']);

        $exhibitor = Exhibitor::create($data + [
            'slug'   => \Illuminate\Support\Str::slug($data['company']).'-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(5)),
            'status' => 'pending',
        ]);

        if ($boothId) {
            $booth = \App\Models\Booth::find($boothId);
            if ($booth && $booth->status === 'available') {
                $booth->update(['status' => 'reserved']);
                $exhibitor->update(['booth_id' => $booth->id]);
            }
        }

        return back()->with('ok', __('Merci ! Votre demande de stand a bien été reçue. Notre équipe expo vous contactera pour finaliser la réservation.'));
    }

    public function networking()
    {
        return view('pages.networking');
    }

    public function awards()
    {
        return view('pages.awards');
    }

    public function media()
    {
        return view('pages.media', [
            'posts' => NewsPost::published()->where('tag', 'Communiqué')->latest('published_at')->take(6)->get(),
        ]);
    }

    public function gallery(Request $request)
    {
        $query = GalleryItem::orderBy('sort')->orderByDesc('id');

        if ($edition = $request->query('edition')) {
            $query->where('edition', $edition);
        }

        return view('pages.gallery', [
            'items'    => $query->get(),
            'editions' => GalleryItem::distinct()->orderByDesc('edition')->pluck('edition'),
        ]);
    }

    public function news()
    {
        return view('pages.news', [
            'posts' => NewsPost::published()->latest('published_at')->paginate(9),
        ]);
    }

    public function newsShow(NewsPost $post)
    {
        abort_unless($post->published_at && $post->published_at->isPast(), 404);

        return view('pages.news-show', [
            'post'   => $post,
            'others' => NewsPost::published()->where('id', '!=', $post->id)->latest('published_at')->take(3)->get(),
        ]);
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function contactSubmit(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:190',
            'email'   => 'required|email|max:190',
            'phone'   => 'nullable|string|max:60',
            'subject' => 'nullable|string|max:190',
            'message' => 'required|string|max:5000',
            'company' => 'nullable|string|max:60', // honeypot
        ]);

        if (empty($data['company'])) {
            unset($data['company']);
            ContactMessage::create($data);
        }

        return back()->with('ok', __('Message envoyé ! Notre équipe vous répondra dans les plus brefs délais.'));
    }

    public function newsletter(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:190']);

        NewsletterSubscriber::firstOrCreate(['email' => strtolower($data['email'])]);

        return back()->with('ok', __('Merci ! Vous êtes maintenant abonné(e) aux actualités FINPO.'));
    }

    /** Statistiques attendues + compteurs réels. */
    private function stats(): array
    {
        return [
            'participants'  => 3000,
            'institutions'  => 150,
            'ngos'          => 60,
            'companies'     => 120,
            'government'    => 45,
            'universities'  => 25,
            'international' => 30,
            'countries'     => 12,
            'speakers'      => max(Speaker::where('active', true)->count(), 80),
            'sponsors'      => max(Sponsor::where('status', 'approved')->count(), 20),
            'partners'      => max(Partner::where('status', 'approved')->count(), 40),
            'registered'    => Registration::where('status', '!=', 'cancelled')->count(),
        ];
    }
}
