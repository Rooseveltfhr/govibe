@extends('layouts.public')

@section('title', 'Explorer — Môle-Saint-Nicolas')
@section('meta_description', "Activités et expériences à Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Explorer</h1>
        <p class="mt-3 text-msn-sea-700">Activités et expériences à Môle-Saint-Nicolas.</p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($activities as $activity)
                <a href="{{ route('explorer.show', $activity->slug) }}"
                   class="block overflow-hidden rounded-2xl border border-msn-sand-200 bg-white shadow-sm transition hover:shadow-md">
                    <x-photo-placeholder icon="map" class="h-32 w-full" />
                    <div class="p-6">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-semibold text-msn-sea-900">{{ $activity->title }}</h2>
                            <x-content-status-badge :status="$activity->content_status" />
                        </div>
                        @if ($activity->category)
                            <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-msn-terracotta-500">{{ $activity->category }}</p>
                        @endif
                        <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">{{ $activity->description }}</p>
                    </div>
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucune activité enregistrée]</p>
            @endforelse
        </div>
    </div>
@endsection
