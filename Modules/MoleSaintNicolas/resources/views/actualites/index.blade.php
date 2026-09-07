@extends('layouts.public')

@section('title', 'Actualités — Môle-Saint-Nicolas')
@section('meta_description', "Actualités et nouvelles de Môle-Saint-Nicolas, Haïti.")

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-msn-sand-100 sm:text-4xl">Actualités</h1>

        <div class="mt-8 space-y-6">
            @forelse ($posts as $post)
                <a href="{{ route('actualites.show', $post->slug) }}"
                   class="block rounded-2xl border border-msn-sea-700 bg-msn-sea-900 p-6 shadow-sm transition hover:shadow-md">
                    <div class="flex flex-wrap items-center gap-3">
                        <h2 class="font-semibold text-msn-sand-100">{{ $post->title }}</h2>
                        <x-content-status-badge :status="$post->content_status" />
                    </div>
                    <p class="mt-1 text-xs uppercase tracking-wide text-msn-sand-200/70">{{ $post->published_at->format('d/m/Y') }}</p>
                    <p class="mt-2 text-sm text-msn-sand-200 line-clamp-2">{{ $post->excerpt ?: '[Information à compléter]' }}</p>
                </a>
            @empty
                <p class="text-msn-sand-200">[Contenu à compléter — aucun article publié pour l'instant]</p>
            @endforelse
        </div>
    </div>
@endsection
