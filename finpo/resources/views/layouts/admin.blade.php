<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Admin' }} — FINPO 2026</title>
    <link rel="icon" href="{{ asset('assets/img/favicon.svg') }}" type="image/svg+xml">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Sora:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="{{ asset('assets/vendor/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/finpo.css') }}" rel="stylesheet">
    <style>
        .fp-admin { display: grid; grid-template-columns: 250px 1fr; min-height: 100vh; }
        .fp-side { background: var(--fp-bg-2); border-right: 1px solid var(--fp-card-border); padding: 1.2rem; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .fp-side a { display: flex; gap: .6rem; align-items: center; padding: .5rem .8rem; border-radius: .6rem; color: var(--fp-text); font-size: .92rem; }
        .fp-side a:hover, .fp-side a.active { background: rgba(232, 185, 49, .12); color: var(--fp-gold); }
        .fp-side .nav-label { font-size: .7rem; text-transform: uppercase; letter-spacing: .16em; color: var(--fp-muted); margin: 1.1rem 0 .3rem .5rem; }
        .fp-main { padding: 1.6rem 2rem; }
        @media (max-width: 991px) { .fp-admin { grid-template-columns: 1fr; } .fp-side { position: static; height: auto; } }
        .table { --bs-table-bg: transparent; }
        .table th { color: var(--fp-muted); font-weight: 600; font-size: .8rem; text-transform: uppercase; letter-spacing: .06em; }
        .table td { vertical-align: middle; }
    </style>
</head>
<body>
<div class="fp-admin">
    <aside class="fp-side">
        <a href="{{ route('admin.dashboard') }}" class="fp-brand fs-4 mb-2 d-inline-block">FIN<span>PO</span> <small class="fs-6 fw-normal fp-muted">Admin</small></a>
        <nav aria-label="Administration">
            <div class="nav-label">Pilotage</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">📊 Tableau de bord</a>
            <a href="{{ route('admin.checkin') }}" class="{{ request()->routeIs('admin.checkin') ? 'active' : '' }}">✅ Check-in</a>
            <div class="nav-label">Billetterie</div>
            <a href="{{ route('admin.tickets.index') }}" class="{{ request()->routeIs('admin.tickets.*') ? 'active' : '' }}">🎟️ Catégories de billets</a>
            <a href="{{ route('admin.registrations.index') }}" class="{{ request()->routeIs('admin.registrations.*') ? 'active' : '' }}">👥 Inscriptions</a>
            <a href="{{ route('admin.coupons.index') }}" class="{{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}">🏷️ Codes promo</a>
            <div class="nav-label">Événement</div>
            <a href="{{ route('admin.speakers.index') }}" class="{{ request()->routeIs('admin.speakers.*') ? 'active' : '' }}">🎤 Intervenants</a>
            <a href="{{ route('admin.sessions.index') }}" class="{{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}">📅 Programme</a>
            <a href="{{ route('admin.partners.index') }}" class="{{ request()->routeIs('admin.partners.*') ? 'active' : '' }}">🤝 Partenaires</a>
            <a href="{{ route('admin.sponsors.index') }}" class="{{ request()->routeIs('admin.sponsors.*') ? 'active' : '' }}">⭐ Sponsors</a>
            <a href="{{ route('admin.exhibitors.index') }}" class="{{ request()->routeIs('admin.exhibitors.*') ? 'active' : '' }}">🏪 Exposants</a>
            <a href="{{ route('admin.booths.index') }}" class="{{ request()->routeIs('admin.booths.*') ? 'active' : '' }}">📐 Stands</a>
            <div class="nav-label">Contenu</div>
            <a href="{{ route('admin.news.index') }}" class="{{ request()->routeIs('admin.news.*') ? 'active' : '' }}">📰 Actualités</a>
            <a href="{{ route('admin.gallery.index') }}" class="{{ request()->routeIs('admin.gallery.*') ? 'active' : '' }}">🖼️ Galerie</a>
            <a href="{{ route('admin.messages.index') }}" class="{{ request()->routeIs('admin.messages.*') ? 'active' : '' }}">✉️ Messages</a>
        </nav>
        <hr style="border-color: var(--fp-card-border);">
        <a href="{{ route('home') }}" target="_blank">🌐 Voir le site</a>
        <form method="post" action="{{ route('admin.logout') }}">
            @csrf
            <button class="btn btn-link p-0 border-0 w-100 text-start" style="color: var(--fp-muted); padding: .5rem .8rem !important;">🚪 Déconnexion ({{ auth()->user()->name }})</button>
        </form>
    </aside>
    <main class="fp-main">
        @if (session('ok'))
            <div class="alert alert-success alert-dismissible fade show">{{ session('ok') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
<script src="{{ asset('assets/vendor/bootstrap.bundle.min.js') }}"></script>
@stack('scripts')
</body>
</html>
