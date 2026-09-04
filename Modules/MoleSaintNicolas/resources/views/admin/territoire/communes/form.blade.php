@extends('layouts.admin')

@section('title', ($commune->exists ? 'Modifier' : 'Nouvelle').' commune — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $commune->exists ? "Modifier {$commune->name}" : 'Nouvelle commune' }}
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
              action="{{ $commune->exists ? route('admin.territoire.communes.update', $commune) : route('admin.territoire.communes.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($commune->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Arrondissement</label>
                <select name="arrondissement_id" required class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach ($arrondissements as $arrondissement)
                        <option value="{{ $arrondissement->id }}" @selected(old('arrondissement_id', $commune->arrondissement_id) == $arrondissement->id)>
                            {{ $arrondissement->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Nom</label>
                <input type="text" name="name" required value="{{ old('name', $commune->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (URL, laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $commune->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $commune->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Population</label>
                    <input type="number" name="population" value="{{ old('population', $commune->population) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Année (population)</label>
                    <input type="number" name="population_year" value="{{ old('population_year', $commune->population_year) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Latitude</label>
                    <input type="text" name="lat" value="{{ old('lat', $commune->lat) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Longitude</label>
                    <input type="text" name="lng" value="{{ old('lng', $commune->lng) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $commune->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $commune->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.territoire.communes.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
