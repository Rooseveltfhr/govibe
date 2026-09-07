@extends('layouts.public')

@section('content')
    <section class="relative overflow-hidden bg-msn-sea-900 text-msn-sand-100">
        <div class="mx-auto flex min-h-[70vh] max-w-7xl flex-col justify-center px-4 py-24 sm:px-6 lg:px-8">
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
    </section>

    <section id="lieux-historiques" class="border-b border-msn-sand-200 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Lieux historiques</h2>
            <p class="mt-2 text-msn-sea-700">Forts, monuments et sites du patrimoine.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($sites as $site)
                    <a href="{{ route('lieux-historiques.show', $site->slug) }}"
                       class="block overflow-hidden rounded-2xl border border-msn-sand-200 bg-white shadow-sm transition hover:shadow-md">
                        <x-photo-placeholder icon="landmark" class="h-28 w-full" />
                        <div class="p-5">
                            <h3 class="font-semibold text-msn-sea-900">{{ $site->name }}</h3>
                            <p class="mt-1 text-sm text-msn-sea-700 line-clamp-2">{{ $site->description ?: '[Information à compléter]' }}</p>
                        </div>
                    </a>
                @empty
                    <p class="text-msn-sea-700">[Information à compléter — aucun lieu historique enregistré]</p>
                @endforelse
            </div>

            <a href="{{ route('lieux-historiques.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Voir tous les lieux historiques &rarr;
            </a>
        </div>
    </section>

    <section id="territoire" class="border-b border-msn-sand-200 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Territoire et sections communales</h2>
            <p class="mt-2 text-msn-sea-700">L'arrondissement de {{ $arrondissement?->name ?? 'Môle-Saint-Nicolas' }} et ses communes.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @forelse ($arrondissement?->communes ?? [] as $commune)
                    <a href="{{ route('territoire.commune', $commune->slug) }}"
                       class="block rounded-2xl border border-msn-sand-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                        <h3 class="font-semibold text-msn-sea-900">{{ $commune->name }}</h3>
                    </a>
                @empty
                    <p class="text-msn-sea-700">[Information à compléter]</p>
                @endforelse
            </div>

            <a href="{{ route('territoire.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Voir tout le territoire &rarr;
            </a>
        </div>
    </section>

    <section id="sejour" class="border-b border-msn-sand-200 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Où séjourner ?</h2>
            <p class="mt-2 text-msn-sea-700">Hôtels et hébergements à Môle-Saint-Nicolas.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($hotels as $hotel)
                    <a href="{{ route('hotels.show', $hotel->slug) }}"
                       class="block overflow-hidden rounded-2xl border border-msn-sand-200 bg-white shadow-sm transition hover:shadow-md">
                        <x-photo-placeholder class="h-28 w-full" />
                        <div class="p-5">
                            <h3 class="font-semibold text-msn-sea-900">{{ $hotel->name }}</h3>
                            <p class="mt-1 text-sm text-msn-sea-700 line-clamp-2">{{ $hotel->description ?: '[Information à compléter]' }}</p>
                        </div>
                    </a>
                @empty
                    <p class="text-msn-sea-700">[Information à compléter — aucun hôtel enregistré]</p>
                @endforelse
            </div>

            <a href="{{ route('hotels.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Voir tous les hôtels &rarr;
            </a>
        </div>
    </section>

    <section id="restaurants" class="border-b border-msn-sand-200 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Restaurants et bars</h2>
            <p class="mt-2 text-msn-sea-700">Où manger et boire un verre à Môle-Saint-Nicolas.</p>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($restaurants as $restaurant)
                    <a href="{{ route('restaurants.show', $restaurant->slug) }}"
                       class="block overflow-hidden rounded-2xl border border-msn-sand-200 bg-white shadow-sm transition hover:shadow-md">
                        <x-photo-placeholder class="h-28 w-full" />
                        <div class="p-5">
                            <h3 class="font-semibold text-msn-sea-900">{{ $restaurant->name }}</h3>
                            <p class="mt-1 text-sm text-msn-sea-700 line-clamp-2">{{ $restaurant->description ?: '[Information à compléter]' }}</p>
                        </div>
                    </a>
                @empty
                    <p class="text-msn-sea-700">[Information à compléter — aucun restaurant enregistré]</p>
                @endforelse
            </div>

            <a href="{{ route('restaurants.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Voir tous les restaurants &rarr;
            </a>
        </div>
    </section>

    <section id="actualites" class="border-b border-msn-sand-200 py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Dernières actualités</h2>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($posts as $post)
                    <a href="{{ route('actualites.show', $post->slug) }}"
                       class="block rounded-2xl border border-msn-sand-200 bg-white p-5 shadow-sm transition hover:shadow-md">
                        <h3 class="font-semibold text-msn-sea-900">{{ $post->title }}</h3>
                        <p class="mt-1 text-xs uppercase tracking-wide text-msn-sea-700/70">{{ $post->published_at->format('d/m/Y') }}</p>
                        <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">{{ $post->excerpt ?: '[Information à compléter]' }}</p>
                    </a>
                @empty
                    <p class="text-msn-sea-700">[Contenu à compléter — aucun article publié pour l'instant]</p>
                @endforelse
            </div>

            <a href="{{ route('actualites.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Voir toutes les actualités &rarr;
            </a>
        </div>
    </section>

    <section id="carte" class="py-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">Carte interactive</h2>
            <p class="mt-2 text-msn-sea-700">Communes, lieux historiques et établissements géolocalisés.</p>

            <div class="mt-6">
                <x-leaflet-map :markers="[]" class="h-72 w-full" />
            </div>

            <a href="{{ route('carte.index') }}" class="mt-6 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
                Ouvrir la carte interactive &rarr;
            </a>
        </div>
    </section>

    @php
        $sections = [
            ['id' => 'centre-ville', 'title' => 'Centre-ville', 'note' => 'Phase 2'],
            ['id' => 'explorer', 'title' => 'Activités et expériences', 'note' => 'Phase 3'],
            ['id' => 'evenements', 'title' => 'Événements', 'note' => 'Phase 5'],
            ['id' => 'galerie', 'title' => 'Galerie photos', 'note' => 'Phase 5'],
        ];
    @endphp

    @foreach ($sections as $section)
        <section id="{{ $section['id'] }}" class="border-t border-msn-sand-200 py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">{{ $section['title'] }}</h2>
                <p class="mt-3 text-msn-sea-700">[Contenu à compléter — {{ $section['note'] }}]</p>
            </div>
        </section>
    @endforeach
@endsection
