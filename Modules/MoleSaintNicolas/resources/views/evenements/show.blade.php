@extends('layouts.public')

@section('title', "{$event->title} — Môle-Saint-Nicolas")
@section('meta_description', $event->description)

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <a href="{{ route('evenements.index') }}" class="text-sm text-msn-ink-700 hover:underline">&larr; Événements</a>

        <div class="mt-3 flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold text-msn-ink-900 sm:text-4xl">{{ $event->title }}</h1>
            @unless ($event->isUpcoming())
                <span class="rounded-full bg-msn-sand-200 px-2.5 py-0.5 text-xs font-medium text-msn-ink-700">Passé</span>
            @endunless
            <x-content-status-badge :status="$event->content_status" />
        </div>

        <p class="mt-1 text-sm font-semibold text-msn-terracotta-500">
            {{ $event->starts_at->format('d/m/Y à H:i') }}
            @if ($event->ends_at) — {{ $event->ends_at->format('d/m/Y à H:i') }} @endif
        </p>
        @if ($event->location)
            <p class="mt-1 text-sm text-msn-ink-700">{{ $event->location }}</p>
        @endif

        <p class="mt-6 whitespace-pre-line text-msn-ink-700">{{ $event->description }}</p>
    </div>
@endsection
