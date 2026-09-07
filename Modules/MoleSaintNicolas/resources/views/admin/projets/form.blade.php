@extends('layouts.admin')

@section('title', ($project->exists ? 'Modifier' : 'Nouveau').' projet — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $project->exists ? "Modifier {$project->title}" : 'Nouveau projet communautaire' }}
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
              action="{{ $project->exists ? route('admin.projets.update', $project) : route('admin.projets.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($project->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Titre</label>
                <input type="text" name="title" required value="{{ old('title', $project->title) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $project->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Avancement</label>
                <select name="status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['planifie' => 'Planifié', 'en_cours' => 'En cours', 'termine' => 'Terminé'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $project->status ?? 'planifie') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="6" required class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $project->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $project->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $project->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.projets.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
