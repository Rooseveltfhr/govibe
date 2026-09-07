@extends('layouts.public')

@section('title', "Territoire — Arrondissement de Môle-Saint-Nicolas")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <p class="text-sm font-semibold uppercase tracking-wide text-msn-terracotta-500">
            {{ $arrondissement?->department?->name ?? '[Information à compléter]' }}
        </p>
        <h1 class="mt-2 text-3xl font-bold text-msn-sea-900 sm:text-4xl">
            Arrondissement de {{ $arrondissement?->name ?? '[Information à compléter]' }}
        </h1>

        @if ($arrondissement?->population)
            <p class="mt-3 text-msn-sea-700">
                {{ number_format($arrondissement->population, 0, ',', ' ') }} habitants
                @if ($arrondissement->population_year)
                    ({{ $arrondissement->population_year }})
                @endif
                — {{ $arrondissement->area_km2 ? number_format($arrondissement->area_km2, 0, ',', ' ').' km²' : '[Information à compléter]' }}
            </p>
        @endif

        <h2 class="mt-10 text-xl font-bold text-msn-sea-900">Communes</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @forelse ($arrondissement?->communes ?? [] as $commune)
                <a href="{{ route('territoire.commune', $commune->slug) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-msn-sea-900">{{ $commune->name }}</h3>
                        <x-content-status-badge :status="$commune->content_status" />
                    </div>
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">
                        {{ $commune->description ?: '[Information à compléter]' }}
                    </p>
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucune commune enregistrée]</p>
            @endforelse
        </div>
    </div>
@endsection
