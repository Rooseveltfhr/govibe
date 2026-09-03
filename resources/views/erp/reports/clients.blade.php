@extends('erp.layouts.app')
@section('title','Rapport Clients')
@section('page-title','Rapport — Clients CRM')
@section('page-subtitle','Portefeuille clients et activité')

@section('content')
{{-- Filters --}}
<div class="content-card p-4 mb-5">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs font-semibold text-gray-500 mb-1">Année</label>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                @foreach($availableYears as $y)
                <option value="{{ $y }}" @selected($y==$year)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-gold px-4 py-2 text-sm">Filtrer</button>
        <a href="{{ route('erp.reports.pdf.clients', ['year'=>$year]) }}"
           class="flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-700 transition">
            <i class="fas fa-file-pdf"></i> Télécharger PDF
        </a>
        <a href="{{ route('erp.reports.index') }}" class="text-gray-500 text-sm hover:underline self-end pb-2">
            ← Retour aux rapports
        </a>
    </form>
</div>

{{-- KPIs --}}
<div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
    @foreach([
        ['Total clients','fa-users',$stats['total'],'#1e3a5f'],
        ['Clients actifs','fa-user-check',$stats['active'],'#059669'],
        ['Nouveaux en '.$year,'fa-user-plus',$stats['new_year'],'#DC2626'],
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

@if(!empty($byType))
<div class="content-card p-5 mb-6">
    <h3 class="font-semibold text-gray-800 mb-3 text-sm">Répartition par type</h3>
    <div class="flex flex-wrap gap-3">
        @foreach($byType as $type => $count)
        <div class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
            <span class="text-xs font-semibold text-gray-700 capitalize">{{ $type ?: 'Non défini' }}</span>
            <span class="text-xs font-extrabold text-red-600">{{ $count }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Clients table --}}
<div class="content-card overflow-hidden">
    <div class="p-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Liste des clients ({{ $clients->count() }})</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                <tr>
                    <th class="text-left px-4 py-3">Client</th>
                    <th class="text-left px-4 py-3">Type</th>
                    <th class="text-left px-4 py-3">Contact</th>
                    <th class="text-center px-4 py-3">Factures</th>
                    <th class="text-center px-4 py-3">Réservations</th>
                    <th class="text-right px-4 py-3">Total facturé</th>
                    <th class="text-center px-4 py-3">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($clients as $c)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">{{ $c->name }}</div>
                        <div class="text-xs text-gray-400 font-mono">{{ $c->reference_number }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-500 capitalize">{{ $c->type ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">
                        <div class="text-xs">{{ $c->email ?? '—' }}</div>
                        <div class="text-xs">{{ $c->phone ?? '' }}</div>
                    </td>
                    <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ $c->invoices_count }}</td>
                    <td class="px-4 py-3 text-center font-semibold text-gray-700">{{ $c->bookings_count }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900">
                        HTG {{ number_format($c->total_invoiced ?? 0,0,'.',',') }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($c->status==='active')
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-green-100 text-green-700">Actif</span>
                        @else
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-gray-100 text-gray-500">{{ ucfirst($c->status ?? 'inactif') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-10 text-center text-gray-400">Aucun client trouvé.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
