@extends('layouts.admin')

@section('title', ($page->exists ? 'Modifier' : 'Nouvelle').' page — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $page->exists ? "Modifier {$page->title}" : 'Nouvelle page statique' }}
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
              action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($page->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Titre</label>
                <input type="text" name="title" required value="{{ old('title', $page->title) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $page->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
                <p class="mt-1 text-xs text-msn-sea-700">Ex. « a-propos » donne l'URL /a-propos. Changer le slug d'une page déjà liée dans le menu casse ce lien.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description (SEO, meta description)</label>
                <input type="text" name="meta_description" maxlength="255" value="{{ old('meta_description', $page->meta_description) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Contenu</label>
                <textarea name="body" rows="14" required class="mt-1 block w-full rounded-lg border-gray-300 font-mono text-sm">{{ old('body', $page->body) }}</textarea>
                <p class="mt-1 text-xs text-msn-sea-700">HTML simple accepté (paragraphes, titres, listes) — affiché tel quel sur la page publique.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $page->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $page->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.pages.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
