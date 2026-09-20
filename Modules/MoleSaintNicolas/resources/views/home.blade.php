@extends('layouts.public')

@section('content')
    @php $homeVideoId = config('services.home_video.id'); @endphp
    <section class="relative overflow-hidden bg-msn-sea-900 text-msn-sand-100">
        <div class="mx-auto flex max-w-7xl flex-col-reverse items-center gap-10 px-4 py-16 sm:px-6 lg:flex-row lg:items-center lg:gap-16 lg:px-8 lg:py-24">
            <div class="w-full lg:flex-1">
                <p class="hero-fade-up text-sm font-semibold uppercase tracking-[0.3em] text-msn-gold-400" style="animation-delay: .05s">
                    Nord-Ouest, Haïti
                </p>
                <h1 class="hero-fade-up mt-4 max-w-3xl text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl" style="animation-delay: .15s">
                    Môle-Saint-Nicolas, la porte historique d'Haïti
                </h1>
                <p class="hero-fade-up mt-6 max-w-2xl text-lg text-msn-sand-200" style="animation-delay: .3s">
                    Découvrez
                    <span x-data="{
                            words: ['l\'histoire', 'le patrimoine', 'le territoire', 'le tourisme'],
                            i: 0,
                            word: '',
                        }"
                        x-init="
                            word = words[0];
                            setInterval(() => {
                                $el.classList.add('opacity-0');
                                setTimeout(() => {
                                    i = (i + 1) % words.length;
                                    word = words[i];
                                    $el.classList.remove('opacity-0');
                                }, 300);
                            }, 2600);
                        "
                        x-text="word"
                        class="inline-block font-semibold text-msn-gold-400 transition-opacity duration-300"
                    ></span>
                    de Môle-Saint-Nicolas — réunis dans une seule plateforme, en construction module
                    par module.
                </p>
                <div class="hero-fade-up mt-10 flex flex-wrap gap-4" style="animation-delay: .45s">
                    <a href="{{ route('histoire.index') }}" class="rounded-full bg-msn-terracotta-500 px-6 py-3 font-semibold text-white hover:bg-msn-terracotta-600">
                        Découvrir Môle-Saint-Nicolas
                    </a>
                    <a href="{{ route('territoire.index') }}" class="rounded-full border border-msn-sea-700 px-6 py-3 font-semibold hover:bg-msn-sea-950/10">
                        Explorer le territoire
                    </a>
                </div>
            </div>

            @if ($homeVideoId)
                {{-- Vidéo dans le hero (demande client), format d'origine respecté
                     intégralement : la boîte a exactement le ratio de la source
                     (YouTube Shorts, 9:16), donc la vidéo la remplit sans jamais
                     être recadrée ni zoomée. En colonne à côté du texte plutôt
                     qu'en fond derrière lui : sur petit écran, une vidéo pleine
                     largeur en fond aurait entièrement recouvert le texte. Pas de
                     voile sombre par-dessus : le client veut la vidéo bien
                     visible, contrastée. --}}
                <div class="w-full max-w-[220px] flex-shrink-0 sm:max-w-xs">
                    <div class="aspect-[9/16] overflow-hidden rounded-2xl shadow-2xl">
                        <iframe
                            class="pointer-events-none h-full w-full"
                            src="https://www.youtube.com/embed/{{ $homeVideoId }}?autoplay=1&mute=1&loop=1&playlist={{ $homeVideoId }}&controls=0&rel=0&modestbranding=1&playsinline=1&disablekb=1"
                            title="Vidéo de présentation — Môle-Saint-Nicolas"
                            allow="autoplay; encrypted-media"
                        ></iframe>
                    </div>
                </div>
            @endif
        </div>
    </section>

    @php $moleCommune = $arrondissement?->communes->firstWhere('slug', 'mole-saint-nicolas'); @endphp
    @if ($moleCommune?->description)
        <section class="border-b border-msn-sand-200 bg-white py-16">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-bold text-msn-ink-900 sm:text-3xl">Môle-Saint-Nicolas</h2>
                    <x-content-status-badge :status="$moleCommune->content_status" />
                </div>
                <p class="mt-6 whitespace-pre-line leading-relaxed text-msn-ink-700">{{ $moleCommune->description }}</p>
                @if ($moleCommune->population)
                    <p class="mt-6 text-sm font-medium text-msn-ink-700">
                        Population : {{ number_format($moleCommune->population, 0, ',', ' ') }}
                        @if ($moleCommune->population_year) ({{ $moleCommune->population_year }}) @endif
                    </p>
                @endif
            </div>
        </section>
    @endif

    <section id="carte" class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-ink-900 sm:text-3xl">Carte interactive</h2>
            <p class="mt-2 text-msn-ink-700">Communes, lieux historiques et établissements géolocalisés.</p>

            <div class="mt-6">
                <x-leaflet-map :markers="[]" class="h-72 w-full" />
            </div>

            <a href="{{ route('carte.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Ouvrir la carte interactive &rarr;
            </a>
        </div>
    </section>

    @if ($photos->isNotEmpty())
        <section id="galerie" class="border-t border-msn-sand-200 py-16">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-msn-ink-900 sm:text-3xl">Galerie photos</h2>

                <div class="relative mt-6" x-data="{
                        slides: {{ $photos->count() }},
                        current: 0,
                        auto: null,
                        start() { this.auto = setInterval(() => this.next(), 5000) },
                        next() { this.current = (this.current + 1) % this.slides },
                        prev() { this.current = (this.current - 1 + this.slides) % this.slides },
                    }"
                    x-init="start()">
                    <div class="aspect-video overflow-hidden rounded-2xl border border-msn-sand-200 bg-msn-sand-200">
                        @foreach ($photos as $index => $photo)
                            <img x-show="current === {{ $index }}" x-transition.opacity.duration.700ms
                                 src="{{ $photo->url }}" alt="{{ $photo->title ?: 'Môle-Saint-Nicolas' }}"
                                 class="h-full w-full object-cover" loading="lazy" style="display: none">
                        @endforeach
                    </div>

                    @if ($photos->count() > 1)
                        <button type="button" @click="auto && clearInterval(auto); prev(); start()"
                                class="absolute top-1/2 left-3 -translate-y-1/2 rounded-full bg-white/90 p-2 text-msn-ink-900 shadow hover:bg-white"
                                aria-label="Photo précédente">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        <button type="button" @click="auto && clearInterval(auto); next(); start()"
                                class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full bg-white/90 p-2 text-msn-ink-900 shadow hover:bg-white"
                                aria-label="Photo suivante">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div class="mt-4 flex justify-center gap-2">
                            @foreach ($photos as $index => $photo)
                                <button type="button" @click="auto && clearInterval(auto); current = {{ $index }}; start()"
                                        class="h-2 w-2 rounded-full"
                                        :class="current === {{ $index }} ? 'bg-msn-terracotta-500' : 'bg-msn-sand-200'"
                                        aria-label="Aller à la photo {{ $index + 1 }}"></button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <a href="{{ route('galerie.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                    Voir la galerie complète &rarr;
                </a>
            </div>
        </section>
    @endif
@endsection
