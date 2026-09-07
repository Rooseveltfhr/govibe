@extends('layouts.public')

@section('title', 'Projets communautaires — Môle-Saint-Nicolas')
@section('meta_description', "Projets communautaires en cours, planifiés ou terminés à Môle-Saint-Nicolas, Haïti.")

@php
    $statusLabels = ['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'];
    $statusColors = [
        'planifie' => 'bg-msn-gold-400/20 text-msn-gold-400',
        'en_cours' => 'bg-msn-terracotta-500/20 text-msn-terracotta-500',
        'termine' => 'bg-green-500/20 text-green-400',
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">Projets communautaires</h1>
        <p class="mt-3 text-msn-sea-700">
            Projets menés dans la commune — donnez votre avis sur chacun.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            @forelse ($projects as $project)
                <a href="{{ route('projets.show', $project->slug) }}"
                   class="block rounded-2xl border border-msn-sand-200 bg-white p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold text-msn-sea-900">{{ $project->title }}</h2>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$project->status] }}">
                            {{ $statusLabels[$project->status] }}
                        </span>
                        <x-content-status-badge :status="$project->content_status" />
                    </div>
                    <p class="mt-2 text-sm text-msn-sea-700 line-clamp-2">{{ $project->description }}</p>
                </a>
            @empty
                <p class="text-msn-sea-700">[Information à compléter — aucun projet communautaire enregistré]</p>
            @endforelse
        </div>
    </div>
@endsection
