<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $metaTitle = trim(($title ?? '') !== '' ? ($title.' — '.config('finpo.name')) : (config('finpo.name').' — '.config('finpo.subtitle')));
        $metaDescription = $description ?? config('finpo.tagline');
        $metaImage = $image ?? 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1200&q=80';
    @endphp

    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Open Graph / Twitter --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('finpo.name') }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ $metaImage }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    {{-- Schema.org --}}
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => config('finpo.name'),
        'description' => config('finpo.tagline'),
        'startDate' => \Illuminate\Support\Carbon::parse(config('finpo.starts_at'), config('finpo.timezone'))->toIso8601String(),
        'endDate' => \Illuminate\Support\Carbon::parse(config('finpo.ends_at'), config('finpo.timezone'))->toIso8601String(),
        'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'location' => [
            '@type' => 'Place',
            'name' => config('finpo.venue.name'),
            'address' => config('finpo.venue.address'),
        ],
        'organizer' => [
            '@type' => 'Organization',
            'name' => config('finpo.organizer.name'),
            'url' => config('finpo.organizer.url'),
        ],
        'image' => $metaImage,
        'url' => url('/'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
    </script>

    {{-- PWA --}}
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#070d1a">
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('assets/img/icon-192.svg') }}">

    {{-- Polices + Bootstrap --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/finpo.css') }}" rel="stylesheet">
    @stack('head')
</head>
<body>
<a class="visually-hidden-focusable" href="#fp-main">{{ __('Aller au contenu principal') }}</a>

{{-- ============================== NAVIGATION ============================== --}}
<nav class="fp-nav navbar navbar-expand-xl" aria-label="{{ __('Navigation principale') }}">
    <div class="container">
        <a class="fp-brand navbar-brand" href="{{ route('home') }}">FIN<span>PO</span> <small class="fw-normal fs-6 fp-muted">2026</small></a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#fpNav"
                aria-controls="fpNav" aria-expanded="false" aria-label="{{ __('Ouvrir le menu') }}">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="fpNav">
            <ul class="navbar-nav mx-auto mb-2 mb-xl-0">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">{{ __('Accueil') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">{{ __('À propos') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('programme') ? 'active' : '' }}" href="{{ route('programme') }}">{{ __('Programme') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('speakers*') ? 'active' : '' }}" href="{{ route('speakers') }}">{{ __('Intervenants') }}</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('expo*') ? 'active' : '' }}" href="{{ route('expo') }}">{{ __('Expo') }}</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ __('Participer') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('register') }}">{{ __('Inscription & billets') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('sponsors') }}">{{ __('Devenir sponsor') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('partners') }}">{{ __('Devenir partenaire') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('exhibitors') }}">{{ __('Devenir exposant') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('networking') }}">{{ __('Networking') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('awards') }}">{{ __('Awards') }}</a></li>
                    </ul>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">{{ __('Découvrir') }}</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('forum') }}">{{ __('Le Forum') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('news') }}">{{ __('Actualités') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('gallery') }}">{{ __('Galerie') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('media') }}">{{ __('Espace médias') }}</a></li>
                        <li><a class="dropdown-item" href="{{ route('certificate.verify') }}">{{ __('Vérifier un certificat') }}</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">{{ __('Contact') }}</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                {{-- Sélecteur de langue --}}
                <div class="dropdown">
                    <button class="btn btn-sm btn-fp-outline dropdown-toggle py-2" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['lang' => 'fr']) }}">🇫🇷 Français</a></li>
                        <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['lang' => 'ht']) }}">🇭🇹 Kreyòl</a></li>
                        <li><a class="dropdown-item" href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}">🇺🇸 English</a></li>
                    </ul>
                </div>
                <button id="fp-theme-toggle" class="btn btn-sm btn-fp-outline py-2" type="button" aria-label="{{ __('Changer de thème') }}">☀️</button>
                <button id="fp-font-toggle" class="btn btn-sm btn-fp-outline py-2" type="button" aria-label="{{ __('Agrandir le texte') }}">A+</button>
                <a href="{{ route('register') }}" class="btn btn-fp-primary btn-sm py-2 px-3">{{ __('S\'inscrire') }}</a>
            </div>
        </div>
    </div>
</nav>

