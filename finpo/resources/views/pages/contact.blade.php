@extends('layouts.app', ['title' => __('Contact'), 'description' => __('Contactez l\'équipe FINPO 2026 : email, téléphone, WhatsApp et formulaire en ligne.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Contact'),
    'heading' => __('Parlons de votre participation'),
    'lead' => __('Une question sur les billets, le sponsoring, l\'expo ou la presse ? Notre équipe répond sous 24 h ouvrées.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-7 reveal">
                <div class="fp-card p-4">
                    <h2 class="h4 mb-3">{{ __('Envoyez-nous un message') }}</h2>
                    <form method="post" action="{{ route('contact.submit') }}" class="row g-3">
                        @csrf
                        <input type="text" name="company" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
                        <div class="col-md-6">
                            <label class="form-label" for="c-name">{{ __('Nom complet') }} *</label>
                            <input id="c-name" name="name" class="form-control" required value="{{ old('name') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="c-email">{{ __('Email') }} *</label>
                            <input id="c-email" type="email" name="email" class="form-control" required value="{{ old('email') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="c-phone">{{ __('Téléphone') }}</label>
                            <input id="c-phone" name="phone" class="form-control" value="{{ old('phone') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="c-subject">{{ __('Sujet') }}</label>
                            <select id="c-subject" name="subject" class="form-select">
                                @foreach ([__('Billetterie'), __('Sponsoring'), __('Partenariat'), __('Expo & stands'), 'FINPO Awards', __('Presse'), __('Autre')] as $subject)
                                    <option @selected(old('subject') === $subject)>{{ $subject }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="c-message">{{ __('Message') }} *</label>
                            <textarea id="c-message" name="message" rows="5" class="form-control" required>{{ old('message') }}</textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-fp-primary">{{ __('Envoyer le message') }}</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="col-lg-5 reveal">
                <div class="d-grid gap-3">
                    <div class="fp-card p-4">
                        <h2 class="h5 mb-3">{{ __('Coordonnées') }}</h2>
                        <p class="mb-2">✉️ <a href="mailto:{{ config('finpo.contact.email') }}">{{ config('finpo.contact.email') }}</a></p>
                        <p class="mb-2">📞 <a href="tel:{{ config('finpo.contact.phone') }}">{{ config('finpo.contact.phone') }}</a></p>
                        <p class="mb-2">💬 <a href="https://wa.me/{{ preg_replace('/\D/', '', config('finpo.contact.whatsapp')) }}" target="_blank" rel="noopener">WhatsApp</a></p>
                        <p class="mb-0">📍 {{ config('finpo.venue.name') }}, {{ config('finpo.venue.city') }}, {{ config('finpo.venue.country') }}</p>
                    </div>
                    <div class="fp-card overflow-hidden">
                        <iframe title="{{ __('Carte du lieu de l\'événement') }}" loading="lazy" style="width: 100%; height: 300px; border: 0; display:block;"
                                src="https://www.google.com/maps?q={{ urlencode(config('finpo.venue.map_q')) }}&output=embed"></iframe>
                    </div>
                    <div class="fp-card p-4">
                        <h2 class="h5 mb-2">{{ __('Suivez-nous') }}</h2>
                        <div class="d-flex gap-2">
                            <a class="fp-social" href="{{ config('finpo.social.facebook') }}" target="_blank" rel="noopener" aria-label="Facebook">f</a>
                            <a class="fp-social" href="{{ config('finpo.social.instagram') }}" target="_blank" rel="noopener" aria-label="Instagram">◎</a>
                            <a class="fp-social" href="{{ config('finpo.social.linkedin') }}" target="_blank" rel="noopener" aria-label="LinkedIn">in</a>
                            <a class="fp-social" href="{{ config('finpo.social.x') }}" target="_blank" rel="noopener" aria-label="X">𝕏</a>
                            <a class="fp-social" href="{{ config('finpo.social.youtube') }}" target="_blank" rel="noopener" aria-label="YouTube">▶</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
