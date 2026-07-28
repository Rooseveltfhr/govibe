@extends('erp.layouts.app')
@section('title','Inventaire')
@section('page-title','Inventaire')
@section('page-subtitle','Gestion des stocks GOVIBE')

@section('content')
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    @foreach([['Articles',$stats['total'],'bi-box-seam-fill','#1e3a5f','#dbeafe'],['Stock faible',$stats['lowStock'],'bi-exclamation-triangle-fill','#dc2626','#fee2e2'],['Valeur stock','HTG '.number_format($stats['value'],0,'.',','),'bi-cash-stack','#059669','#d1fae5']] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-800 dark:text-white">Articles en stock</h3>
        <button class="btn-gold text-sm opacity-60 cursor-not-allowed" disabled><i class="bi bi-plus-lg mr-1"></i> Ajouter (bientôt)</button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50 dark:bg-slate-800/60">
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Article</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Catégorie</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Stock</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Min.</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Prix unit.</th>
                    <th class="text-left px-5 py-3 text-xs font-semibold text-gray-400 uppercase">Statut</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                @forelse($items as $item)
                <tr class="table-row">
                    <td class="px-5 py-3.5 text-sm font-medium text-gray-800 dark:text-white">{{ $item->name }}</td>
                    <td class="px-5 py-3.5 text-sm text-gray-500">{{ $item->category ?? '—' }}</td>
                    <td class="px-5 py-3.5 text-sm font-semibold {{ $item->quantity <= $item->min_quantity ? 'text-red-600' : 'text-gray-800 dark:text-white' }}">
                        {{ $item->quantity }}
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-400">{{ $item->min_quantity }}</td>
                    <td class="px-5 py-3.5 text-sm text-gray-600">HTG {{ number_format($item->unit_price,2,'.',',') }}</td>
                    <td class="px-5 py-3.5">
                        @if($item->quantity <= $item->min_quantity)
                        <span class="badge text-xs bg-red-100 text-red-700">Stock faible</span>
                        @else
                        <span class="badge text-xs bg-green-100 text-green-700">OK</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-5 py-12 text-center text-gray-400">
                    <i class="bi bi-box-seam text-4xl block mb-3 opacity-30"></i>
                    <p>Aucun article en inventaire.</p>
                </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($items,'hasPages') && $items->hasPages())
    <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $items->links() }}</div>
    @endif
</div>
@endsection
