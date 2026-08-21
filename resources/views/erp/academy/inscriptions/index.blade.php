@extends('erp.layouts.app')
@section('title','Inscriptions Academy')
@section('page-title','Inscriptions — Formations')
@section('page-subtitle','Gestion des participants aux formations GOVIBE')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Total inscrits',  $stats['total'],     'bi-people-fill',        '#1e3a5f','#dbeafe'],
        ['Présents',        $stats['presents'],  'bi-person-check-fill',  '#059669','#d1fae5'],
        ['Absents',         $stats['absents'],   'bi-person-x-fill',      '#dc2626','#fee2e2'],
        ['Formations',      $stats['formations'],'bi-mortarboard-fill',   '#d4a017','#fef9c3'],
    ] as [$label,$val,$icon,$color,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ number_format($val) }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Filters --}}
<div class="content-card mb-4">
    <form method="GET" action="{{ route('erp.academy.inscriptions.index') }}"
          class="flex flex-wrap gap-3 p-4 items-end">

        <div class="flex-1 min-w-48">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Rechercher</label>
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Nom, email, téléphone, numéro…"
                       class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
        </div>

        <div class="min-w-44">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Formation</label>
            <select name="formation_id"
                    class="w-full py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="">Toutes</option>
                @foreach($formations as $f)
                <option value="{{ $f->id }}" {{ request('formation_id') == $f->id ? 'selected' : '' }}>
                    {{ $f->nom }}
                </option>
                @endforeach
            </select>
        </div>

        <div class="min-w-36">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Département</label>
            <select name="departement"
                    class="w-full py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="">Tous</option>
                @foreach($departements as $dep)
                <option value="{{ $dep }}" {{ request('departement') === $dep ? 'selected' : '' }}>{{ $dep }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-36">
            <label class="block text-xs text-gray-500 mb-1 font-medium">Présence</label>
            <select name="presence"
                    class="w-full py-2 px-3 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-blue-400 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="">Tous</option>
                <option value="present" {{ request('presence') === 'present' ? 'selected' : '' }}>Présents</option>
                <option value="absent"  {{ request('presence') === 'absent'  ? 'selected' : '' }}>Absents</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="btn-primary text-sm px-4 py-2">
                <i class="bi bi-search mr-1"></i> Filtrer
            </button>
            @if(request()->hasAny(['search','formation_id','departement','presence']))
            <a href="{{ route('erp.academy.inscriptions.index') }}"
               class="px-4 py-2 text-sm border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 dark:border-slate-600 dark:text-gray-300 dark:hover:bg-slate-700">
                <i class="bi bi-x-lg"></i>
            </a>
            @endif
        </div>
    </form>
</div>

{{-- Table --}}
<div class="content-card">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <div>
            <h3 class="font-semibold text-gray-800 dark:text-white">Liste des inscrits</h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ $inscriptions->total() }} participant(s)</p>
        </div>
        <a href="{{ route('erp.academy.index') }}"
           class="text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 flex items-center gap-1">
            <i class="bi bi-arrow-left"></i> Academy
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Numéro</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Participant</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Formation</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Département</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Téléphone</th>
                    <th class="text-center px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Présence</th>
                    <th class="text-right px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/50">
                @forelse($inscriptions as $ins)
                <tr class="table-row">
                    <td class="px-5 py-3">
                        <span class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $ins->numero_inscription }}</span>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-navy w-8 h-8 text-xs flex-shrink-0">
                                {{ strtoupper(substr($ins->nom_complet, 0, 1)) }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 dark:text-white">{{ $ins->nom_complet }}</p>
                                <p class="text-xs text-gray-400">{{ $ins->email ?? '—' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3">
                        <p class="text-gray-700 dark:text-gray-300 text-sm">{{ $ins->formation?->nom ?? '—' }}</p>
                        @if($ins->formation?->date_debut)
                        <p class="text-xs text-gray-400">{{ $ins->formation->date_debut->format('d/m/Y') }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell">
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $ins->departement ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3 hidden lg:table-cell">
                        <span class="text-xs text-gray-600 dark:text-gray-400">{{ $ins->telephone ?? '—' }}</span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        <form method="POST" action="{{ route('erp.academy.inscriptions.presence', $ins) }}">
                            @csrf
                            <button type="submit"
                                    class="badge text-xs cursor-pointer border-0 {{ $ins->present ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }} hover:opacity-80 transition-opacity">
                                <i class="bi {{ $ins->present ? 'bi-check-circle-fill' : 'bi-circle' }} mr-1"></i>
                                {{ $ins->present ? 'Présent' : 'Absent' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex items-center justify-end gap-1">
                            <a href="{{ route('erp.academy.inscriptions.show', $ins) }}"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
                               title="Voir détails">
                                <i class="bi bi-eye text-sm"></i>
                            </a>
                            <a href="{{ route('erp.academy.inscriptions.attestation', $ins) }}"
                               class="p-1.5 rounded-lg text-gray-400 hover:text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20 transition-colors"
                               title="Attestation PDF">
                                <i class="bi bi-file-earmark-pdf text-sm"></i>
                            </a>
                            <form method="POST" action="{{ route('erp.academy.inscriptions.destroy', $ins) }}"
                                  onsubmit="return confirm('Supprimer l\'inscription de {{ addslashes($ins->nom_complet) }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit"
                                        class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                        title="Supprimer">
                                    <i class="bi bi-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-5 py-16 text-center text-gray-400">
                        <i class="bi bi-people text-4xl block mb-3 opacity-30"></i>
                        <p class="text-sm font-medium">Aucune inscription trouvée</p>
                        @if(request()->hasAny(['search','formation_id','departement','presence']))
                        <a href="{{ route('erp.academy.inscriptions.index') }}"
                           class="text-xs text-blue-600 hover:underline mt-1 inline-block">Réinitialiser les filtres</a>
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($inscriptions->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">
        {{ $inscriptions->links() }}
    </div>
    @endif
</div>

@endsection
