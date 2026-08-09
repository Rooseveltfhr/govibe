@extends('layouts.app', ['title' => __('À propos'), 'description' => __('Mission, vision et objectifs de FINPO — le Forum & Expo National des Institutions Publiques, Privées et Organisations.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('À propos'),
    'heading' => config('finpo.full_name'),
    'lead' => config('finpo.tagline'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-4 reveal">
                <div class="fp-card p-4 h-100">
                    <span class="fs-2">🎯</span>
                    <h2 class="h4 mt-2">{{ __('Mission') }}</h2>
                    <p class="fp-muted mb-0">{{ __('Créer la plus grande plateforme nationale de rencontre, de dialogue et de contractualisation entre les institutions publiques, le secteur privé et les organisations, au service du développement d\'Haïti.') }}</p>
                </div>
            </div>
            <div class="col-md-4 reveal">
                <div class="fp-card p-4 h-100">
                    <span class="fs-2">🔭</span>
                    <h2 class="h4 mt-2">{{ __('Vision') }}</h2>
                    <p class="fp-muted mb-0">{{ __('Faire de FINPO le rendez-vous institutionnel de référence de la Caraïbe : un espace où chaque édition produit des partenariats concrets, mesurables et durables.') }}</p>
                </div>
            </div>
            <div class="col-md-4 reveal">
                <div class="fp-card p-4 h-100">
                    <span class="fs-2">🧭</span>
                    <h2 class="h4 mt-2">{{ __('Objectifs') }}</h2>
                    <ul class="fp-muted mb-0 ps-3 d-grid gap-1">
                        <li>{{ __('Connecter 3 000+ décideurs publics, privés et associatifs') }}</li>
                        <li>{{ __('Faciliter la signature de partenariats structurants') }}</li>
                        <li>{{ __('Valoriser l\'innovation institutionnelle haïtienne') }}</li>
                        <li>{{ __('Renforcer la confiance entre acteurs nationaux et internationaux') }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-lg-6 reveal">
                <div class="fp-card p-4 h-100">
                    <h2 class="h4">{{ __('Résultats attendus') }}</h2>
                    <ul class="fp-muted mb-0 ps-3 d-grid gap-2 mt-3">
                        <li>{{ __('50+ protocoles d\'accord et partenariats signés pendant l\'événement') }}</li>
                        <li>{{ __('1 500+ rendez-vous B2B et B2G organisés via la plateforme de networking') }}</li>
                        <li>{{ __('Une déclaration finale co-signée par les parties prenantes') }}</li>
                        <li>{{ __('Un répertoire national des institutions et organisations participantes') }}</li>
                        <li>{{ __('Des retombées médiatiques nationales et internationales') }}</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6 reveal">
                <div class="fp-card p-4 h-100">
                    <h2 class="h4">{{ __('Qui devrait participer ?') }}</h2>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        @foreach (['Ministères et organismes publics', 'Collectivités territoriales', 'Entreprises et PME', 'Banques et institutions financières', 'ONG et fondations', 'Organisations internationales', 'Universités et centres de recherche', 'Startups et incubateurs', 'Chambres de commerce', 'Médias', 'Diaspora et investisseurs'] as $who)
                            <span class="fp-chip">{{ __($who) }}</span>
                        @endforeach
                    </div>
                    <h3 class="h5 mt-4">{{ __('Pourquoi participer ?') }}</h3>
                    <ul class="fp-muted mb-0 ps-3 d-grid gap-1 mt-2">
                        <li>{{ __('Rencontrer en 3 jours les décideurs qu\'il faudrait des mois à réunir') }}</li>
                        <li>{{ __('Présenter vos projets à des financeurs et partenaires qualifiés') }}</li>
                        <li>{{ __('Gagner en visibilité auprès des médias et du grand public') }}</li>
                        <li>{{ __('Se former grâce aux ateliers pratiques et keynotes d\'experts') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="fp-section" style="background: var(--fp-bg-2);">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-6 reveal">
                <span class="fp-kicker mb-2">{{ __('Histoire') }}</span>
                <h2 class="h1 mb-4">{{ __('D\'une idée à un mouvement national') }}</h2>
                <div class="fp-timeline">
                    <div class="fp-timeline-item">
                        <strong>2024 — {{ __('La genèse') }}</strong>
                        <p class="fp-muted small mb-0">{{ __('GOVIBE Innovation Hub organise une première série de tables rondes interinstitutionnelles à Port-au-Prince.') }}</p>
                    </div>
                    <div class="fp-timeline-item">
                        <strong>2025 — {{ __('Le forum pilote') }}</strong>
                        <p class="fp-muted small mb-0">{{ __('Édition pilote : 800 participants, 40 institutions, 12 partenariats signés. La preuve du concept est faite.') }}</p>
                    </div>
                    <div class="fp-timeline-item">
                        <strong>2026 — {{ __('L\'édition nationale') }}</strong>
                        <p class="fp-muted small mb-0">{{ __('FINPO devient le grand rendez-vous national : 3 jours, 3 000 participants attendus, expo de 32 stands et FINPO Awards.') }}</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 reveal">
                <span class="fp-kicker mb-2">{{ __('Organisation') }}</span>
                <h2 class="h1 mb-4">{{ __('Comité organisateur') }}</h2>
                <div class="fp-card p-4 mb-3">
                    <h3 class="h5 mb-1">{{ config('finpo.organizer.name') }}</h3>
                    <p class="fp-muted small mb-2">{{ __('Organisation hôte') }}</p>
                    <p class="fp-muted mb-2">{{ config('finpo.organizer.tagline') }}</p>
                    <a href="{{ config('finpo.organizer.url') }}" target="_blank" rel="noopener">{{ config('finpo.organizer.url') }}</a>
                </div>
                <div class="row g-3">
                    @foreach ([['Comité scientifique', 'Programme, intervenants et contenus'], ['Comité partenariats', 'Sponsors, partenaires et exposants'], ['Comité logistique', 'Accueil, sécurité et expérience participants'], ['Comité communication', 'Médias, presse et rayonnement']] as [$name, $role])
                        <div class="col-sm-6">
                            <div class="fp-card p-3 h-100">
                                <strong>{{ __($name) }}</strong>
                                <p class="fp-muted small mb-0">{{ __($role) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>

<section class="fp-section">
    <div class="container" style="max-width: 860px;">
        <div class="text-center mb-4 reveal">
            <span class="fp-kicker mb-2 justify-content-center">FAQ</span>
            <h2 class="h1">{{ __('Questions fréquentes') }}</h2>
        </div>
        <div class="accordion reveal" id="fpFaq">
            @foreach ([
                ['Quand et où se tient FINPO 2026 ?', 'Du 18 au 20 novembre 2026 au '.config('finpo.venue.name').', à Port-au-Prince. L\'accueil ouvre chaque jour à 8h00.'],
                ['Comment obtenir mon billet ?', 'Choisissez votre catégorie sur la page Inscription, remplissez le formulaire et recevez immédiatement votre billet électronique avec QR code, également envoyé par email.'],
                ['Y a-t-il un tarif étudiant ?', 'Oui : 1 000 HTG pour les étudiants sur présentation d\'une carte étudiante valide à l\'entrée. Les accréditations presse et volontaires sont gratuites.'],
                ['Quels moyens de paiement acceptez-vous ?', 'MonCash, NatCash, cartes Visa/MasterCard, PayPal, virement bancaire et paiement en espèces sur place.'],
                ['Comment devenir sponsor ou exposant ?', 'Rendez-vous sur les pages Sponsors ou Exposants et remplissez le formulaire : notre équipe commerciale vous recontacte sous 72 heures.'],
                ['Recevrai-je un certificat de participation ?', 'Oui, chaque participant enregistré (check-in sur place) reçoit un certificat officiel vérifiable en ligne par QR code.'],
            ] as $i => [$q, $a])
                <div class="accordion-item" style="background: var(--fp-card); border-color: var(--fp-card-border);">
                    <h3 class="accordion-header">
                        <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" style="background: transparent; color: var(--fp-text); box-shadow: none;" type="button" data-bs-toggle="collapse" data-bs-target="#faq{{ $i }}">
                            {{ __($q) }}
                        </button>
                    </h3>
                    <div id="faq{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#fpFaq">
                        <div class="accordion-body fp-muted">{{ __($a) }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endsection
