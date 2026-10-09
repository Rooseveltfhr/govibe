@extends('layouts.app', ['title' => __('Galerie'), 'description' => __('Photos et vidéos des éditions FINPO.')])

@section('content')
@include('partials.page-head', [
    'kicker' => __('Galerie'),
    'heading' => __('FINPO en images'),
    'lead' => __('Revivez les moments forts des éditions précédentes.'),
])

<section class="fp-section-tight">
    <div class="container">
        @if ($editions->count() > 1)
            <div class="d-flex gap-2 mb-4 reveal">
                <a href="{{ route('gallery') }}" class="btn btn-sm {{ request('edition') ? 'btn-fp-outline' : 'btn-fp-primary' }}">{{ __('Toutes') }}</a>
                @foreach ($editions as $edition)
                    <a href="{{ route('gallery', ['edition' => $edition]) }}" class="btn btn-sm {{ (string) request('edition') === (string) $edition ? 'btn-fp-primary' : 'btn-fp-outline' }}">{{ $edition }}</a>
                @endforeach
            </div>
        @endif
        <div class="fp-gallery-grid reveal">
            @forelse ($items as $item)
                @if ($item->type === 'video')
                    <figure>
                        <a href="{{ $item->url }}" target="_blank" rel="noopener">
                            <img src="{{ $item->thumb_url ?: 'https://placehold.co/800x600/101a2e/9fb3d1?text=Video' }}" alt="{{ $item->caption }}" loading="lazy">
                            <figcaption>▶ {{ $item->caption ?: __('Vidéo') }}</figcaption>
                        </a>
                    </figure>
                @else
                    <figure>
                        <img src="{{ $item->thumb_url ?: $item->url }}" alt="{{ $item->caption }}" loading="lazy">
                        @if ($item->caption)<figcaption>{{ $item->caption }}</figcaption>@endif
                    </figure>
                @endif
            @empty
                <p class="fp-muted">{{ __('La galerie sera alimentée pendant l\'événement.') }}</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
