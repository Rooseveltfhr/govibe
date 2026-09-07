@extends('layouts.public')

@section('title', 'Établissements — Môle-Saint-Nicolas')
@section('meta_description', 'Hôtels, restaurants et bars de Môle-Saint-Nicolas, Haïti.')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Établissements</h1>
        <p class="mt-2 text-msn-sea-700">Hôtels, restaurants et bars à Môle-Saint-Nicolas.</p>

        <div class="mt-6 flex gap-3 text-sm">
            <a href="{{ route('hotels.index') }}" class="rounded-full border border-msn-sand-200 px-4 py-1.5 font-semibold text-msn-sea-900 hover:bg-white">Hôtels</a>
            <a href="{{ route('restaurants.index') }}" class="rounded-full border border-msn-sand-200 px-4 py-1.5 font-semibold text-msn-sea-900 hover:bg-white">Restaurants &amp; bars</a>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($establishments as $establishment)
                <a href="{{ route($establishment->type === 'hotel' ? 'hotels.show' : 'restaurants.show', $establishment->slug) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-msn-sea-900">{{ $establishment->name }}</h2>
                        <x-content-status-badge :status="$establishment->content_status" />
                    </div>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-msn-terracotta-500">
                        {{ ['hotel' => 'Hôtel', 'restaurant' => 'Restaurant', 'bar' => 'Bar'][$establishment->type] }}
                    </p>
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">
                        {{ $establishment->description ?: '[Information à compléter]' }}
                    </p>
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucun établissement enregistré]</p>
            @endforelse
        </div>
    </div>
@endsection
