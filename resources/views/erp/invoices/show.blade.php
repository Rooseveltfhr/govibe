@extends('erp.layouts.app')
@section('title','Facture — '.$invoice->reference)
@section('page-title','Facture')
@section('page-subtitle',$invoice->reference)

@section('content')
@php
$sc=['draft'=>'bg-gray-100 text-gray-600','sent'=>'bg-blue-100 text-blue-700','paid'=>'bg-green-100 text-green-700','overdue'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500'];
$sl=['draft'=>'Brouillon','sent'=>'Envoyée','paid'=>'Payée','overdue'=>'Échus','cancelled'=>'Annulée'];
@endphp

<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('erp.invoices.index') }}" class="p-2 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-xl">
        <i class="bi bi-arrow-left text-gray-500"></i>
    </a>
    <div class="flex-1 flex items-center gap-3">
        <h2 class="font-bold text-gray-800 dark:text-white text-xl font-mono">{{ $invoice->reference }}</h2>
        <span class="badge text-xs {{ $sc[$invoice->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $sl[$invoice->status] ?? $invoice->status }}</span>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('erp.invoices.pdf',$invoice) }}" class="flex items-center gap-2 px-4 py-2 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition-colors">
            <i class="bi bi-download"></i> PDF
        </a>
        @if($invoice->status !== 'paid')
        <form action="{{ route('erp.invoices.mark-paid',$invoice) }}" method="POST">
            @csrf @method('PATCH')
            <button type="submit" class="btn-gold text-sm">
                <i class="bi bi-check-circle mr-1"></i> Marquer payée
            </button>
        </form>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-5">

        {{-- Invoice header --}}
        <div class="content-card p-6">
            <div class="flex items-start justify-between mb-6">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background:#1e3a5f">
                            <span class="text-yellow-400 font-bold text-xs">G</span>
                        </div>
                        <span class="font-bold text-gray-800 dark:text-white">GOVIBE Innovation Hub</span>
                    </div>
                    <p class="text-xs text-gray-400">Port-au-Prince, Haïti</p>
                    <p class="text-xs text-gray-400">govibeht@gmail.com</p>
                </div>
                <div class="text-right">
                    <p class="text-2xl font-extrabold text-gray-800 dark:text-white font-mono">{{ $invoice->reference }}</p>
                    <p class="text-xs text-gray-400 mt-1">Émise le {{ $invoice->issued_date->format('d/m/Y') }}</p>
                    <p class="text-xs {{ $invoice->due_date->isPast() && $invoice->status !== 'paid' ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                        Échéance {{ $invoice->due_date->format('d/m/Y') }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-6 py-4 border-y border-gray-100 dark:border-slate-700 mb-6">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Facturé à</p>
                    <p class="font-semibold text-gray-800 dark:text-white">{{ $invoice->client->name ?? '—' }}</p>
                    @if($invoice->client?->email)<p class="text-sm text-gray-500">{{ $invoice->client->email }}</p>@endif
                    @if($invoice->client?->phone)<p class="text-sm text-gray-500">{{ $invoice->client->phone }}</p>@endif
                    @if($invoice->client?->city)<p class="text-sm text-gray-500">{{ $invoice->client->city }}</p>@endif
                </div>
                @if($invoice->project)
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase mb-1">Projet</p>
                    <a href="{{ route('erp.projects.show',$invoice->project) }}" class="font-semibold text-blue-600 hover:underline">{{ $invoice->project->name }}</a>
                    <p class="text-sm text-gray-400 font-mono">{{ $invoice->project->reference }}</p>
                </div>
                @endif
            </div>

            {{-- Items table --}}
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 dark:bg-slate-800/60">
                        <th class="text-left py-2 px-3 text-xs font-semibold text-gray-400 uppercase rounded-l-lg">Description</th>
                        <th class="text-center py-2 px-3 text-xs font-semibold text-gray-400 uppercase">Qté</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold text-gray-400 uppercase">Prix unit.</th>
                        <th class="text-right py-2 px-3 text-xs font-semibold text-gray-400 uppercase rounded-r-lg">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-slate-700">
                    @foreach($invoice->items as $item)
                    <tr>
                        <td class="py-3 px-3 text-gray-700 dark:text-gray-300">{{ $item->description }}</td>
                        <td class="py-3 px-3 text-center text-gray-500">{{ $item->quantity }}</td>
                        <td class="py-3 px-3 text-right text-gray-500">HTG {{ number_format($item->unit_price,2,'.',',') }}</td>
                        <td class="py-3 px-3 text-right font-semibold text-gray-800 dark:text-white">HTG {{ number_format($item->total,2,'.',',') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Totals --}}
            <div class="mt-4 flex justify-end">
                <div class="w-64 space-y-1.5 text-sm">
                    <div class="flex justify-between text-gray-500">
                        <span>Sous-total</span>
                        <span>HTG {{ number_format($invoice->subtotal,2,'.',',') }}</span>
                    </div>
                    @if($invoice->tax_rate > 0)
                    <div class="flex justify-between text-gray-500">
                        <span>Taxe ({{ $invoice->tax_rate }}%)</span>
                        <span>HTG {{ number_format($invoice->tax_amount,2,'.',',') }}</span>
                    </div>
                    @endif
                    @if($invoice->discount > 0)
                    <div class="flex justify-between text-gray-500">
                        <span>Remise</span>
                        <span>- HTG {{ number_format($invoice->discount,2,'.',',') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-bold text-gray-800 dark:text-white text-base pt-2 border-t border-gray-200 dark:border-slate-600">
                        <span>Total dû</span>
                        <span style="color:#d4a017">HTG {{ number_format($invoice->total,2,'.',',') }}</span>
                    </div>
                    @if($invoice->status === 'paid')
                    <div class="mt-3 text-center">
                        <span class="inline-flex items-center gap-1.5 bg-green-100 text-green-700 text-xs font-semibold px-3 py-1.5 rounded-full">
                            <i class="bi bi-check-circle-fill"></i> Payée le {{ $invoice->paid_at?->format('d/m/Y') }}
                        </span>
                    </div>
                    @endif
                </div>
            </div>

            @if($invoice->notes)
            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-slate-700">
                <p class="text-xs font-medium text-gray-400 mb-1">Notes</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $invoice->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-5">
        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-4">Actions</h3>
            <div class="space-y-2">
                <a href="{{ route('erp.invoices.pdf',$invoice) }}" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-xl transition-colors">
                    <i class="bi bi-file-pdf text-red-500"></i> Télécharger PDF
                </a>
                @if($invoice->status !== 'paid')
                <form action="{{ route('erp.invoices.mark-paid',$invoice) }}" method="POST">
                    @csrf @method('PATCH')
                    <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-slate-700 rounded-xl transition-colors text-left">
                        <i class="bi bi-check-circle text-green-500"></i> Marquer comme payée
                    </button>
                </form>
                @endif
                <form action="{{ route('erp.invoices.destroy',$invoice) }}" method="POST" onsubmit="return confirm('Supprimer cette facture ?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="flex items-center gap-2 w-full px-3 py-2 text-sm text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-colors">
                        <i class="bi bi-trash"></i> Supprimer
                    </button>
                </form>
            </div>
        </div>

        <div class="content-card p-5">
            <h3 class="font-semibold text-gray-800 dark:text-white mb-3">Résumé</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400">Montant TTC</dt>
                    <dd class="font-bold text-gray-800 dark:text-white">HTG {{ number_format($invoice->total,0,'.',',') }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Statut</dt>
                    <dd><span class="badge text-xs {{ $sc[$invoice->status] ?? '' }}">{{ $sl[$invoice->status] ?? $invoice->status }}</span></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Client</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $invoice->client->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400">Créée le</dt>
                    <dd class="text-gray-700 dark:text-gray-300">{{ $invoice->created_at->format('d/m/Y') }}</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
@endsection
