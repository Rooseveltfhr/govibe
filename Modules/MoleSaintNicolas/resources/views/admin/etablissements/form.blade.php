@extends('layouts.admin')

@section('title', ($establishment->exists ? 'Modifier' : 'Nouvel').' établissement — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $establishment->exists ? "Modifier {$establishment->name}" : 'Nouvel établissement' }}
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
              action="{{ $establishment->exists ? route('admin.etablissements.update', $establishment) : route('admin.etablissements.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($establishment->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Type</label>
                <select name="type" required class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['hotel' => 'Hôtel', 'restaurant' => 'Restaurant', 'bar' => 'Bar'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $establishment->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Nom</label>
                <input type="text" name="name" required value="{{ old('name', $establishment->name) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $establishment->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="4" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $establishment->description) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Section communale (optionnel)</label>
                <select name="section_communale_id" class="mt-1 block w-full rounded-lg border-gray-300">
                    <option value="">— Aucune —</option>
                    @foreach ($sections as $section)
                        <option value="{{ $section->id }}" @selected(old('section_communale_id', $establishment->section_communale_id) == $section->id)>
                            {{ $section->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Adresse</label>
                <input type="text" name="address" value="{{ old('address', $establishment->address) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Latitude</label>
                    <input type="text" name="lat" value="{{ old('lat', $establishment->lat) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Longitude</label>
                    <input type="text" name="lng" value="{{ old('lng', $establishment->lng) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $establishment->phone) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $establishment->whatsapp) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">E-mail</label>
                    <input type="email" name="email" value="{{ old('email', $establishment->email) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Gamme de prix (ex : $, $$, $$$)</label>
                    <input type="text" name="price_range" value="{{ old('price_range', $establishment->price_range) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Type de cuisine (restaurant/bar)</label>
                    <input type="text" name="cuisine_type" value="{{ old('cuisine_type', $establishment->cuisine_type) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Équipements (hôtel)</label>
                <textarea name="amenities" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('amenities', $establishment->amenities) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Horaires</label>
                <textarea name="opening_hours" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('opening_hours', $establishment->opening_hours) }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $establishment->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $establishment->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.etablissements.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
