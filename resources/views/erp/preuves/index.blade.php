@extends('erp.layouts.app')

@section('title', 'Preuves de paiement')
@section('page-title', 'Preuves de paiement')
@section('page-subtitle', 'Captures envoyées par les clients depuis le site')

@section('content')

@php $statuts = \App\Models\PreuvePaiement::statuts(); @endphp

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Total reçues', $stats['total'], 'bi-inbox-fill', '#1e3a5f', '#dbeafe', null],
        ['En attente', $stats['en_attente'], 'bi-hourglass-split', '#b45309', '#fef3c7', 'recue'],
        ['Vérifiées', $stats['verifiees'], 'bi-patch-check-fill', '#059669', '#d1fae5', 'verifiee'],
        ['Rejetées', $stats['rejetees'], 'bi-x-octagon-fill', '#b91c1c', '#fee2e2', 'rejetee'],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.preuves.index', ['statut' => $st]) : route('erp.preuves.index') }}" class="stat-card block">
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
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, téléphone, référence, transaction..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-64 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q', 'statut']))
                <a href="{{ route('erp.preuves.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>
        <a href="{{ route('paiement.preuve') }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
            <i class="bi bi-box-arrow-up-right"></i> Formulaire client
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Client</th>
                    <th class="px-4 py-3 text-left font-semibold">Moyen</th>
                    <th class="px-4 py-3 text-right font-semibold">Montant</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Reçue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($preuves as $p)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.preuves.show', $p) }}'">
                        <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $p->reference }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 dark:text-gray-200">{{ $p->nom }}</div>
                            <div class="text-xs text-gray-400">{{ $p->telephone }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $p->moyen_nom ?: '—' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($p->montant !== null){{ number_format((float) $p->montant, 2, ',', ' ') }} {{ $p->devise }}@else — @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $c = match ($p->statut) {
                                    'verifiee' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'rejetee'  => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    default    => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $c }}">{{ $p->statut_libelle }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-inbox text-4xl mb-3 block opacity-30"></i>
                            Aucune preuve @if (request()->hasAny(['q', 'statut'])) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($preuves->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $preuves->links() }}</div>
    @endif
</div>

@endsection
