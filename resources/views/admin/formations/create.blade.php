@extends('layouts.admin')

@section('title', 'Nouvelle formation')
@section('page-title', 'Nouvelle formation')

@section('content')
<div class="max-w-2xl">
    <div class="flex items-center space-x-3 mb-6">
        <a href="{{ route('admin.formations.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">Créer une formation</h2>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-8">
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                @foreach($errors->all() as $error)
                    <p class="text-red-600 text-sm">• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('admin.formations.store') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom de la formation *</label>
                <input type="text" name="nom" value="{{ old('nom') }}" required
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400"
                       placeholder="Ex: Marketing Digital & Réseaux Sociaux">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
                <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none"
                          placeholder="Décrivez le contenu et les objectifs de cette formation...">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Date de début</label>
                    <input type="date" name="date_debut" value="{{ old('date_debut') }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Date de fin</label>
                    <input type="date" name="date_fin" value="{{ old('date_fin') }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Lieu</label>
                <input type="text" name="lieu" value="{{ old('lieu') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400"
                       placeholder="Ex: Port-au-Prince, Cap-Haïtien...">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Lien groupe WhatsApp</label>
                <input type="url" name="whatsapp_link" value="{{ old('whatsapp_link') }}"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400"
                       placeholder="https://chat.whatsapp.com/...">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre maximum de participants *</label>
                <input type="number" name="max_participants" value="{{ old('max_participants', 50) }}" required min="1"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
            </div>

            <div class="flex items-center space-x-3">
                <input type="checkbox" name="active" id="active" value="1" {{ old('active', true) ? 'checked' : '' }}
                       class="w-4 h-4 rounded">
                <label for="active" class="text-sm font-medium text-gray-700">Formation active (visible sur le formulaire public)</label>
            </div>

            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-3 text-white font-semibold rounded-xl text-sm transition-colors" style="background:#1e3a5f">
                    <i class="fas fa-plus mr-2"></i>Créer la formation
                </button>
                <a href="{{ route('admin.formations.index') }}" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition-colors">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
