@extends('layouts.app', ['title' => __('Intervenants'), 'description' => __('Découvrez les intervenants de FINPO 2026 : gouvernement, secteur privé, ONG et organisations internationales.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Intervenants'),
    'heading' => __('Les voix qui comptent'),
    'lead' => __('Décideurs publics, capitaines d\'industrie, innovateurs et partenaires internationaux partagent leur vision.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="d-flex flex-wrap gap-2 mb-4 reveal" role="group" aria-label="{{ __('Filtrer par catégorie') }}">
            <a href="{{ route('speakers') }}" class="btn btn-sm {{ $current ? 'btn-fp-outline' : 'btn-fp-primary' }}">{{ __('Tous') }}</a>
            @foreach (config('finpo.speaker_categories') as $key => $label)
                <a href="{{ route('speakers', ['categorie' => $key]) }}"
                   class="btn btn-sm {{ $current === $key ? 'btn-fp-primary' : 'btn-fp-outline' }}">{{ $label }}</a>
            @endforeach
        </div>

        <div class="row g-4">
            @forelse ($speakers as $speaker)
                <div class="col-6 col-md-4 col-xl-3 reveal">
                    @include('partials.speaker-card', ['speaker' => $speaker])
                </div>
            @empty
                <p class="fp-muted">{{ __('Aucun intervenant dans cette catégorie pour le moment.') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
