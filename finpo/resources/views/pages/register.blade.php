@extends('layouts.app', ['title' => __('Inscription & billets'), 'description' => __('Choisissez votre pass FINPO 2026 : étudiant, professionnel, institution, VIP, presse et plus.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Billetterie'),
    'heading' => __('Inscription & billets'),
    'lead' => __('Un tarif pour chaque profil. Paiement par MonCash, NatCash, carte bancaire, PayPal, virement ou sur place.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4">
            @forelse ($categories as $category)
                <div class="col-md-6 col-xl-3 reveal">
                    @include('partials.ticket-card', ['category' => $category])
                </div>
            @empty
                <p class="fp-muted">{{ __('La billetterie ouvrira bientôt.') }}</p>
            @endforelse
        </div>

        <div class="fp-card p-4 mt-5 reveal">
            <div class="row g-4 text-center">
                <div class="col-md-3"><span class="fs-3">🎟️</span><p class="small fp-muted mb-0 mt-1">{{ __('Billet électronique avec QR code unique') }}</p></div>
                <div class="col-md-3"><span class="fs-3">📧</span><p class="small fp-muted mb-0 mt-1">{{ __('Confirmation immédiate par email') }}</p></div>
                <div class="col-md-3"><span class="fs-3">🪪</span><p class="small fp-muted mb-0 mt-1">{{ __('Badge professionnel généré automatiquement') }}</p></div>
                <div class="col-md-3"><span class="fs-3">📜</span><p class="small fp-muted mb-0 mt-1">{{ __('Certificat de participation vérifiable') }}</p></div>
            </div>
        </div>
    </div>
</section>
@endsection
