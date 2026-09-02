@extends('layouts.public')

@section('title', "Histoire de Môle-Saint-Nicolas")
@section('meta_description', "Timeline historique de Môle-Saint-Nicolas, Haïti : origines, périodes coloniales, événements et personnages.")

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Histoire de Môle-Saint-Nicolas</h1>
        <p class="mt-3 text-msn-sea-700">
            Une chronologie en construction — chaque période est sourcée et sera confirmée
            progressivement par l'équipe éditoriale.
        </p>

        <div class="mt-12 space-y-16">
            @forelse ($periods as $period)
                <section class="border-l-4 border-msn-terracotta-500 pl-6">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="text-2xl font-bold text-msn-sea-900">{{ $period->name }}</h2>
                        <span class="text-sm font-semibold text-msn-sea-700">
                            @if ($period->start_year)
                                ({{ $period->start_year }}@if ($period->end_year) – {{ $period->end_year }} @endif)
                            @endif
                        </span>
                        <x-content-status-badge :status="$period->content_status" />
                    </div>

                    <p class="mt-3 max-w-2xl text-msn-sea-700">{{ $period->description ?: '[Information à compléter]' }}</p>

                    @if ($period->events->isNotEmpty())
                        <ul class="mt-6 space-y-4">
                            @foreach ($period->events as $event)
                                <li class="rounded-xl bg-white p-4 shadow-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-msn-terracotta-600">
                                            {{ $event->happened_on?->translatedFormat('d F Y') ?? $event->circa_year ?? '[Information à compléter]' }}
                                        </span>
                                        <x-content-status-badge :status="$event->content_status" />
                                    </div>
                                    <p class="mt-1 font-semibold text-msn-sea-900">{{ $event->title }}</p>
                                    <p class="mt-1 text-sm text-msn-sea-700">{{ $event->description ?: '[Information à compléter]' }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($period->figures->isNotEmpty())
                        <div class="mt-6">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-msn-sea-700">Personnages</h3>
                            <ul class="mt-2 flex flex-wrap gap-2">
                                @foreach ($period->figures as $figure)
                                    <li class="rounded-full bg-msn-sand-200 px-3 py-1 text-sm text-msn-sea-900">{{ $figure->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucune période historique enregistrée]</p>
            @endforelse
        </div>
    </div>
@endsection
