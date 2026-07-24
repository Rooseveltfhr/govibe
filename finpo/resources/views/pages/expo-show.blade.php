@extends('layouts.app', ['title' => $exhibitor->company, 'description' => \Illuminate\Support\Str::limit($exhibitor->description, 150), 'image' => $exhibitor->banner_url])

@section('content')
<header class="fp-page-head pb-0" style="padding-bottom: 0 !important;">
    <div class="container">
        <div class="rounded-4 overflow-hidden mb-n5 position-relative" style="aspect-ratio: 16/5; min-height: 180px;">
            <img src="{{ $exhibitor->banner_url ?: 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1600&q=80' }}"
                 alt="" class="w-100 h-100" style="object-fit: cover;">
        </div>
    </div>
</header>

<section class="fp-section-tight" style="padding-top: 5rem;">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-8">
                <div class="d-flex gap-3 align-items-center mb-4">
                    <img src="{{ $exhibitor->logo_url ?: 'https://placehold.co/160x160/101a2e/9fb3d1?text='.urlencode($exhibitor->company) }}"
                         alt="" width="88" height="88" class="rounded-4" style="object-fit: cover;">
                    <div>
                        <h1 class="h2 mb-1">{{ $exhibitor->company }}</h1>
                        <div class="d-flex flex-wrap gap-2">
                            @if ($exhibitor->sector)<span class="fp-chip">{{ $exhibitor->sector }}</span>@endif
                            @if ($exhibitor->booth)<span class="fp-chip fp-chip-gold">📍 {{ __('Stand') }} {{ $exhibitor->booth->code }} ({{ __('zone') }} {{ $exhibitor->booth->zone }})</span>@endif
                        </div>
                    </div>
                </div>

                <p class="fp-muted">{{ $exhibitor->description }}</p>

                @if ($exhibitor->products)
                    <h2 class="h4 mt-4">{{ __('Produits') }}</h2>
                    <p class="fp-muted">{{ $exhibitor->products }}</p>
                @endif
                @if ($exhibitor->services)
                    <h2 class="h4 mt-4">{{ __('Services') }}</h2>
                    <p class="fp-muted">{{ $exhibitor->services }}</p>
                @endif

                @if ($exhibitor->video_url)
                    <a class="btn btn-fp-outline mt-3" href="{{ $exhibitor->video_url }}" target="_blank" rel="noopener">▶ {{ __('Voir la vidéo de présentation') }}</a>
                @endif
                @if ($exhibitor->brochure_url)
                    <a class="btn btn-fp-outline mt-3" href="{{ $exhibitor->brochure_url }}" target="_blank" rel="noopener">⬇ {{ __('Télécharger la brochure') }}</a>
                @endif
            </div>

            <div class="col-lg-4">
                <div class="fp-card p-4">
                    <h2 class="h5 mb-3">{{ __('Entrer en contact') }}</h2>
                    @if ($exhibitor->website)
                        <p class="mb-2">🌐 <a href="{{ $exhibitor->website }}" target="_blank" rel="noopener">{{ parse_url($exhibitor->website, PHP_URL_HOST) ?: $exhibitor->website }}</a></p>
                    @endif
                    @foreach (($exhibitor->socials ?? []) as $network => $url)
                        <p class="mb-2 text-capitalize">🔗 <a href="{{ $url }}" target="_blank" rel="noopener">{{ $network }}</a></p>
                    @endforeach
                    <hr style="border-color: var(--fp-card-border);">
                    <a href="{{ route('register') }}" class="btn btn-fp-primary w-100 mb-2">📅 {{ __('Réserver un rendez-vous sur place') }}</a>
                    <p class="fp-muted small mb-3">{{ __('Les rendez-vous B2B se planifient via FINPO Connect, inclus avec votre billet.') }}</p>
                    <div class="text-center bg-white rounded-3 p-3">
                        <img src="{{ \App\Support\Qr::svgDataUri(url()->current(), 180) }}" alt="{{ __('QR code de la fiche exposant') }}" class="img-fluid" style="max-width: 150px;">
                        <p class="small text-dark mb-0 mt-1">{{ __('Partager cette fiche') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
