@extends('layouts.app', ['description' => config('finpo.tagline')])

@section('content')

{{-- ================================ HERO ================================ --}}
<header class="fp-hero">
    <div class="fp-hero-media" aria-hidden="true">
        <img src="https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=2000&q=80"
             alt="" fetchpriority="high">
    </div>
    <div class="fp-hero-overlay" aria-hidden="true"></div>
    <div class="container position-relative">
        <div class="row">
            <div class="col-lg-9">
                <span class="fp-hero-badge mb-4">
                    📅 {{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'))->translatedFormat('d') }}–{{ \Illuminate\Support\Carbon::parse(config('finpo.ends_at'))->translatedFormat('d F Y') }}
                    · 📍 {{ config('finpo.venue.city') }}, {{ config('finpo.venue.country') }}
                </span>
                <h1 class="mb-3">
                    FINPO <span class="fp-gradient-text">2026</span><br>
                    <span class="fp-rotator" data-words='@json(config('finpo.hero_words'))'>Forum</span>
                </h1>
                <p class="fw-semibold text-uppercase mb-2" style="letter-spacing:.14em; color:#c6d3e8;">{{ config('finpo.subtitle') }}</p>
                <p class="fp-hero-sub mb-4">{{ config('finpo.tagline') }}</p>

                <div class="d-flex flex-wrap gap-3 mb-5">
                    <a href="{{ route('register') }}" class="btn btn-fp-primary btn-lg">{{ __('S\'inscrire maintenant') }}</a>
                    <a href="{{ route('sponsors') }}" class="btn btn-fp-outline">{{ __('Devenir sponsor') }}</a>
                    <a href="{{ route('partners') }}" class="btn btn-fp-outline">{{ __('Devenir partenaire') }}</a>
                    <a href="{{ route('exhibitors') }}" class="btn btn-fp-outline">{{ __('Devenir exposant') }}</a>
                </div>

                <div class="fp-countdown mb-4" data-countdown="{{ \Illuminate\Support\Carbon::parse(config('finpo.starts_at'), config('finpo.timezone'))->toIso8601String() }}" role="timer" aria-label="{{ __('Compte à rebours avant l\'événement') }}">
                    <div class="fp-count-cell"><b data-cd="d">00</b><span>{{ __('Jours') }}</span></div>
                    <div class="fp-count-cell"><b data-cd="h">00</b><span>{{ __('Heures') }}</span></div>
                    <div class="fp-count-cell"><b data-cd="m">00</b><span>{{ __('Minutes') }}</span></div>
                    <div class="fp-count-cell"><b data-cd="s">00</b><span>{{ __('Secondes') }}</span></div>
                </div>

                <div class="d-flex flex-wrap gap-4 small">
                    <a href="{{ config('finpo.previous_edition_video') }}" target="_blank" rel="noopener" class="text-white-50">
                        ▶ {{ __('Revoir l\'édition précédente') }}
                    </a>
                    <a href="{{ config('finpo.brochure_url') }}" class="text-white-50">
                        ⬇ {{ __('Télécharger la brochure') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>

{{-- ============================ CHIFFRES CLÉS ============================ --}}
<section class="fp-section-tight" aria-label="{{ __('Chiffres clés') }}">
    <div class="container">
        <div class="row g-3 fp-stat-row reveal">
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['participants'] }}" data-suffix="+">0</b><span>{{ __('Participants attendus') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['institutions'] }}" data-suffix="+">0</b><span>{{ __('Institutions') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['speakers'] }}" data-suffix="+">0</b><span>{{ __('Intervenants') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['countries'] }}">0</b><span>{{ __('Pays représentés') }}</span></div></div>
        </div>
        <div class="row g-3 mt-1 fp-stat-row reveal">
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['companies'] }}" data-suffix="+">0</b><span>{{ __('Entreprises') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['ngos'] }}" data-suffix="+">0</b><span>{{ __('ONG & associations') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['universities'] }}" data-suffix="+">0</b><span>{{ __('Universités') }}</span></div></div>
            <div class="col-6 col-md-3"><div class="fp-card fp-stat"><b data-count="{{ $stats['international'] }}" data-suffix="+">0</b><span>{{ __('Org. internationales') }}</span></div></div>
        </div>
    </div>
