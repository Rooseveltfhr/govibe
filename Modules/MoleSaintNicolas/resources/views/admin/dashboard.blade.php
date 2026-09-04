@extends('layouts.admin')

@section('title', 'Tableau de bord — Administration')

@php
    $links = [
        ['route' => 'admin.territoire.communes.index', 'label' => 'Communes', 'count' => \App\Models\Territoire\Commune::count()],
        ['route' => 'admin.territoire.sections.index', 'label' => 'Sections communales', 'count' => \App\Models\Territoire\SectionCommunale::count()],
        ['route' => 'admin.histoire.periods.index', 'label' => 'Périodes historiques', 'count' => \App\Models\Histoire\HistoricalPeriod::count()],
        ['route' => 'admin.histoire.events.index', 'label' => 'Événements historiques', 'count' => \App\Models\Histoire\HistoricalEvent::count()],
        ['route' => 'admin.histoire.figures.index', 'label' => 'Personnages historiques', 'count' => \App\Models\Histoire\HistoricalFigure::count()],
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-10">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-msn-sea-900">Tableau de bord</h1>
                <p class="text-sm text-msn-sea-700">Connecté en tant que {{ auth()->user()->name }}
                    ({{ auth()->user()->getRoleNames()->implode(', ') }})</p>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="rounded-lg border border-msn-sea-500 px-4 py-2 text-sm font-semibold text-msn-sea-900 hover:bg-msn-sea-50">
                    Se déconnecter
                </button>
            </form>
        </header>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($links as $link)
                <a href="{{ route($link['route']) }}" class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <p class="text-3xl font-bold text-msn-sea-900">{{ $link['count'] }}</p>
                    <p class="mt-1 text-sm font-medium text-msn-sea-700">{{ $link['label'] }}</p>
                </a>
            @endforeach
        </div>

        <div class="mt-8 rounded-2xl border border-dashed border-msn-sea-500/40 bg-white p-6 text-sm text-msn-sea-700">
            À venir module par module : Sites historiques, Centre-ville, Carte interactive, Hôtels,
            Restaurants, Booking, Blog/Actualités, Événements publics, Galerie.
        </div>
    </div>
@endsection
