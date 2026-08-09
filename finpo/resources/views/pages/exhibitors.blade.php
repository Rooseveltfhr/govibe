@extends('layouts.app', ['title' => __('Devenir exposant'), 'description' => __('Plan des stands, tarifs et réservation en ligne pour exposer à FINPO 2026.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Exposants'),
    'heading' => __('Réservez votre stand'),
    'lead' => __('32 stands répartis en 3 zones au cœur de l\'événement. Choisissez votre emplacement et réservez en ligne.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-3 mb-4 reveal">
            @foreach ([['A', '150 000 HTG', '3x3 m — zone premium, entrée principale'], ['B', '100 000 HTG', '3x3 m — cœur de l\'expo'], ['C', '250 000 HTG', '6x3 m — grands stands, espaces démo']] as [$zone, $price, $description])
                <div class="col-md-4">
                    <div class="fp-card p-4 h-100">
                        <h2 class="h5">{{ __('Zone') }} {{ $zone }}</h2>
                        <p class="fp-price mb-0 fs-3">{{ $price }}</p>
                        <p class="fp-muted small mb-0">{{ __($description) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="fp-card p-4 mb-5 reveal">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0">{{ __('Plan des stands') }}</h2>
                <div class="d-flex gap-3 small">
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#7ee0a3;"></span>{{ __('Disponible') }}</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:var(--fp-gold);"></span>{{ __('Réservé') }}</span>
                    <span><span class="d-inline-block rounded-circle me-1" style="width:10px;height:10px;background:#ff8f8f;"></span>{{ __('Vendu') }}</span>
                </div>
            </div>
            @foreach ($booths as $zone => $zoneBooths)
                <h3 class="h6 fp-muted mt-3">{{ __('Zone') }} {{ $zone }} — {{ $zoneBooths->first()->size }} m</h3>
                <div class="d-grid gap-2" style="grid-template-columns: repeat(auto-fill, minmax(84px, 1fr));">
                    @foreach ($zoneBooths as $booth)
                        <div class="fp-booth {{ $booth->status }}" title="{{ $booth->code }} — {{ number_format($booth->price, 0, ',', ' ') }} HTG ({{ $booth->status }})">
                            {{ $booth->code }}
                            <small>{{ $booth->status === 'available' ? number_format($booth->price / 1000).'k' : __(ucfirst($booth->status)) }}</small>
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        <div class="row g-5" id="reserver">
            <div class="col-lg-5 reveal">
                <h2 class="h3 mb-3">{{ __('Inclus avec chaque stand') }}</h2>
                <div class="d-grid gap-2">
                    @foreach (['Structure, table, chaises et éclairage', 'Signalétique avec votre logo', '2 à 6 badges exposant selon la zone', 'Fiche exposant sur le site + QR code', 'Accès à FINPO Connect (networking B2B)', 'Wi-Fi professionnel et électricité', 'Mention dans le répertoire officiel'] as $included)
                        <div class="fp-benefit">{{ __($included) }}</div>
                    @endforeach
                </div>
                <p class="fp-muted small mt-3">{{ __('Paiement en ligne (MonCash, carte, virement) après validation de votre demande par l\'équipe expo.') }}</p>
            </div>
            <div class="col-lg-7 reveal">
                <div class="fp-card p-4">
                    <h2 class="h4 mb-3">{{ __('Demande de réservation') }}</h2>
                    <form method="post" action="{{ route('exhibitors.apply') }}" class="row g-3">
                        @csrf
                        <input type="text" name="website_hp" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="col-md-6">
                            <label class="form-label" for="e-company">{{ __('Entreprise') }} *</label>
                            <input id="e-company" name="company" class="form-control" required value="{{ old('company') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-sector">{{ __('Secteur d\'activité') }}</label>
                            <input id="e-sector" name="sector" class="form-control" value="{{ old('sector') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-booth">{{ __('Stand souhaité') }}</label>
                            <select id="e-booth" name="booth_id" class="form-select">
                                <option value="">{{ __('Laisser l\'équipe choisir') }}</option>
                                @foreach ($booths->flatten()->where('status', 'available') as $booth)
                                    <option value="{{ $booth->id }}">{{ $booth->code }} — {{ $booth->size }} m — {{ number_format($booth->price, 0, ',', ' ') }} HTG</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-website">{{ __('Site web') }}</label>
                            <input id="e-website" type="url" name="website" class="form-control" placeholder="https://" value="{{ old('website') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-contact">{{ __('Personne de contact') }} *</label>
                            <input id="e-contact" name="contact_name" class="form-control" required value="{{ old('contact_name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-email">{{ __('Email') }} *</label>
                            <input id="e-email" type="email" name="contact_email" class="form-control" required value="{{ old('contact_email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="e-phone">{{ __('Téléphone') }}</label>
                            <input id="e-phone" name="contact_phone" class="form-control" value="{{ old('contact_phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="e-description">{{ __('Que présenterez-vous ?') }}</label>
                            <textarea id="e-description" name="description" rows="3" class="form-control">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-fp-primary">{{ __('Réserver mon stand') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
