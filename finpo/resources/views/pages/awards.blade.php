@extends('layouts.app', ['title' => 'FINPO Awards', 'description' => __('Les FINPO Awards distinguent les institutions, entreprises et organisations les plus innovantes d\'Haïti.')])

@section('content')
@include('partials.page-head', [
    'kicker' => 'FINPO Awards',
    'heading' => __('Célébrer l\'excellence institutionnelle'),
    'lead' => __('La cérémonie des FINPO Awards distingue chaque année les acteurs qui font bouger les lignes du développement en Haïti.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4 mb-5">
            @foreach ([
                ['🏛️', 'Institution publique de l\'année', 'Pour une administration exemplaire dans la modernisation de ses services.'],
                ['🏢', 'Entreprise citoyenne de l\'année', 'Pour un impact social et économique remarquable.'],
                ['🌍', 'ONG / Organisation de l\'année', 'Pour un programme à fort impact sur les communautés.'],
                ['💡', 'Prix de l\'innovation', 'Pour une solution innovante au service de l\'intérêt général.'],
                ['🚀', 'Startup de l\'année', 'Pour une jeune entreprise haïtienne à fort potentiel.'],
                ['🤝', 'Partenariat de l\'année', 'Pour une alliance public-privé aux résultats mesurables.'],
            ] as [$icon, $award, $description])
                <div class="col-md-6 col-lg-4 reveal">
                    <div class="fp-card p-4 h-100 text-center">
                        <span class="fs-1">{{ $icon }}</span>
                        <h2 class="h5 mt-2">{{ __($award) }}</h2>
                        <p class="fp-muted small mb-0">{{ __($description) }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 align-items-center reveal">
            <div class="col-lg-6">
                <img class="img-fluid rounded-4 w-100" style="aspect-ratio: 16/10; object-fit: cover;"
                     src="https://images.unsplash.com/photo-1505236858219-8359eb29e329?auto=format&fit=crop&w=1200&q=80" alt="{{ __('Cérémonie de remise de prix') }}" loading="lazy">
            </div>
            <div class="col-lg-6">
                <h2 class="h1 mb-3">{{ __('Comment candidater ?') }}</h2>
                <ol class="fp-muted d-grid gap-2 ps-3">
                    <li>{{ __('Soumettez votre dossier avant le 30 septembre 2026 (formulaire de contact, objet « FINPO Awards »).') }}</li>
                    <li>{{ __('Un jury indépendant évalue impact, innovation et durabilité.') }}</li>
                    <li>{{ __('Les finalistes sont annoncés le 1er novembre et invités à la cérémonie.') }}</li>
                    <li>{{ __('Remise des prix le 20 novembre en clôture de FINPO 2026.') }}</li>
                </ol>
                <a href="{{ route('contact') }}" class="btn btn-fp-primary mt-2">{{ __('Déposer une candidature') }}</a>
            </div>
        </div>
    </div>
</section>
@endsection
