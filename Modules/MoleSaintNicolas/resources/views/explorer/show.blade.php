@extends('layouts.public')

@section('title', "{$activity->title} — Môle-Saint-Nicolas")
@section('meta_description', $activity->description)

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('explorer.index') }}" class="text-sm text-msn-sea-700 hover:underline">&larr; Explorer</a>

        <x-photo-placeholder icon="map" class="mt-3 h-56 w-full rounded-2xl" />

        <div class="mt-6 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">{{ $activity->title }}</h1>
            <x-content-status-badge :status="$activity->content_status" />
        </div>
        @if ($activity->category)
            <p class="mt-1 text-sm font-semibold uppercase tracking-wide text-msn-terracotta-500">{{ $activity->category }}</p>
        @endif

        <p class="mt-4 max-w-2xl text-msn-sea-700">{{ $activity->description }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-msn-sea-700">Durée</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $activity->duration ?: '[Information à compléter]' }}</dd>
            </div>
            <div>
                <dt class="text-msn-sea-700">Tarif</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $activity->price_range ?: '[Information à compléter]' }}</dd>
            </div>
            <div>
                <dt class="text-msn-sea-700">Téléphone</dt>
                <dd class="font-semibold text-msn-sea-900">{{ $activity->phone ?: '[Information à compléter]' }}</dd>
            </div>
        </dl>

        <div class="mt-10 rounded-2xl border border-msn-sand-200 bg-white p-6">
            <h2 class="text-xl font-bold text-msn-sea-900">Contact direct</h2>
            <p class="mt-1 text-sm text-msn-sea-700">
                Aucune réservation en ligne — contactez directement l'organisateur pour réserver.
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                @if ($activity->whatsapp)
                    <a href="https://wa.me/{{ preg_replace('/\D/', '', $activity->whatsapp) }}"
                       class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                        WhatsApp
                    </a>
                @endif
                @if ($activity->phone)
                    <a href="tel:{{ $activity->phone }}"
                       class="rounded-lg border border-msn-sand-200 px-5 py-2.5 font-semibold text-msn-sea-900 hover:bg-msn-sand-100">
                        Appeler
                    </a>
                @endif
                @if (! $activity->whatsapp && ! $activity->phone)
                    <p class="text-sm text-msn-sea-700">[Information à compléter — coordonnées de contact]</p>
                @endif
            </div>
        </div>
    </div>
@endsection
