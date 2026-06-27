@extends('erp.layouts.app')

@section('title','Clients')
@section('page-title','CRM — Clients')
@section('page-subtitle','Gestion de la relation client')

@section('content')
{{-- Stats --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    @foreach([
        ['Total clients', $stats['total'], 'bi-people-fill','#1e3a5f','#dbeafe'],
        ['Clients actifs', $stats['active'], 'bi-person-check-fill','#059669','#d1fae5'],
        ['Prospects', $stats['prospect'], 'bi-person-plus-fill','#d97706','#fef3c7'],
    ] as [$label,$val,$icon,$color,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $val }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    {{-- Toolbar --}}
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Nom, email, téléphone..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 w-56 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="type" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les types</option>
                @foreach(['individual'=>'Particulier','company'=>'Entreprise','ngo'=>'ONG','government'=>'Gouvernement','university'=>'Université','association'=>'Association'] as $v => $l)
                <option value="{{ $v }}" {{ request('type')===$v ? 'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
            <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous statuts</option>
                <option value="active" {{ request('status')==='active'?'selected':'' }}>Actif</option>
                <option value="prospect" {{ request('status')==='prospect'?'selected':'' }}>Prospect</option>
                <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Inactif</option>
            </select>
            <button type="submit" class="btn-primary">Filtrer</button>
            @if(request()->hasAny(['search','type','status']))
            <a href="{{ route('erp.crm.clients') }}" class="px-4 py-2 text-sm text-gray-500 bg-gray-100 hover:bg-gray-200 rounded-xl transition-colors">✕</a>
            @endif
        </form>
        <a href="{{ route('erp.crm.clients.create') }}" class="btn-gold flex items-center gap-2 whitespace-nowrap">
            <i class="bi bi-plus-lg"></i> Nouveau client
        </a>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Client</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Type</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Contact</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Ville</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Statut</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase tracking-wider">Créé le</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($clients as $client)
                <tr class="table-row">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-3">
                            <div class="avatar avatar-navy w-9 h-9 text-sm flex-shrink-0">{{ strtoupper(substr($client->name,0,1)) }}</div>
                            <div>
                                <a href="{{ route('erp.crm.clients.show',$client) }}" class="text-sm font-semibold text-gray-800 dark:text-white hover:text-blue-600">{{ $client->name }}</a>
                                <p class="text-xs text-gray-400 font-mono">{{ $client->reference_number }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        @php $typeLabels=['individual'=>'Particulier','company'=>'Entreprise','ngo'=>'ONG','government'=>'Gouvernement','university'=>'Université','association'=>'Association']; @endphp
                        <span class="badge bg-blue-50 text-blue-700 text-xs">{{ $typeLabels[$client->type] ?? $client->type }}</span>
                    </td>
                    <td class="px-5 py-3.5">
                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $client->phone ?: '—' }}</p>
                        <p class="text-xs text-gray-400">{{ $client->email ?: '—' }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-600 dark:text-gray-400">{{ $client->city ?: '—' }}</td>
                    <td class="px-5 py-3.5">
                        <span class="badge text-xs {{ $client->status==='active' ? 'status-active' : ($client->status==='prospect' ? 'status-pending' : 'status-inactive') }}">
                            {{ $client->status }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-400">{{ $client->created_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('erp.crm.clients.show',$client) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="bi bi-eye text-xs"></i></a>
                            <a href="{{ route('erp.crm.clients.edit',$client) }}" class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors"><i class="bi bi-pencil text-xs"></i></a>
                            <form action="{{ route('erp.crm.clients.destroy',$client) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer {{ addslashes($client->name) }} ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="bi bi-trash text-xs"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">
                    <i class="bi bi-people text-4xl block mb-3 opacity-30"></i>
                    <p>Aucun client trouvé.</p>
                    <a href="{{ route('erp.crm.clients.create') }}" class="text-blue-600 text-sm mt-2 inline-block hover:underline">Ajouter le premier client</a>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($clients->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $clients->links() }}</div>
    @endif
</div>
@endsection
