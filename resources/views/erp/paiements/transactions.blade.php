@extends('erp.layouts.app')

@section('title', 'Transactions')
@section('page-title', 'Transactions')
@section('page-subtitle', 'Tous les encaissements GOVIBE, automatiques et manuels')

@section('content')

@php $statuts = \App\Models\Paiement::statuts(); $modes = \App\Models\Paiement::modes(); @endphp

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Transactions', $stats['total'], 'bi-arrow-left-right', '#1e3a5f', '#dbeafe', null],
        ['À valider', $stats['a_valider'], 'bi-hourglass-split', '#b45309', '#fef3c7', 'verification'],
        ['Réussies', $stats['reussis'], 'bi-check-circle-fill', '#059669', '#d1fae5', 'reussi'],
        ['Encaissé (HTG)', number_format((float) $stats['encaisse'], 0, ',', ' '), 'bi-cash-coin', '#7c3aed', '#ede9fe', null],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.transactions.index', ['statut' => $st]) : route('erp.transactions.index') }}" class="stat-card block">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $val }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
        </div>
    </a>
    @endforeach
</div>

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Référence, transaction, moyen..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-60 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="mode" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les modes</option>
                @foreach ($modes as $v => $l)<option value="{{ $v }}" @selected(request('mode') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q','statut','mode']))
                <a href="{{ route('erp.transactions.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>
        <a href="{{ route('erp.transactions.configuration') }}" class="btn-secondary text-sm">
            <i class="bi bi-sliders"></i> Configuration
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Moyen</th>
                    <th class="px-4 py-3 text-left font-semibold">Mode</th>
                    <th class="px-4 py-3 text-right font-semibold">Montant</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($paiements as $p)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.transactions.show', $p) }}'">
                        <td class="px-5 py-3">
                            <div class="font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $p->reference }}</div>
                            @if ($p->reference_externe)
                                <div class="text-xs text-gray-400">{{ $p->reference_externe }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->passerelle_nom ?: '—' }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $p->mode_libelle }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            {{ $p->montant_affiche }}
                            @if ($p->montant_converti_affiche)
                                <span class="block text-xs text-gray-400">≈ {{ $p->montant_converti_affiche }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $c = match ($p->statut) {
                                    'reussi'       => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'echoue','expire','annule' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'verification' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    default        => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $c }}">{{ $p->statut_libelle }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-arrow-left-right text-4xl mb-3 block opacity-30"></i>
                            Aucune transaction @if (request()->hasAny(['q','statut','mode'])) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($paiements->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $paiements->links() }}</div>
    @endif
</div>

@endsection
