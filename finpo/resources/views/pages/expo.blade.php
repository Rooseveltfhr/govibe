@extends('layouts.app', ['title' => __('Expo'), 'description' => __('Annuaire interactif des exposants de FINPO 2026 : entreprises, institutions et innovations.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Expo'),
    'heading' => __('Annuaire des exposants'),
    'lead' => __('Découvrez les entreprises et institutions qui exposent leurs produits, services et innovations.'),
])

<section class="fp-section-tight">
    <div class="container">
        <form method="get" class="fp-card p-3 d-flex flex-wrap gap-2 mb-4 reveal" role="search">
            <label class="visually-hidden" for="expo-q">{{ __('Rechercher un exposant') }}</label>
            <input id="expo-q" type="search" name="q" value="{{ request('q') }}" class="form-control w-auto flex-grow-1"
                   placeholder="🔍 {{ __('Rechercher un exposant…') }}">
            <select name="secteur" class="form-select w-auto" aria-label="{{ __('Filtrer par secteur') }}">
                <option value="">{{ __('Tous les secteurs') }}</option>
                @foreach ($sectors as $sector)
                    <option value="{{ $sector }}" @selected(request('secteur') === $sector)>{{ $sector }}</option>
                @endforeach
            </select>
            <button class="btn btn-fp-primary">{{ __('Filtrer') }}</button>
        </form>

        <div class="row g-4">
            @forelse ($exhibitors as $exhibitor)
                <div class="col-md-6 col-xl-4 reveal">
                    <a href="{{ route('expo.show', $exhibitor) }}" class="d-block h-100" style="color: inherit;">
                        <article class="fp-card h-100 p-4 d-flex gap-3">
                            <img src="{{ $exhibitor->logo_url ?: 'https://placehold.co/160x160/101a2e/9fb3d1?text='.urlencode($exhibitor->company) }}"
                                 alt="" width="72" height="72" class="rounded-3 flex-shrink-0" style="object-fit: cover;" loading="lazy">
                            <div>
                                <div class="d-flex flex-wrap gap-2 mb-1">
                                    @if ($exhibitor->featured)<span class="fp-chip fp-chip-gold">★ {{ __('À la une') }}</span>@endif
                                    @if ($exhibitor->booth)<span class="fp-chip">{{ __('Stand') }} {{ $exhibitor->booth->code }}</span>@endif
                                </div>
                                <h2 class="h5 mb-1">{{ $exhibitor->company }}</h2>
                                <p class="fp-muted small mb-0">{{ $exhibitor->sector }}</p>
                            </div>
                        </article>
                    </a>
                </div>
            @empty
                <p class="fp-muted">{{ __('Aucun exposant trouvé pour ces critères.') }}</p>
            @endforelse
        </div>

        <div class="fp-card p-4 mt-5 text-center reveal">
            <h2 class="h4">{{ __('Votre entreprise mérite d\'être ici') }}</h2>
            <p class="fp-muted mb-3">{{ __('Réservez votre stand et présentez vos solutions à 3 000+ décideurs.') }}</p>
            <a href="{{ route('exhibitors') }}" class="btn btn-fp-primary">{{ __('Devenir exposant') }}</a>
        </div>
    </div>
</section>
@endsection
