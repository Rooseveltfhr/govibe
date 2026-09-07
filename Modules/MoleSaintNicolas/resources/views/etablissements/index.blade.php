@extends('layouts.public')

@section('title', "{$title} — Môle-Saint-Nicolas")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">{{ $title }}</h1>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($establishments as $establishment)
                <a href="{{ route($typeSlug === 'hotels' ? 'hotels.show' : 'restaurants.show', $establishment->slug) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold text-msn-sea-900">{{ $establishment->name }}</h2>
                        <x-content-status-badge :status="$establishment->content_status" />
                    </div>
                    @if ($establishment->type !== 'hotel')
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-msn-terracotta-500">
                            {{ $establishment->type === 'bar' ? 'Bar' : 'Restaurant' }}
                            @if ($establishment->cuisine_type) · {{ $establishment->cuisine_type }} @endif
                        </p>
                    @endif
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">
                        {{ $establishment->description ?: '[Information à compléter]' }}
                    </p>
                    @if ($establishment->price_range)
                        <p class="mt-2 text-sm font-semibold text-msn-sea-900">{{ $establishment->price_range }}</p>
                    @endif
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucun établissement enregistré]</p>
            @endforelse
        </div>
    </div>
@endsection
