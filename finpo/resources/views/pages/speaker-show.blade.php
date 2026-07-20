@extends('layouts.app', ['title' => $speaker->name, 'description' => $speaker->topic ?: $speaker->position, 'image' => $speaker->photo_url])

@section('content')
<section class="fp-page-head">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-md-4 col-lg-3">
                <img src="{{ $speaker->photo_url ?: 'https://placehold.co/600x640/101a2e/9fb3d1?text='.urlencode($speaker->name) }}"
                     alt="{{ $speaker->name }}" class="img-fluid rounded-4 w-100" style="aspect-ratio: 1/1.05; object-fit: cover;">
            </div>
            <div class="col-md-8 col-lg-9">
                <span class="fp-chip mb-2">{{ $speaker->categoryLabel() }}</span>
                <h1 class="display-6 mb-1">{{ $speaker->name }}</h1>
                <p class="fp-muted mb-2">{{ $speaker->position }}@if($speaker->institution) — {{ $speaker->institution }}@endif · {{ $speaker->country }}</p>
                @if ($speaker->topic)
                    <p class="h5 fp-gradient-text mb-3">« {{ $speaker->topic }} »</p>
                @endif
                <div class="d-flex gap-2">
                    @if ($speaker->linkedin)<a class="fp-social" href="{{ $speaker->linkedin }}" target="_blank" rel="noopener" aria-label="LinkedIn">in</a>@endif
                    @if ($speaker->facebook)<a class="fp-social" href="{{ $speaker->facebook }}" target="_blank" rel="noopener" aria-label="Facebook">f</a>@endif
                    @if ($speaker->website)<a class="fp-social" href="{{ $speaker->website }}" target="_blank" rel="noopener" aria-label="{{ __('Site web') }}">🌐</a>@endif
                </div>
            </div>
        </div>
    </div>
</section>

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7">
                <h2 class="h4 mb-3">{{ __('Biographie') }}</h2>
                <p class="fp-muted" style="white-space: pre-line;">{{ $speaker->bio }}</p>
            </div>
            <div class="col-lg-5">
                <h2 class="h4 mb-3">{{ __('Interventions à FINPO 2026') }}</h2>
                <div class="d-grid gap-3">
                    @forelse ($speaker->sessions as $session)
                        <div class="fp-card p-3">
                            <span class="fp-chip fp-chip-gold mb-2">{{ $session->typeLabel() }}</span>
                            <h3 class="h6 mb-1">{{ $session->title }}</h3>
                            <p class="fp-muted small mb-0">
                                📅 {{ $session->day->translatedFormat('d F') }} · {{ substr($session->starts_at, 0, 5) }}–{{ substr($session->ends_at, 0, 5) }}
                                @if ($session->room) · 📍 {{ $session->room->name }}@endif
                            </p>
                        </div>
                    @empty
                        <p class="fp-muted">{{ __('Sessions bientôt annoncées.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($others->isNotEmpty())
            <h2 class="h4 mt-5 mb-3">{{ __('Autres intervenants') }}</h2>
            <div class="row g-4">
                @foreach ($others as $other)
                    <div class="col-6 col-md-3">
                        @include('partials.speaker-card', ['speaker' => $other])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endsection
