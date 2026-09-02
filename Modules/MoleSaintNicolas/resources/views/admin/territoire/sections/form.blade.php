@extends('layouts.admin')

@section('title', ($section->exists ? 'Modifier' : 'Nouvelle').' section communale — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $section->exists ? "Modifier {$section->name}" : 'Nouvelle section communale' }}
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
              action="{{ $section->exists ? route('admin.territoire.sections.update', $section) : route('admin.territoire.sections.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($section->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Commune</label>
                <select name="commune_id" required class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach ($communes as $commune)
                        <option value="{{ $commune->id }}" @selected(old('commune_id', $section->commune_id) == $commune->id)>
                            {{ $commune->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Nom</label>
                <input type="text" name="name" required value="{{ old('name', $section->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (URL, laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $section->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $section->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Population</label>
                    <input type="number" name="population" value="{{ old('population', $section->population) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Année (population)</label>
                    <input type="number" name="population_year" value="{{ old('population_year', $section->population_year) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Latitude</label>
                    <input type="text" name="lat" value="{{ old('lat', $section->lat) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Longitude</label>
                    <input type="text" name="lng" value="{{ old('lng', $section->lng) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $section->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $section->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.territoire.sections.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
