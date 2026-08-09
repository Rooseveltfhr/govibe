@extends('layouts.app', ['title' => __('Programme'), 'description' => __('Agenda interactif de FINPO 2026 : keynotes, panels, ateliers et networking sur trois jours.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Programme'),
    'heading' => __('Agenda interactif'),
    'lead' => __('Filtrez par jour, salle ou thématique. Ajoutez chaque session à votre calendrier en un clic.'),
])

<section class="fp-section-tight">
    <div class="container">
        {{-- Filtres (client-side) --}}
        <div class="fp-card p-3 mb-4 d-flex flex-wrap gap-2 align-items-center fp-no-print reveal">
            <strong class="me-2 small text-uppercase" style="letter-spacing:.1em;">{{ __('Filtres') }}</strong>
            <select id="filter-room" class="form-select form-select-sm w-auto" aria-label="{{ __('Filtrer par salle') }}">
                <option value="">{{ __('Toutes les salles') }}</option>
                @foreach ($rooms as $room)
                    <option value="{{ $room->id }}">{{ $room->name }}</option>
                @endforeach
            </select>
            <select id="filter-track" class="form-select form-select-sm w-auto" aria-label="{{ __('Filtrer par thématique') }}">
                <option value="">{{ __('Toutes les thématiques') }}</option>
                @foreach ($tracks as $track)
                    <option value="{{ $track }}">{{ $track }}</option>
                @endforeach
            </select>
            <select id="filter-type" class="form-select form-select-sm w-auto" aria-label="{{ __('Filtrer par format') }}">
                <option value="">{{ __('Tous les formats') }}</option>
                @foreach (config('finpo.session_types') as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
            <button class="btn btn-fp-outline btn-sm ms-auto" onclick="window.print()">🖨 {{ __('Télécharger en PDF') }}</button>
        </div>

        {{-- Onglets jours --}}
        <ul class="nav nav-pills gap-2 mb-4 fp-no-print" role="tablist">
            @foreach ($days as $date => $daySessions)
                <li class="nav-item" role="presentation">
                    <button class="nav-link {{ $loop->first ? 'active' : '' }} btn-fp-outline"
                            style="{{ $loop->first ? 'background: var(--fp-grad); color:#101a2e; font-weight:700;' : '' }}"
                            data-bs-toggle="pill" data-bs-target="#day{{ $loop->index }}" type="button" role="tab">
                        {{ __('Jour :n', ['n' => $loop->iteration]) }} · {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('d M') }}
                    </button>
                </li>
            @endforeach
        </ul>

        <div class="tab-content">
            @forelse ($days as $date => $daySessions)
                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="day{{ $loop->index }}" role="tabpanel">
                    <h2 class="h4 mb-3 d-none d-print-block">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l d F Y') }}</h2>
                    <div class="d-grid gap-3">
                        @foreach ($daySessions as $session)
                            <article class="fp-card fp-slot session-row"
                                     data-room="{{ $session->room_id }}" data-track="{{ $session->track }}" data-type="{{ $session->type }}">
                                <div>
                                    <div class="fp-slot-time">{{ substr($session->starts_at, 0, 5) }}</div>
                                    <small class="fp-muted">→ {{ substr($session->ends_at, 0, 5) }}</small>
                                </div>
                                <div>
                                    <div class="d-flex flex-wrap gap-2 mb-1 align-items-center">
                                        <span class="fp-chip fp-chip-gold">{{ $session->typeLabel() }}</span>
                                        @if ($session->track)<span class="fp-chip">{{ $session->track }}</span>@endif
                                        @if ($session->room)<span class="fp-muted small">📍 {{ $session->room->name }}</span>@endif
                                    </div>
                                    <h3 class="h5 mb-1">{{ $session->title }}</h3>
                                    @if ($session->description)<p class="fp-muted small mb-2">{{ $session->description }}</p>@endif
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        @foreach ($session->speakers as $speaker)
                                            <a class="fp-chip" href="{{ route('speakers.show', $speaker) }}">🎤 {{ $speaker->name }}</a>
                                        @endforeach
                                        <a class="btn btn-sm btn-fp-outline py-1 fp-no-print" href="{{ route('programme.ics', $session) }}">📅 {{ __('Ajouter au calendrier') }}</a>
                                        @php
                                            $tzConfig = config('finpo.timezone');
                                            $gcalStart = \Illuminate\Support\Carbon::parse($session->day->toDateString().' '.$session->starts_at, $tzConfig)->utc()->format('Ymd\THis\Z');
                                            $gcalEnd = \Illuminate\Support\Carbon::parse($session->day->toDateString().' '.$session->ends_at, $tzConfig)->utc()->format('Ymd\THis\Z');
                                        @endphp
                                        <a class="btn btn-sm btn-fp-outline py-1 fp-no-print" target="_blank" rel="noopener"
                                           href="https://calendar.google.com/calendar/render?action=TEMPLATE&text={{ urlencode($session->title.' — FINPO 2026') }}&dates={{ $gcalStart }}/{{ $gcalEnd }}&location={{ urlencode(config('finpo.venue.name')) }}">
                                            G {{ __('Google Calendar') }}
                                        </a>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="fp-muted">{{ __('Le programme détaillé sera publié prochainement.') }}</p>
            @endforelse
        </div>
    </div>
</section>

@push('scripts')
<script>
(function () {
    const selects = ['filter-room', 'filter-track', 'filter-type'].map((id) => document.getElementById(id));
    const attrs = ['room', 'track', 'type'];
    const apply = () => {
        document.querySelectorAll('.session-row').forEach((row) => {
            const visible = selects.every((sel, i) => !sel.value || row.dataset[attrs[i]] === sel.value);
            row.style.display = visible ? '' : 'none';
        });
    };
    selects.forEach((sel) => sel && sel.addEventListener('change', apply));
})();
</script>
@endpush
@endsection
