@extends('layouts.app', ['title' => __('Le Forum'), 'description' => __('Keynotes et panels de haut niveau : le cœur intellectuel de FINPO 2026.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Le Forum'),
    'heading' => __('Trois jours de dialogue au sommet'),
    'lead' => __('Keynotes, panels sectoriels et débats stratégiques réunissant les plus hauts décideurs publics, privés et internationaux.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-3 mb-5 reveal">
            @foreach ([['🏛️', 'Gouvernance & administration', 'Modernisation de l\'État et services publics numériques'], ['💰', 'Finance & investissement', 'Financement du développement et climat des affaires'], ['💡', 'Tech & innovation', 'Transformation numérique et écosystème startup'], ['🌱', 'Développement durable', 'Environnement, agriculture, santé et éducation']] as [$icon, $theme, $description])
                <div class="col-md-6 col-xl-3">
                    <div class="fp-card p-4 h-100">
                        <span class="fs-2">{{ $icon }}</span>
                        <h2 class="h5 mt-2">{{ __($theme) }}</h2>
                        <p class="fp-muted small mb-0">{{ __($description) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <h2 class="h1 mb-4 reveal">{{ __('Keynotes & panels') }}</h2>
        <div class="d-grid gap-3">
            @forelse ($sessions as $session)
                <article class="fp-card fp-slot reveal">
                    <div>
                        <div class="fp-slot-time">{{ substr($session->starts_at, 0, 5) }}–{{ substr($session->ends_at, 0, 5) }}</div>
                        <small class="fp-muted">{{ $session->day->translatedFormat('d F') }}</small>
                    </div>
                    <div>
                        <div class="d-flex flex-wrap gap-2 mb-1">
                            <span class="fp-chip fp-chip-gold">{{ $session->typeLabel() }}</span>
                            @if ($session->track)<span class="fp-chip">{{ $session->track }}</span>@endif
                            @if ($session->room)<span class="fp-muted small align-self-center">📍 {{ $session->room->name }}</span>@endif
                        </div>
                        <h3 class="h5 mb-1">{{ $session->title }}</h3>
                        <p class="fp-muted small mb-2">{{ $session->description }}</p>
                        @if ($session->speakers->isNotEmpty())
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($session->speakers as $speaker)
                                    <a class="fp-chip" href="{{ route('speakers.show', $speaker) }}">🎤 {{ $speaker->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="fp-muted">{{ __('Le programme du forum sera bientôt dévoilé.') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
