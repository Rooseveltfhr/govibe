@extends('erp.layouts.app')
@section('title','Factures')
@section('page-title','Factures')
@section('page-subtitle','Gestion de la facturation GOVIBE')

@section('content')
@php
$statCards = [
    ['Total',    $stats['total'],    'bi-receipt',          '#1e3a5f','#dbeafe'],
    ['Payées',   $stats['paid'],     'bi-check-circle-fill','#059669','#d1fae5'],
    ['En attente',$stats['pending'], 'bi-clock-fill',       '#d97706','#fef3c7'],
    ['Échus',    $stats['overdue'],  'bi-exclamation-circle-fill','#dc2626','#fee2e2'],
];
@endphp

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach($statCards as [$l,$v,$i,$c,$bg])
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
                <input type="text" name="search" value="{{ request('search') }}" placeholder="N° ou client..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-blue-400 w-44 dark:bg-slate-700 dark:border-slate-600 dark:text-white">
            </div>
            <select name="status" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-white">
                <option value="">Tous statuts</option>
                @foreach(['draft'=>'Brouillon','sent'=>'Envoyée','paid'=>'Payée','overdue'=>'Échus','cancelled'=>'Annulée'] as $v=>$l)
                <option value="{{ $v }}" {{ request('status')===$v?'selected':'' }}>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
        </form>
        <a href="{{ route('erp.invoices.create') }}" class="btn-gold flex items-center gap-2">
            <i class="bi bi-plus-lg"></i> Nouvelle facture
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">N° Facture</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Client</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Montant</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Statut</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Date</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Échéance</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($invoices as $inv)
                @php
                $sc=['draft'=>'bg-gray-100 text-gray-600','sent'=>'bg-blue-100 text-blue-700','paid'=>'bg-green-100 text-green-700','overdue'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500'];
                $sl=['draft'=>'Brouillon','sent'=>'Envoyée','paid'=>'Payée','overdue'=>'Échus','cancelled'=>'Annulée'];
                @endphp
                <tr class="table-row">
                    <td class="px-5 py-3.5">
                        <a href="{{ route('erp.invoices.show',$inv) }}" class="text-sm font-mono font-semibold text-blue-600 hover:underline">{{ $inv->reference }}</a>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300">{{ $inv->client->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm font-semibold text-gray-800 dark:text-white">HTG {{ number_format($inv->total,0,'.',',') }}</td>
                    <td class="px-5 py-3.5">
                        <span class="badge text-xs {{ $sc[$inv->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $sl[$inv->status] ?? $inv->status }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-xs text-gray-400">{{ $inv->created_at->format('d/m/Y') }}</td>
                    <td class="px-5 py-3.5 text-xs {{ $inv->due_date && $inv->due_date->isPast() && $inv->status!=='paid' ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                        {{ $inv->due_date?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-1">
                            <a href="{{ route('erp.invoices.show',$inv) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"><i class="bi bi-eye text-xs"></i></a>
                            <a href="{{ route('erp.invoices.pdf',$inv) }}" class="p-1.5 text-gray-400 hover:text-green-600 hover:bg-green-50 rounded-lg transition-colors"><i class="bi bi-download text-xs"></i></a>
                            @if($inv->status !== 'paid')
                            <form action="{{ route('erp.invoices.mark-paid',$inv) }}" method="POST" class="inline">
                                @csrf @method('PATCH')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors" title="Marquer payée">
                                    <i class="bi bi-check-circle text-xs"></i>
                                </button>
                            </form>
                            @endif
                            <form action="{{ route('erp.invoices.destroy',$inv) }}" method="POST" class="inline" onsubmit="return confirm('Supprimer ?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"><i class="bi bi-trash text-xs"></i></button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400">
                    <i class="bi bi-receipt text-4xl block mb-3 opacity-30"></i>
                    <p>Aucune facture. <a href="{{ route('erp.invoices.create') }}" class="text-blue-600 hover:underline">Créer la première</a></p>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($invoices->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $invoices->links() }}</div>
    @endif
</div>
@endsection
