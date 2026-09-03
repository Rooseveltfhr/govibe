@extends('erp.layouts.app')
@section('title','Rapport Bootcamp AI')
@section('page-title','Rapport — Bootcamp AI 2026')
@section('page-subtitle','Inscriptions et statuts des participants')

@section('content')
{{-- Filters --}}
<div class="content-card p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Statut</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous</option>
                <option value="pending" @selected($status==='pending')>En attente</option>
                <option value="approved" @selected($status==='approved')>Approuvés</option>
                <option value="rejected" @selected($status==='rejected')>Refusés</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Module</label>
            <select name="module" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Tous les modules</option>
                @foreach($modules as $key => $m)
                <option value="{{ $key }}" @selected($module===$key)>{{ $m['label'] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-gold px-4 py-2 text-sm">Filtrer</button>
        <a href="{{ route('erp.reports.pdf.bootcamp', ['status'=>$status, 'module'=>$module]) }}"
           class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-700 transition">
            <i class="fas fa-file-pdf"></i> Télécharger PDF
        </a>
        <a href="{{ route('erp.reports.index') }}" class="text-gray-500 text-sm hover:underline self-end pb-2">
            ← Retour aux rapports
        </a>
    </form>
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Total inscrits','fa-users',$stats['total'],'#374151'],
        ['Approuvés','fa-user-check',$stats['approved'],'#059669'],
        ['En attente','fa-clock',$stats['pending'],'#d97706'],
        ['Revenus HTG','fa-coins','HTG '.number_format($stats['revenue'],0,'.',','),'#DC2626'],
    ] as [$label,$icon,$val,$color])
    <div class="content-card p-4 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $color }}15">
            <i class="fas {{ $icon }}" style="color:{{ $color }}"></i>
        </div>
        <div>
            <p class="text-xs text-gray-400">{{ $label }}</p>
            <p class="text-xl font-extrabold text-gray-800">{{ $val }}</p>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
    {{-- By module --}}
    <div class="content-card p-5">
        <h3 class="font-semibold text-gray-800 mb-4 text-sm flex items-center gap-2">
            <i class="fas fa-chart-pie text-red-500"></i> Répartition par module
        </h3>
        <div class="space-y-2">
            @foreach($modules as $key => $m)
            @php $count = $byModule[$key] ?? 0; @endphp
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 flex-1 truncate">{{ Str::limit($m['label'],30) }}</span>
                <span class="text-xs font-bold text-gray-700 w-6 text-right">{{ $count }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Revenue breakdown --}}
    <div class="content-card p-5 lg:col-span-2">
        <h3 class="font-semibold text-gray-800 mb-4 text-sm flex items-center gap-2">
            <i class="fas fa-coins text-yellow-500"></i> Revenus par devise (approuvés)
        </h3>
        <div class="grid grid-cols-2 gap-4">
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <p class="text-2xl font-extrabold text-gray-900">HTG {{ number_format($stats['revenue'],0,'.',',') }}</p>
                <p class="text-xs text-gray-500 mt-1">Paiements en Gourdes</p>
            </div>
            <div class="bg-gray-50 rounded-xl p-4 text-center">
                <p class="text-2xl font-extrabold text-gray-900">USD {{ number_format($stats['revenue_usd'],2,'.',',') }}</p>
                <p class="text-xs text-gray-500 mt-1">Paiements en Dollars</p>
            </div>
        </div>
    </div>
</div>

{{-- Registrations table --}}
<div class="content-card overflow-hidden">
    <div class="p-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800">Liste des inscrits ({{ $registrations->count() }})</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Nom complet</th>
                    <th class="text-left px-4 py-3">Email / Tél</th>
                    <th class="text-left px-4 py-3">Pays</th>
                    <th class="text-left px-4 py-3">Module</th>
                    <th class="text-right px-4 py-3">Montant</th>
                    <th class="text-center px-4 py-3">Statut</th>
                    <th class="text-left px-4 py-3">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($registrations as $r)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900">{{ $r->full_name }}</td>
                    <td class="px-4 py-3 text-gray-500">
                        <div>{{ $r->email }}</div>
                        <div class="text-xs">{{ $r->phone }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $r->country }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">{{ $r->module_label }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">
                        {{ $r->currency }} {{ number_format($r->amount,0,'.',',') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($r->status==='approved')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Approuvé</span>
                        @elseif($r->status==='rejected')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">Refusé</span>
                        @else
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-yellow-100 text-yellow-700">En attente</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-400 text-xs">{{ $r->created_at->format('d/m/Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">Aucune inscription trouvée.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
