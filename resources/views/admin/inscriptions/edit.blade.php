@extends('layouts.admin')

@section('title', 'Modifier participant')
@section('page-title', 'Modifier participant')

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center space-x-3 mb-6">
        <a href="{{ route('admin.inscriptions.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">{{ $inscription->nom_complet }}</h2>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 p-8">
        @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                @foreach($errors->all() as $error)
                    <p class="text-red-600 text-sm">• {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('admin.inscriptions.update', $inscription) }}" method="POST">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Nom complet *</label>
                    <input type="text" name="nom_complet" value="{{ old('nom_complet', $inscription->nom_complet) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Sexe *</label>
                    <select name="sexe" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                        <option value="Masculin" {{ old('sexe', $inscription->sexe) == 'Masculin' ? 'selected' : '' }}>Masculin</option>
                        <option value="Féminin" {{ old('sexe', $inscription->sexe) == 'Féminin' ? 'selected' : '' }}>Féminin</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Date de naissance *</label>
                    <input type="date" name="date_naissance" value="{{ old('date_naissance', $inscription->date_naissance?->format('Y-m-d')) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Téléphone *</label>
                    <input type="text" name="telephone" value="{{ old('telephone', $inscription->telephone) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email *</label>
                    <input type="email" name="email" value="{{ old('email', $inscription->email) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Département *</label>
                    <select name="departement" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                        @foreach(['Artibonite', 'Centre', 'Grand\'Anse', 'Nippes', 'Nord', 'Nord-Est', 'Nord-Ouest', 'Ouest', 'Sud', 'Sud-Est'] as $dept)
                            <option value="{{ $dept }}" {{ old('departement', $inscription->departement) == $dept ? 'selected' : '' }}>{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Ville *</label>
                    <input type="text" name="ville" value="{{ old('ville', $inscription->ville) }}" required
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Profession</label>
                    <input type="text" name="profession" value="{{ old('profession', $inscription->profession) }}"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Niveau d'étude *</label>
                    <select name="niveau_etude" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                        @foreach(['Primaire', 'Secondaire (1ère à 3ème)', 'Secondaire (4ème à Philo)', 'BAC', 'Licence (en cours)', 'Licence (obtenue)', 'Master', 'Doctorat', 'Formation professionnelle', 'Autodidacte'] as $niveau)
                            <option value="{{ $niveau }}" {{ old('niveau_etude', $inscription->niveau_etude) == $niveau ? 'selected' : '' }}>{{ $niveau }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Formation *</label>
                    <select name="formation_id" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                        @foreach($formations as $f)
                            <option value="{{ $f->id }}" {{ old('formation_id', $inscription->formation_id) == $f->id ? 'selected' : '' }}>{{ $f->nom }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Source d'information *</label>
                    <select name="source_info" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400">
                        @foreach(['Facebook', 'Instagram', 'WhatsApp', 'Twitter / X', 'LinkedIn', 'Bouche à oreille / Ami(e)', 'Affiche / Flyer', 'Radio / Télévision', 'Site web de GOVIBE', 'Autre'] as $source)
                            <option value="{{ $source }}" {{ old('source_info', $inscription->source_info) == $source ? 'selected' : '' }}>{{ $source }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Objectif après la formation</label>
                    <textarea name="objectif" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none">{{ old('objectif', $inscription->objectif) }}</textarea>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Attentes</label>
                    <textarea name="attentes" rows="3" class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-blue-400 resize-none">{{ old('attentes', $inscription->attentes) }}</textarea>
                </div>
            </div>

            <div class="flex gap-3 mt-6 pt-6 border-t border-gray-100">
                <button type="submit" class="px-6 py-3 text-white font-semibold rounded-xl text-sm transition-colors" style="background:#1e3a5f">
                    <i class="fas fa-save mr-2"></i>Enregistrer
                </button>
                <a href="{{ route('admin.inscriptions.index') }}" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl text-sm transition-colors">
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
