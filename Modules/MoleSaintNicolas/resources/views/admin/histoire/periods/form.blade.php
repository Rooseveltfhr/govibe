@extends('layouts.admin')

@section('title', ($period->exists ? 'Modifier' : 'Nouvelle').' période — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $period->exists ? "Modifier {$period->name}" : 'Nouvelle période historique' }}
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
              action="{{ $period->exists ? route('admin.histoire.periods.update', $period) : route('admin.histoire.periods.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($period->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Nom</label>
                <input type="text" name="name" required value="{{ old('name', $period->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $period->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Année de début</label>
                    <input type="number" name="start_year" value="{{ old('start_year', $period->start_year) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Année de fin</label>
                    <input type="number" name="end_year" value="{{ old('end_year', $period->end_year) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Ordre d'affichage</label>
                    <input type="number" name="display_order" value="{{ old('display_order', $period->display_order ?? 0) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $period->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $period->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $period->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.histoire.periods.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