</section>

{{-- ============================== À PROPOS ============================== --}}
<section class="fp-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6 reveal">
                <span class="fp-kicker mb-3">{{ __('À propos') }}</span>
                <h2 class="display-6 mb-3">{{ __('Le rendez-vous national des institutions qui construisent Haïti') }}</h2>
                <p class="fp-muted mb-4">
                    {{ __('FINPO réunit pendant trois jours les institutions publiques, les entreprises privées, les ONG, les universités et les organisations internationales autour d\'un objectif commun : bâtir des partenariats concrets pour accélérer le développement d\'Haïti.') }}
                </p>
                <div class="d-grid gap-3 mb-4">
                    <div class="d-flex gap-3"><span class="fs-4">🤝</span><div><strong>{{ __('Connecter') }}</strong><p class="fp-muted mb-0 small">{{ __('Un espace unique de rencontre entre décideurs publics, privés et société civile.') }}</p></div></div>
                    <div class="d-flex gap-3"><span class="fs-4">🏛️</span><div><strong>{{ __('Construire') }}</strong><p class="fp-muted mb-0 small">{{ __('Des partenariats structurants, signés et suivis dans la durée.') }}</p></div></div>
                    <div class="d-flex gap-3"><span class="fs-4">🚀</span><div><strong>{{ __('Accélérer') }}</strong><p class="fp-muted mb-0 small">{{ __('Des projets à impact mesurable pour le développement national.') }}</p></div></div>
                </div>
                <a href="{{ route('about') }}" class="btn btn-fp-outline">{{ __('Découvrir FINPO') }}</a>
            </div>
            <div class="col-lg-6 reveal">
                <div class="row g-3">
                    <div class="col-7"><img class="img-fluid rounded-4 w-100" style="aspect-ratio:4/5; object-fit:cover;" src="https://images.unsplash.com/photo-1475721027785-f74eccf877e2?auto=format&fit=crop&w=800&q=80" alt="{{ __('Conférence plénière') }}" loading="lazy"></div>
                    <div class="col-5 d-grid gap-3">
                        <img class="img-fluid rounded-4 w-100 h-100" style="object-fit:cover;" src="https://images.unsplash.com/photo-1511578314322-379afb476865?auto=format&fit=crop&w=600&q=80" alt="{{ __('Networking') }}" loading="lazy">
                        <img class="img-fluid rounded-4 w-100 h-100" style="object-fit:cover;" src="https://images.unsplash.com/photo-1556761175-b413da4baf72?auto=format&fit=crop&w=600&q=80" alt="{{ __('Rencontres B2B') }}" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ========================= SESSIONS PHARES ========================= --}}
@if ($sessions->isNotEmpty())
<section class="fp-section" style="background: var(--fp-bg-2);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 reveal">
            <div>
                <span class="fp-kicker mb-2">{{ __('Programme') }}</span>
                <h2 class="display-6 mb-0">{{ __('Moments forts') }}</h2>
            </div>
            <a href="{{ route('programme') }}" class="btn btn-fp-outline btn-sm">{{ __('Programme complet') }} →</a>
        </div>
        <div class="row g-4">
            @foreach ($sessions as $session)
                <div class="col-md-6 col-xl-3 reveal">
                    <div class="fp-card p-4 h-100">
                        <span class="fp-chip fp-chip-gold mb-3">{{ $session->typeLabel() }}</span>
                        <h5 class="mb-2">{{ $session->title }}</h5>
                        <p class="fp-muted small mb-0">
                            📅 {{ $session->day->translatedFormat('d F') }} · 🕘 {{ substr($session->starts_at, 0, 5) }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================ INTERVENANTS ============================ --}}
