@extends('layouts.public')

@section('content')
    <section class="relative overflow-hidden bg-msn-sea-900 text-msn-sand-100">
        <div class="mx-auto flex min-h-[70vh] max-w-7xl flex-col justify-center px-4 py-24 sm:px-6 lg:px-8">
            <p class="text-sm font-semibold uppercase tracking-[0.3em] text-msn-gold-400">Nord-Ouest, Haïti</p>
            <h1 class="mt-4 max-w-3xl text-4xl font-bold leading-tight sm:text-5xl lg:text-6xl">
                Môle-Saint-Nicolas, la porte historique d'Haïti
            </h1>
            <p class="mt-6 max-w-2xl text-lg text-msn-sand-200">
                Histoire, territoire, patrimoine et tourisme réunis dans une seule plateforme —
                en construction, module par module.
            </p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="#histoire" class="rounded-full bg-msn-terracotta-500 px-6 py-3 font-semibold text-white hover:bg-msn-terracotta-600">
                    Découvrir Môle-Saint-Nicolas
                </a>
                <a href="{{ route('territoire.index') }}" class="rounded-full border border-msn-sand-200 px-6 py-3 font-semibold hover:bg-msn-sand-100/10">
                    Explorer le territoire
                </a>
            </div>
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

    @php
        $sections = [
            ['id' => 'histoire', 'title' => "Découvrir son histoire", 'note' => 'Timeline historique — Phase 2'],
            ['id' => 'sites-historiques', 'title' => 'Sites historiques', 'note' => 'Fiches détaillées + carte — Phase 2'],
            ['id' => 'centre-ville', 'title' => 'Centre-ville', 'note' => 'Phase 2'],
            ['id' => 'sejour', 'title' => 'Où séjourner ?', 'note' => 'Hôtels et hébergements — Phase 3'],
            ['id' => 'restaurants', 'title' => 'Restaurants', 'note' => 'Phase 3'],
            ['id' => 'explorer', 'title' => 'Activités et expériences', 'note' => 'Phase 3'],
            ['id' => 'evenements', 'title' => 'Événements', 'note' => 'Phase 5'],
            ['id' => 'actualites', 'title' => 'Dernières actualités', 'note' => 'Blog / News — Phase 5'],
            ['id' => 'galerie', 'title' => 'Galerie photos', 'note' => 'Phase 5'],
            ['id' => 'carte', 'title' => 'Carte interactive', 'note' => 'Phase 2'],
        ];
    @endphp

    @foreach ($sections as $section)
        <section id="{{ $section['id'] }}" class="border-b border-msn-sand-200 py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <h2 class="text-2xl font-bold text-msn-sea-900 sm:text-3xl">{{ $section['title'] }}</h2>
                <p class="mt-3 text-msn-sea-700">[Contenu à compléter — {{ $section['note'] }}]</p>
            </div>
        </section>
    @endforeach
@endsection