<main id="fp-main">
    {{-- Messages flash --}}
    @if (session('ok') || $errors->any())
        <div class="position-fixed top-0 start-50 translate-middle-x mt-5 pt-4" style="z-index:2000; max-width: min(92vw, 560px);">
            @if (session('ok'))
                <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
                    {{ session('ok') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Fermer') }}"></button>
                </div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger alert-dismissible fade show shadow" role="alert">
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Fermer') }}"></button>
                </div>
            @endif
        </div>
    @endif

    @yield('content')
</main>

{{-- ============================== FOOTER ============================== --}}
<footer class="fp-footer pt-5 pb-4 mt-auto">
    <div class="container">
        <div class="row g-4 pb-4">
            <div class="col-lg-4">
                <a class="fp-brand fs-3" href="{{ route('home') }}">FIN<span>PO</span> 2026</a>
                <p class="fp-muted mt-3 mb-3" style="max-width: 340px;">{{ config('finpo.tagline') }}</p>
                <p class="fp-muted small mb-1">{{ __('Organisé par') }} <strong>{{ config('finpo.organizer.name') }}</strong></p>
                <div class="d-flex gap-2 mt-3">
                    <a class="fp-social" href="{{ config('finpo.social.facebook') }}" target="_blank" rel="noopener" aria-label="Facebook">f</a>
                    <a class="fp-social" href="{{ config('finpo.social.instagram') }}" target="_blank" rel="noopener" aria-label="Instagram">◎</a>
                    <a class="fp-social" href="{{ config('finpo.social.linkedin') }}" target="_blank" rel="noopener" aria-label="LinkedIn">in</a>
                    <a class="fp-social" href="{{ config('finpo.social.x') }}" target="_blank" rel="noopener" aria-label="X">𝕏</a>
                    <a class="fp-social" href="{{ config('finpo.social.youtube') }}" target="_blank" rel="noopener" aria-label="YouTube">▶</a>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="mb-3">{{ __('Événement') }}</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="{{ route('about') }}">{{ __('À propos') }}</a></li>
                    <li><a href="{{ route('programme') }}">{{ __('Programme') }}</a></li>
                    <li><a href="{{ route('speakers') }}">{{ __('Intervenants') }}</a></li>
                    <li><a href="{{ route('expo') }}">{{ __('Expo') }}</a></li>
                    <li><a href="{{ route('awards') }}">{{ __('Awards') }}</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6 class="mb-3">{{ __('Participer') }}</h6>
                <ul class="list-unstyled d-grid gap-2">
                    <li><a href="{{ route('register') }}">{{ __('Inscription') }}</a></li>
                    <li><a href="{{ route('sponsors') }}">{{ __('Sponsors') }}</a></li>
                    <li><a href="{{ route('partners') }}">{{ __('Partenaires') }}</a></li>
                    <li><a href="{{ route('exhibitors') }}">{{ __('Exposants') }}</a></li>
                    <li><a href="{{ route('networking') }}">{{ __('Networking') }}</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="mb-3">{{ __('Contact') }}</h6>
                <ul class="list-unstyled d-grid gap-2 fp-muted">
                    <li>📍 {{ config('finpo.venue.name') }}, {{ config('finpo.venue.city') }}</li>
                    <li>✉️ <a href="mailto:{{ config('finpo.contact.email') }}">{{ config('finpo.contact.email') }}</a></li>
                    <li>📞 <a href="tel:{{ config('finpo.contact.phone') }}">{{ config('finpo.contact.phone') }}</a></li>
                    <li>💬 <a href="https://wa.me/{{ preg_replace('/\D/', '', config('finpo.contact.whatsapp')) }}" target="_blank" rel="noopener">WhatsApp</a></li>
                </ul>
                <form method="post" action="{{ route('newsletter') }}" class="d-flex gap-2 mt-3" style="max-width: 360px;">
                    @csrf
                    <label class="visually-hidden" for="fp-newsletter">{{ __('Votre adresse email') }}</label>
                    <input id="fp-newsletter" type="email" name="email" class="form-control form-control-sm" placeholder="{{ __('Votre email') }}" required>
                    <button class="btn btn-fp-primary btn-sm text-nowrap" type="submit">{{ __('S\'abonner') }}</button>
                </form>
            </div>
        </div>
        <div class="border-top pt-3 d-flex flex-wrap justify-content-between gap-2 small fp-muted" style="border-color: var(--fp-card-border) !important;">
            <span>© {{ date('Y') }} FINPO — {{ config('finpo.organizer.name') }}. {{ __('Tous droits réservés.') }}</span>
            <span><a href="{{ route('admin.login') }}">{{ __('Espace admin') }}</a></span>
        </div>
    </div>
</footer>

<script src="{{ asset('assets/vendor/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/js/finpo.js') }}"></script>
@stack('scripts')
</body>
</html>
