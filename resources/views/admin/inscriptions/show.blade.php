@extends('layouts.admin')

@section('title', $inscription->nom_complet)
@section('page-title', 'Détail participant')

@section('content')
<div class="max-w-3xl">
    <div class="flex items-center space-x-3 mb-6">
        <a href="{{ route('admin.inscriptions.index') }}" class="text-gray-400 hover:text-gray-600 transition-colors">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h2 class="text-xl font-bold text-gray-800">{{ $inscription->nom_complet }}</h2>
        <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full">{{ $inscription->numero_inscription }}</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Left column -->
        <div class="space-y-5">
            <!-- Avatar card -->
            <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center">
                <div class="w-20 h-20 rounded-full mx-auto flex items-center justify-center text-white text-3xl font-bold mb-3"
                     style="background:{{ $inscription->sexe === 'Masculin' ? '#1e3a5f' : '#7c3aed' }}">
                    {{ strtoupper(substr($inscription->nom_complet, 0, 1)) }}
                </div>
                <h3 class="font-bold text-gray-800">{{ $inscription->nom_complet }}</h3>
                <p class="text-sm text-gray-500">{{ $inscription->sexe }}</p>
                @if($inscription->qr_code)
                    <img src="data:image/png;base64,{{ $inscription->qr_code }}" alt="QR" class="w-28 h-28 mx-auto mt-4 border border-gray-200 rounded-xl p-1">
                @endif
            </div>

            <!-- Actions -->
            <div class="bg-white rounded-2xl border border-gray-200 p-4 space-y-2">
                <a href="{{ route('admin.inscriptions.edit', $inscription) }}"
                   class="flex items-center space-x-2 px-4 py-2.5 bg-yellow-50 hover:bg-yellow-100 text-yellow-800 rounded-xl text-sm font-medium transition-colors">
                    <i class="fas fa-pencil"></i><span>Modifier</span>
                </a>
                <a href="{{ route('admin.inscriptions.attestation', $inscription) }}"
                   class="flex items-center space-x-2 px-4 py-2.5 bg-green-50 hover:bg-green-100 text-green-800 rounded-xl text-sm font-medium transition-colors">
                    <i class="fas fa-certificate"></i><span>Télécharger attestation</span>
                </a>
                <form action="{{ route('admin.inscriptions.destroy', $inscription) }}" method="POST"
                      onsubmit="return confirm('Supprimer ce participant ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-full flex items-center space-x-2 px-4 py-2.5 bg-red-50 hover:bg-red-100 text-red-700 rounded-xl text-sm font-medium transition-colors">
                        <i class="fas fa-trash"></i><span>Supprimer</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- Right column -->
        <div class="md:col-span-2 space-y-5">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-semibold text-gray-800 mb-4 text-sm uppercase tracking-wider text-gray-500">Informations personnelles</h4>
                @php
                    $fields = [
                        ['Date de naissance', $inscription->date_naissance?->format('d/m/Y')],
                        ['Téléphone', $inscription->telephone],
                        ['Email', $inscription->email],
                        ['Département', $inscription->departement],
                        ['Ville', $inscription->ville],
                        ['Profession', $inscription->profession ?: '—'],
                        ['Niveau d\'étude', $inscription->niveau_etude],
                    ];
                @endphp
                <dl class="space-y-3">
                    @foreach($fields as [$label, $value])
                    <div class="flex justify-between py-2 border-b border-gray-50">
                        <dt class="text-sm text-gray-500">{{ $label }}</dt>
                        <dd class="text-sm font-medium text-gray-800 text-right">{{ $value }}</dd>
                    </div>
                    @endforeach
                </dl>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <h4 class="font-semibold text-gray-800 mb-4 text-sm uppercase tracking-wider text-gray-500">Formation</h4>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4">
                    <p class="font-semibold text-yellow-900">{{ $inscription->formation->nom ?? '—' }}</p>
                    @if($inscription->formation?->date_debut)
                        <p class="text-yellow-700 text-sm">{{ $inscription->formation->date_debut->format('d/m/Y') }}</p>
                    @endif
                </div>
                <p class="text-sm text-gray-500 mb-1">Source d'information</p>
                <p class="font-medium text-gray-800 text-sm mb-4">{{ $inscription->source_info }}</p>

                @if($inscription->objectif)
                <p class="text-sm text-gray-500 mb-1">Objectif après la formation</p>
                <p class="text-gray-700 text-sm bg-gray-50 rounded-lg p-3 mb-3">{{ $inscription->objectif }}</p>
                @endif

                @if($inscription->attentes)
                <p class="text-sm text-gray-500 mb-1">Attentes</p>
                <p class="text-gray-700 text-sm bg-gray-50 rounded-lg p-3">{{ $inscription->attentes }}</p>
                @endif
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <p class="text-xs text-gray-400">Inscrit le {{ $inscription->created_at->format('d/m/Y à H:i') }}</p>
                @if($inscription->present)
                    <p class="text-green-600 text-sm mt-1"><i class="fas fa-check-circle mr-1"></i>Présence confirmée le {{ $inscription->scanned_at?->format('d/m/Y à H:i') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
