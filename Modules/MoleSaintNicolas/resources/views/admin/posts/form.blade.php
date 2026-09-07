@extends('layouts.admin')

@section('title', ($post->exists ? 'Modifier' : 'Nouvel').' article — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $post->exists ? "Modifier {$post->title}" : 'Nouvel article' }}
        </h1>

        @if ($errors->any())
            <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($post->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Titre</label>
                <input type="text" name="title" required value="{{ old('title', $post->title) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $post->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Résumé (affiché dans la liste)</label>
                <input type="text" name="excerpt" maxlength="255" value="{{ old('excerpt', $post->excerpt) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Contenu</label>
                <textarea name="body" rows="12" required class="mt-1 block w-full rounded-lg border-gray-300 font-mono text-sm">{{ old('body', $post->body) }}</textarea>
                <p class="mt-1 text-xs text-msn-sea-700">HTML simple accepté (paragraphes, titres, listes).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Date de publication</label>
                <input type="datetime-local" name="published_at"
                       value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                <p class="mt-1 text-xs text-msn-sea-700">Laisser vide = brouillon, invisible du public. Une date future publie automatiquement à cette date.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $post->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $post->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.posts.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
