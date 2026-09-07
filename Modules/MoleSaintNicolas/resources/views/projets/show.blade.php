@extends('layouts.public')

@section('title', $project->title.' — Môle-Saint-Nicolas')
@section('meta_description', $project->title)

@php
    $statusLabels = ['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'];
    $statusColors = [
        'planifie' => 'bg-msn-gold-400/20 text-msn-gold-400',
        'en_cours' => 'bg-msn-terracotta-500/20 text-msn-terracotta-500',
        'termine' => 'bg-green-500/20 text-green-400',
    ];
@endphp

@section('content')
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-3xl font-bold text-msn-sea-900 sm:text-4xl">{{ $project->title }}</h1>
            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$project->status] }}">
                {{ $statusLabels[$project->status] }}
            </span>
            <x-content-status-badge :status="$project->content_status" />
        </div>

        <p class="mt-6 whitespace-pre-line text-msn-sea-700">{{ $project->description }}</p>

        <hr class="mt-10 border-msn-sand-200">

        <h2 class="mt-8 text-xl font-semibold text-msn-sea-900">
            Avis ({{ $project->approvedComments->count() }})
        </h2>

        <div class="mt-4 space-y-4">
            @forelse ($project->approvedComments as $comment)
                <div class="rounded-2xl border border-msn-sand-200 bg-white p-4">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold text-msn-sea-900">{{ $comment->author_name }}</p>
                        <p class="text-xs text-msn-sea-700/70">{{ $comment->created_at->format('d/m/Y') }}</p>
                    </div>
                    <p class="mt-2 text-sm text-msn-sea-700">{{ $comment->body }}</p>
                </div>
            @empty
                <p class="text-sm text-msn-sea-700">Aucun avis pour l'instant — soyez le premier à commenter.</p>
            @endforelse
        </div>

        @if (session('status'))
            <div class="mt-6 rounded-lg bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('projets.comments.store', $project->slug) }}" class="mt-6 space-y-3">
            @csrf

            @if ($errors->any())
                <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Votre nom</label>
                <input type="text" name="author_name" required maxlength="100" value="{{ old('author_name') }}"
                       class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-msn-sea-900">
            </div>
            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Votre avis</label>
                <textarea name="body" rows="3" required maxlength="2000"
                          class="mt-1 block w-full rounded-lg border-gray-300 bg-white text-msn-sea-900">{{ old('body') }}</textarea>
            </div>
            <p class="text-xs text-msn-sea-700/70">Votre commentaire sera visible après validation par l'équipe.</p>

            <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                Envoyer mon avis
            </button>
        </form>

        <a href="{{ route('projets.index') }}" class="mt-8 inline-block text-sm font-semibold text-msn-terracotta-500 hover:underline">
            &larr; Tous les projets communautaires
        </a>
    </div>
@endsection
