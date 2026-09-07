@extends('layouts.public')

@section('title', "{$commune->name} — Territoire Môle-Saint-Nicolas")
@section('meta_description', $commune->description ?: "Commune de {$commune->name}, arrondissement de Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('territoire.index') }}" class="text-sm text-msn-sea-700 hover:underline">&larr; Territoire</a>

        <div class="mt-3 flex items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Commune de {{ $commune->name }}</h1>
            <x-content-status-badge :status="$commune->content_status" />
        </div>

        <p class="mt-4 max-w-2xl text-msn-sea-700">{{ $commune->description ?: '[Information à compléter]' }}</p>

        @if ($commune->population)
            <p class="mt-2 text-sm text-msn-sea-700">
                Population : {{ number_format($commune->population, 0, ',', ' ') }}
                @if ($commune->population_year) ({{ $commune->population_year }}) @endif
            </p>
        @endif

        <h2 class="mt-10 text-xl font-bold text-msn-sea-900">Sections communales</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            @forelse ($commune->sectionsCommunales as $section)
                <a href="{{ route('territoire.section', [$commune->slug, $section->slug]) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-msn-sea-900">{{ $section->name }}</h3>
                        <x-content-status-badge :status="$section->content_status" />
                    </div>
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">
                        {{ $section->description ?: '[Information à compléter]' }}
                    </p>
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucune section communale enregistrée]</p>
            @endforelse
        </div>
    </div>
@endsection
