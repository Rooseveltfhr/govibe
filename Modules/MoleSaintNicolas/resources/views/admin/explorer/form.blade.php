@extends('layouts.admin')

@section('title', ($activity->exists ? 'Modifier' : 'Nouvelle').' activité — Administration')

@section('content')
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-2xl font-bold text-msn-sea-900">
            {{ $activity->exists ? "Modifier {$activity->title}" : 'Nouvelle activité' }}
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
              action="{{ $activity->exists ? route('admin.explorer.update', $activity) : route('admin.explorer.store') }}"
              class="mt-6 space-y-4">
            @csrf
            @if ($activity->exists) @method('PUT') @endif

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Titre</label>
                <input type="text" name="title" required value="{{ old('title', $activity->title) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Slug (laisser vide pour générer automatiquement)</label>
                <input type="text" name="slug" value="{{ old('slug', $activity->slug) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Catégorie (randonnée, plongée, pêche...)</label>
                <input type="text" name="category" value="{{ old('category', $activity->category) }}"
                       class="mt-1 block w-full rounded-lg border-gray-300">
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Description</label>
                <textarea name="description" rows="4" required class="mt-1 block w-full rounded-lg border-gray-300">{{ old('description', $activity->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Durée</label>
                    <input type="text" name="duration" value="{{ old('duration', $activity->duration) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300" placeholder="2 heures">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Tarif</label>
                    <input type="text" name="price_range" value="{{ old('price_range', $activity->price_range) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">Téléphone</label>
                    <input type="text" name="phone" value="{{ old('phone', $activity->phone) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-medium text-msn-sea-900">WhatsApp</label>
                    <input type="text" name="whatsapp" value="{{ old('whatsapp', $activity->whatsapp) }}"
                           class="mt-1 block w-full rounded-lg border-gray-300" placeholder="+509...">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Statut du contenu</label>
                <select name="content_status" class="mt-1 block w-full rounded-lg border-gray-300">
                    @foreach (['needs_review' => 'À vérifier', 'submitted' => 'Soumis', 'verified' => 'Vérifié'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('content_status', $activity->content_status ?? 'needs_review') === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-msn-sea-900">Source / note interne</label>
                <textarea name="source_note" rows="2" class="mt-1 block w-full rounded-lg border-gray-300">{{ old('source_note', $activity->source_note) }}</textarea>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="rounded-lg bg-msn-terracotta-500 px-5 py-2.5 font-semibold text-white hover:bg-msn-terracotta-600">
                    Enregistrer
                </button>
                <a href="{{ route('admin.explorer.index') }}" class="rounded-lg border border-msn-sea-500 px-5 py-2.5 font-semibold text-msn-sea-900">
                    Annuler
                </a>
            </div>
        </form>
    </div>
@endsection
