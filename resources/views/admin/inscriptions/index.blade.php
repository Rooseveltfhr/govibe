@extends('layouts.admin')

@section('title', 'Participants')
@section('page-title', 'Gestion des participants')

@section('content')

<!-- Filters -->
<div class="bg-white rounded-2xl border border-gray-200 p-5 mb-6">
    <form method="GET" action="{{ route('admin.inscriptions.index') }}" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Rechercher</label>
            <div class="relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom, email, téléphone..."
                       class="w-full border border-gray-300 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:border-blue-400">
            </div>
        </div>

        <div class="min-w-44">
            <label class="block text-xs font-medium text-gray-500 mb-1">Formation</label>
            <select name="formation_id" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400">
                <option value="">Toutes les formations</option>
                @foreach($formations as $f)
                    <option value="{{ $f->id }}" {{ request('formation_id') == $f->id ? 'selected' : '' }}>{{ $f->nom }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-36">
            <label class="block text-xs font-medium text-gray-500 mb-1">Département</label>
            <select name="departement" class="w-full border border-gray-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:border-blue-400">
                <option value="">Tous</option>
                @foreach($departements as $d)
                    <option value="{{ $d }}" {{ request('departement') == $d ? 'selected' : '' }}>{{ $d }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2.5 text-sm font-medium text-white rounded-xl transition-colors" style="background:#1e3a5f">
                <i class="fas fa-filter mr-1"></i>Filtrer
            </button>
            @if(request()->hasAny(['search', 'formation_id', 'departement']))
                <a href="{{ route('admin.inscriptions.index') }}" class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">
                    <i class="fas fa-times mr-1"></i>Effacer
                </a>
            @endif
        </div>
    </form>
</div>

<!-- Action buttons -->
<div class="flex flex-wrap gap-3 mb-5">
    <a href="{{ route('admin.inscriptions.export.excel', request()->query()) }}"
       class="flex items-center space-x-2 bg-green-600 hover:bg-green-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
        <i class="fas fa-file-excel"></i><span>Excel</span>
    </a>
    <a href="{{ route('admin.inscriptions.export.csv', request()->query()) }}"
       class="flex items-center space-x-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
        <i class="fas fa-file-csv"></i><span>CSV</span>
    </a>
    <a href="{{ route('admin.inscriptions.print', request()->query()) }}" target="_blank"
       class="flex items-center space-x-2 bg-gray-600 hover:bg-gray-700 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition-colors">
        <i class="fas fa-print"></i><span>Imprimer</span>
    </a>
</div>

<!-- Table -->
<div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">
            Participants
            <span class="ml-2 bg-gray-100 text-gray-600 text-xs font-medium px-2.5 py-0.5 rounded-full">{{ $inscriptions->total() }}</span>
        </h3>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">N°</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Participant</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Contact</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Localisation</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Formation</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($inscriptions as $inscription)
                <tr class="table-row">
                    <td class="px-5 py-4">
                        <span class="text-xs font-mono font-bold text-blue-700 bg-blue-50 px-2 py-1 rounded">{{ $inscription->numero_inscription }}</span>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-sm font-bold shrink-0"
                                 style="background:{{ $inscription->sexe === 'Masculin' ? '#1e3a5f' : '#7c3aed' }}">
                                {{ strtoupper(substr($inscription->nom_complet, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 text-sm">{{ $inscription->nom_complet }}</p>
                                <span class="text-xs {{ $inscription->sexe === 'Masculin' ? 'text-blue-600' : 'text-purple-600' }}">{{ $inscription->sexe }}</span>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-sm text-gray-700">{{ $inscription->telephone }}</p>
                        <p class="text-xs text-gray-400">{{ $inscription->email }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <p class="text-sm text-gray-700">{{ $inscription->ville }}</p>
                        <p class="text-xs text-gray-400">{{ $inscription->departement }}</p>
                    </td>
                    <td class="px-5 py-4">
                        <span class="text-xs font-medium text-gray-700 bg-yellow-50 border border-yellow-200 px-2 py-1 rounded-lg">
                            {{ Str::limit($inscription->formation->nom ?? '—', 25) }}
                        </span>
                    </td>
                    <td class="px-5 py-4 text-sm text-gray-500">{{ $inscription->created_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-4">
                        <div class="flex items-center space-x-1">
                            <a href="{{ route('admin.inscriptions.show', $inscription) }}"
                               class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Voir">
                                <i class="fas fa-eye text-xs"></i>
                            </a>
                            <a href="{{ route('admin.inscriptions.edit', $inscription) }}"
                               class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="Modifier">
                                <i class="fas fa-pencil text-xs"></i>
                            </a>
                            <a href="{{ route('admin.inscriptions.attestation', $inscription) }}"
                               class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors" title="Attestation PDF">
                                <i class="fas fa-certificate text-xs"></i>
                            </a>
                            <form action="{{ route('admin.inscriptions.destroy', $inscription) }}" method="POST" class="inline"
                                  onsubmit="return confirm('Supprimer {{ addslashes($inscription->nom_complet) }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="Supprimer">
                                    <i class="fas fa-trash text-xs"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-400">
                        <i class="fas fa-users text-3xl mb-3 block opacity-30"></i>
                        Aucun participant trouvé.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($inscriptions->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $inscriptions->links() }}
    </div>
    @endif
</div>

@endsection