@if ($speakers->isNotEmpty())
<section class="fp-section">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 reveal">
            <div>
                <span class="fp-kicker mb-2">{{ __('Intervenants') }}</span>
                <h2 class="display-6 mb-0">{{ __('Ils prennent la parole') }}</h2>
            </div>
            <a href="{{ route('speakers') }}" class="btn btn-fp-outline btn-sm">{{ __('Tous les intervenants') }} →</a>
        </div>
        <div class="row g-4">
            @foreach ($speakers->take(4) as $speaker)
                <div class="col-6 col-lg-3 reveal">
                    @include('partials.speaker-card', ['speaker' => $speaker])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================== BILLETS ============================== --}}
@if ($categories->isNotEmpty())
<section class="fp-section" style="background: var(--fp-bg-2);">
    <div class="container">
        <div class="text-center mb-5 reveal">
            <span class="fp-kicker mb-2 justify-content-center">{{ __('Billetterie') }}</span>
            <h2 class="display-6">{{ __('Choisissez votre pass') }}</h2>
            <p class="fp-muted">{{ __('Tarifs adaptés à chaque profil : étudiants, professionnels, institutions, VIP…') }}</p>
        </div>
        <div class="row g-4 justify-content-center">
            @foreach ($categories as $category)
                <div class="col-md-6 col-xl-3 reveal">
                    @include('partials.ticket-card', ['category' => $category])
                </div>
            @endforeach
        </div>
        <div class="text-center mt-4 reveal">
            <a href="{{ route('register') }}" class="btn btn-fp-primary">{{ __('Voir tous les billets') }}</a>
        </div>
    </div>
</section>
@endif

{{-- ============================== SPONSORS ============================== --}}
@if ($sponsors->isNotEmpty())
<section class="fp-section-tight">
    <div class="container">
        <div class="text-center mb-4 reveal">
            <span class="fp-kicker mb-2 justify-content-center">{{ __('Ils nous soutiennent') }}</span>
            <h2 class="h1">{{ __('Sponsors') }} & {{ __('Partenaires') }}</h2>
        </div>
    </div>
    <div class="fp-marquee reveal" aria-hidden="true">
        <div class="fp-marquee-track">
            @foreach ([0, 1] as $dup)
                @foreach ($sponsors->flatten() as $sponsor)
                    <img src="{{ $sponsor->logo_url }}" alt="{{ $sponsor->name }}" height="52" style="opacity:.85;" loading="lazy">
                @endforeach
            @endforeach
        </div>
    </div>
    <div class="text-center mt-4">
        <a href="{{ route('sponsors') }}" class="btn btn-fp-outline btn-sm me-2">{{ __('Devenir sponsor') }}</a>
        <a href="{{ route('partners') }}" class="btn btn-fp-outline btn-sm">{{ __('Devenir partenaire') }}</a>
    </div>
</section>
@endif

{{-- ============================== ACTUALITÉS ============================== --}}
@if ($posts->isNotEmpty())
<section class="fp-section" style="background: var(--fp-bg-2);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 reveal">
            <div>
                <span class="fp-kicker mb-2">{{ __('Actualités') }}</span>
                <h2 class="display-6 mb-0">{{ __('Dernières nouvelles') }}</h2>
            </div>
            <a href="{{ route('news') }}" class="btn btn-fp-outline btn-sm">{{ __('Toutes les actualités') }} →</a>
        </div>
        <div class="row g-4">
            @foreach ($posts as $post)
                <div class="col-md-4 reveal">
                    @include('partials.news-card', ['post' => $post])
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- ============================== CTA FINAL ============================== --}}
<section class="fp-section text-center position-relative overflow-hidden">
    <div class="container position-relative reveal">
        <h2 class="display-5 mb-3">{{ __('Prêt à faire partie de') }} <span class="fp-gradient-text">FINPO 2026</span> ?</h2>
        <p class="fp-muted mb-4 mx-auto" style="max-width: 560px;">{{ __('Rejoignez les décideurs qui façonnent l\'avenir institutionnel et économique d\'Haïti.') }}</p>
        <div class="d-flex flex-wrap gap-3 justify-content-center">
            <a href="{{ route('register') }}" class="btn btn-fp-primary btn-lg">{{ __('Réserver mon billet') }}</a>
            <a href="{{ route('contact') }}" class="btn btn-fp-outline">{{ __('Nous contacter') }}</a>
        </div>
    </div>
</section>

@endsection
