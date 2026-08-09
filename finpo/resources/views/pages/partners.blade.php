@extends('layouts.app', ['title' => __('Partenaires'), 'description' => __('Les partenaires institutionnels, internationaux et médias de FINPO 2026 — devenez partenaire.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Partenaires'),
    'heading' => __('Ensemble, plus loin'),
    'lead' => __('Institutions publiques, organisations internationales, académiques et médias : ils rendent FINPO possible.'),
])

<section class="fp-section-tight">
    <div class="container">
        @forelse ($partners as $category => $group)
            <div class="mb-5 reveal">
                <h2 class="h4 mb-3">{{ config('finpo.partner_categories.'.$category, ucfirst($category)) }}</h2>
                <div class="row g-3">
                    @foreach ($group as $partner)
                        <div class="col-6 col-md-4 col-lg-3">
                            <a class="fp-logo-tile h-100" href="{{ $partner->website ?: '#' }}" target="_blank" rel="noopener" title="{{ $partner->name }}">
                                <img src="{{ $partner->logo_url ?: 'https://placehold.co/320x120/101a2e/9fb3d1?text='.urlencode($partner->name) }}" alt="{{ $partner->name }}" loading="lazy">
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="fp-muted">{{ __('Les partenaires de l\'édition 2026 seront annoncés prochainement.') }}</p>
        @endforelse
    </div>
</section>

<section class="fp-section" style="background: var(--fp-bg-2);" id="devenir-partenaire">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5 reveal">
                <span class="fp-kicker mb-2">{{ __('Devenir partenaire') }}</span>
                <h2 class="h1 mb-3">{{ __('Pourquoi s\'associer à FINPO ?') }}</h2>
                <div class="d-grid gap-3">
                    @foreach ([
                        ['Visibilité nationale', 'Votre logo sur tous les supports : site, badges, scène, médias.'],
                        ['Accès privilégié', 'Invitations VIP, prises de parole et espaces dédiés.'],
                        ['Impact mesurable', 'Rapport post-événement : audiences, retombées et contacts générés.'],
                        ['Réseau qualifié', 'Accès à l\'annuaire des participants et au matchmaking B2B.'],
                    ] as [$benefit, $description])
                        <div class="fp-card p-3">
                            <strong>{{ __($benefit) }}</strong>
                            <p class="fp-muted small mb-0">{{ __($description) }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="fp-muted small mt-3 mb-1"><strong>{{ __('Conditions') }} :</strong> {{ __('être une organisation légalement constituée, adhérer à la charte FINPO et contribuer (financièrement ou en nature) à la réussite de l\'événement.') }}</p>
                <a class="btn btn-fp-outline btn-sm mt-2" href="{{ config('finpo.brochure_url') }}">⬇ {{ __('Télécharger la proposition de partenariat') }}</a>
            </div>
            <div class="col-lg-7 reveal">
                <div class="fp-card p-4">
                    <h3 class="h4 mb-3">{{ __('Candidature de partenariat') }}</h3>
                    <form method="post" action="{{ route('partners.apply') }}" class="row g-3">
                        @csrf
                        <input type="text" name="company" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="col-md-6">
                            <label class="form-label" for="p-name">{{ __('Organisation') }} *</label>
                            <input id="p-name" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-category">{{ __('Catégorie') }} *</label>
                            <select id="p-category" name="category" class="form-select" required>
                                @foreach (config('finpo.partner_categories') as $key => $label)
                                    <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-contact">{{ __('Personne de contact') }} *</label>
                            <input id="p-contact" name="contact_name" class="form-control" required value="{{ old('contact_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-email">{{ __('Email') }} *</label>
                            <input id="p-email" type="email" name="contact_email" class="form-control" required value="{{ old('contact_email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-phone">{{ __('Téléphone') }}</label>
                            <input id="p-phone" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="p-website">{{ __('Site web') }}</label>
                            <input id="p-website" type="url" name="website" class="form-control" placeholder="https://" value="{{ old('website') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="p-message">{{ __('Votre proposition') }}</label>
                            <textarea id="p-message" name="message" rows="4" class="form-control" placeholder="{{ __('Décrivez le partenariat envisagé…') }}">{{ old('message') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-fp-primary">{{ __('Envoyer la candidature') }}</button>
                            <p class="fp-muted small mt-2 mb-0">{{ __('Workflow d\'approbation : votre demande est étudiée par le comité partenariats sous 72 h.') }}</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
