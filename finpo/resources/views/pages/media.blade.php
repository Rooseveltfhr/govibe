@extends('layouts.app', ['title' => __('Espace médias'), 'description' => __('Communiqués de presse, kit média et ressources officielles FINPO 2026.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Espace médias'),
    'heading' => __('Ressources presse & médias'),
    'lead' => __('Tout ce qu\'il faut pour couvrir FINPO 2026 : communiqués, visuels officiels et accréditations.'),
])

<section class="fp-section-tight">
    <div class="container">
        <div class="row g-4 mb-5 reveal">
            @foreach ([
                ['📰', __('Communiqués de presse'), __('Les annonces officielles de l\'organisation.'), route('news')],
                ['🧰', __('Kit média'), __('Logos officiels, charte graphique et visuels HD.'), config('finpo.brochure_url')],
                ['🖼️', __('Galerie photo & vidéo'), __('Images libres de droits des éditions FINPO.'), route('gallery')],
                ['🎤', __('Accréditation presse'), __('Billet presse gratuit sur présentation de la carte.'), route('register')],
            ] as [$icon, $resource, $description, $link])
                <div class="col-md-6 col-xl-3">
                    <a href="{{ $link }}" class="d-block h-100" style="color: inherit;">
                        <div class="fp-card p-4 h-100">
                            <span class="fs-2">{{ $icon }}</span>
                            <h2 class="h5 mt-2">{{ $resource }}</h2>
                            <p class="fp-muted small mb-0">{{ $description }}</p>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        @if ($posts->isNotEmpty())
            <h2 class="h3 mb-3 reveal">{{ __('Derniers communiqués') }}</h2>
            <div class="row g-4">
                @foreach ($posts as $post)
                    <div class="col-md-4 reveal">
                        @include('partials.news-card', ['post' => $post])
                    </div>
                @endforeach
            </div>
        @endif

        <div class="fp-card p-4 mt-5 reveal">
            <h2 class="h5 mb-1">{{ __('Contact presse') }}</h2>
            <p class="fp-muted mb-0">{{ config('finpo.contact.email') }} · {{ config('finpo.contact.phone') }} — {{ __('objet « Presse »') }}</p>
        </div>
    </div>
</section>
@endsection
