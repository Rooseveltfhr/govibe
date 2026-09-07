@extends('layouts.public')

@section('title', 'Événements — Môle-Saint-Nicolas')
@section('meta_description', "Événements à venir à Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Événements</h1>

        <div class="mt-8 space-y-4">
            @forelse ($events as $event)
                <a href="{{ route('evenements.show', $event->slug) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="font-semibold text-msn-sea-900">{{ $event->title }}</h2>
                        @unless ($event->isUpcoming())
                            <span class="rounded-full bg-msn-sand-200 px-2.5 py-0.5 text-xs font-medium text-msn-sea-700">Passé</span>
                        @endunless
                        <x-content-status-badge :status="$event->content_status" />
                    </div>
                    <p class="mt-1 text-xs uppercase tracking-wide text-msn-sea-700/70">
                        {{ $event->starts_at->format('d/m/Y à H:i') }}
                        @if ($event->location) · {{ $event->location }} @endif
                    </p>
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">{{ $event->description }}</p>
                </a>
            @empty
                <p class="text-msn-sea-700">[Contenu à compléter — aucun événement enregistré pour l'instant]</p>
            @endforelse
        </div>
    </div>
@endsection
