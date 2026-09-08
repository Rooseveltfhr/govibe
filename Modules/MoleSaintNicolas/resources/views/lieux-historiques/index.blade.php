@extends('layouts.public')

@section('title', 'Lieux historiques — Môle-Saint-Nicolas')
@section('meta_description', "Forts, monuments et sites du patrimoine de Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">Lieux historiques</h1>
        <p class="mt-3 text-msn-ink-700">
            Forts, monuments et sites du patrimoine de Môle-Saint-Nicolas.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($sites as $site)
                <a href="{{ route('lieux-historiques.show', $site->slug) }}"
                   class="block overflow-hidden rounded-2xl border border-msn-sand-200 bg-white shadow-sm transition hover:shadow-md">
                    <x-photo-placeholder icon="landmark" class="h-32 w-full" />
                    <div class="p-6">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-semibold text-msn-ink-900">{{ $site->name }}</h2>
                            <x-content-status-badge :status="$site->content_status" />
                        </div>
                        @if ($site->category)
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-msn-terracotta-500">{{ $site->category }}</p>
                        @endif
                        <p class="mt-2 text-sm text-msn-ink-700 line-clamp-2">
                            {{ $site->description ?: '[Information à compléter]' }}
                        </p>
                    </div>
                </a>
            @empty
                <p class="text-msn-ink-700">[Information à compléter — aucun lieu historique enregistré]</p>
            @endforelse
        </div>
    </div>
@endsection
