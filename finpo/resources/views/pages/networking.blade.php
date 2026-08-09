@extends('layouts.app', ['title' => __('Networking'), 'description' => __('Matchmaking d\'affaires, rendez-vous B2B et carte de visite digitale à FINPO 2026.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Networking'),
    'heading' => __('Les bonnes rencontres, au bon moment'),
    'lead' => __('FINPO Connect, notre plateforme de networking, transforme trois jours d\'événement en des mois d\'opportunités d\'affaires.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4">
            @foreach ([
                ['👥', 'Annuaire des participants', 'Parcourez les profils des participants par secteur, institution et centre d\'intérêt.'],
                ['💬', 'Messagerie intégrée', 'Échangez directement avec les participants avant, pendant et après l\'événement.'],
                ['🤝', 'Demandes de rendez-vous', 'Proposez un créneau : la plateforme gère les confirmations et les rappels.'],
                ['📅', 'Agenda de rendez-vous', 'Votre planning personnel de meetings, synchronisable avec votre calendrier.'],
                ['🎯', 'Matchmaking intelligent', 'Des suggestions de contacts pertinents basées sur vos objectifs déclarés.'],
                ['📲', 'Scan QR & carte digitale', 'Scannez le badge de votre interlocuteur pour échanger vos cartes de visite digitales.'],
            ] as [$icon, $feature, $description])
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="fp-card p-4 h-100">
                        <span class="fs-2">{{ $icon }}</span>
                        <h2 class="h5 mt-2">{{ __($feature) }}</h2>
                        <p class="fp-muted small mb-0">{{ __($description) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="fp-card p-4 p-lg-5 mt-5 text-center reveal">
            <h2 class="h3 mb-2">{{ __('FINPO Connect ouvre 30 jours avant l\'événement') }}</h2>
            <p class="fp-muted mb-4" style="max-width: 620px; margin-inline: auto;">{{ __('Tous les détenteurs de billet reçoivent automatiquement leurs accès par email. Réservez votre pass dès maintenant pour ne rater aucune opportunité.') }}</p>
            <a href="{{ route('register') }}" class="btn btn-fp-primary">{{ __('Réserver mon billet') }}</a>
        </div>
    </div>
</section>
@endsection
