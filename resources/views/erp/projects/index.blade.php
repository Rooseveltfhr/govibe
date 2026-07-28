@extends('erp.layouts.app')
@section('title','Projets')
@section('page-title','Gestion des projets')
@section('page-subtitle','Tous les projets GOVIBE')

@section('content')
<div class="grid grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Total', $stats['total'], 'bi-kanban-fill','#1e3a5f','#dbeafe'],
        ['Actifs', $stats['active'], 'bi-play-circle-fill','#059669','#d1fae5'],
        ['Terminés', $stats['completed'], 'bi-check-circle-fill','#0891b2','#e0f2fe'],
        ['En attente', $stats['on_hold'], 'bi-pause-circle-fill','#d97706','#fef3c7'],
    ] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom du projet..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 w-48 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="">Tous statuts</option>
                @foreach(['planning'=>'Planification','active'=>'Actif','on_hold'=>'En attente','completed'=>'Terminé','cancelled'=>'Annulé'] as $v=>$l)
                <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
        </form>
        <div class="flex items-center gap-2">
            <a href="{{ route('erp.projects.kanban') }}"
               class="flex items-center gap-1.5 px-3 py-2 text-sm text-gray-500 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                <i class="bi bi-kanban"></i> Kanban
            </a>
            <a href="{{ route('erp.projects.create') }}" class="btn-gold flex items-center gap-2">
                <i class="bi bi-plus-lg"></i> Nouveau projet
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Projet</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Client</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Priorité</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Statut</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Progrès</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Échéance</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($projects as $project)
                <tr class="table-row">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('erp.projects.show',$project) }}" class="text-sm font-semibold text-gray-800 dark:text-white hover:text-blue-600">{{ $project->name }}</a>
                        <p class="text-xs text-gray-400 font-mono">{{ $project->reference }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-400">{{ $project->client->name ?? '—' }}</td>
                    <td class="px-5 py-3.5">
                        @php $pColors=['low'=>'text-green-600','medium'=>'text-yellow-600','high'=>'text-orange-600','critical'=>'text-red-600']; @endphp
                        <span class="text-xs font-semibold {{ $pColors[$project->priority] ?? 'text-gray-500' }}">
                            <i class="bi bi-flag-fill mr-1"></i>{{ ucfirst($project->priority) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        @php $sColors=['planning'=>'bg-gray-100 text-gray-600','active'=>'bg-blue-100 text-blue-700','on_hold'=>'bg-yellow-100 text-yellow-700','completed'=>'bg-green-100 text-green-700','cancelled'=>'bg-red-100 text-red-700']; @endphp
                        <span class="badge text-xs {{ $sColors[$project->status] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst(str_replace('_',' ',$project->status)) }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 w-32">
                        <div class="flex items-center gap-2">
                            <div class="progress-bar flex-1"><div class="progress-fill" style="width:{{ $project->progress }}%"></div></div>
                            <span class="text-xs text-gray-400 w-8 text-right">{{ $project->progress }}%</span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-500">{{ $project->end_date?->format('d/m/Y') ?? '—' }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('erp.projects.show',$project) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="bi bi-eye text-xs"></i></a>
                            <a href="{{ route('erp.projects.edit',$project) }}" class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors"><i class="bi bi-pencil text-xs"></i></a>
                            <form action="{{ route('erp.projects.destroy',$project) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="bi bi-trash text-xs"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">
                    <i class="bi bi-kanban text-4xl block mb-3 opacity-30"></i>
                    <p>Aucun projet. <a href="{{ route('erp.projects.create') }}" class="text-blue-600 hover:underline">Créer le premier</a></p>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($projects->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $projects->links() }}</div>
    @endif
</div>
@endsection
