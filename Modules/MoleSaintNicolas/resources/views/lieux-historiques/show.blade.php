@extends('layouts.public')

@section('title', $site->name.' — Môle-Saint-Nicolas')
@section('meta_description', $site->description ?: $site->name)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <x-photo-placeholder icon="landmark" class="h-56 w-full rounded-2xl" />

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">{{ $site->name }}</h1>
            <x-content-status-badge :status="$site->content_status" />
        </div>
        @if ($site->category)
            <p class="mt-1 text-sm font-semibold uppercase tracking-wide text-msn-terracotta-500">{{ $site->category }}</p>
        @endif

        <p class="mt-6 text-msn-ink-700">{{ $site->description ?: '[Information à compléter]' }}</p>

        @if ($site->hasCoordinates())
            <div class="mt-8">
                <h2 class="mb-3 text-lg font-semibold text-msn-ink-900">Localisation</h2>
                <x-leaflet-map
                    :markers="[['lat' => (float) $site->lat, 'lng' => (float) $site->lng, 'label' => $site->name]]"
                    :center-lat="(float) $site->lat"
                    :center-lng="(float) $site->lng"
                    :zoom="15"
                />
            </div>
        @else
            <p class="mt-8 text-sm text-msn-ink-700">[Coordonnées GPS à compléter]</p>
        @endif

        <a href="{{ route('lieux-historiques.index') }}" class="mt-8 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
            &larr; Tous les lieux historiques
        </a>
    </div>
@endsection
