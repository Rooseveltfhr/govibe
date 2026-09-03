@extends('erp.layouts.app')

@section('title', 'Demandes Agents IA')
@section('page-title', 'Demandes Agents IA')
@section('page-subtitle', 'Entreprises qui ont demandé un agent')

@section('content')

@php
  $statuts  = \App\Models\DemandeAgentIa::statuts();
  $statutsP = \App\Models\DemandeAgentIa::statutsPaiement();
@endphp

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Demandes reçues', $stats['total'], 'bi-inbox-fill', '#1e3a5f', '#dbeafe', null],
        ['À traiter', $stats['a_traiter'], 'bi-hourglass-split', '#b45309', '#fef3c7', 'nouvelle'],
        ['Paiement à encaisser', $stats['a_encaisser'], 'bi-cash-coin', '#b91c1c', '#fee2e2', null],
        ['Agents actifs', $stats['actifs'], 'bi-robot', '#059669', '#d1fae5', 'actif'],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.agents-ia.demandes', ['statut' => $st]) : route('erp.agents-ia.demandes') }}" class="stat-card block">
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

<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Parcours des dossiers</span>
        <a href="{{ route('erp.agents-ia.catalogue') }}" class="text-xs text-gray-400 hover:text-red-500">
            <i class="bi bi-sliders"></i> Gérer le catalogue
        </a>
    </div>
    <div class="p-5 flex flex-wrap gap-2">
        @foreach ($tunnel as $cle => $e)
            <a href="{{ route('erp.agents-ia.demandes', ['statut' => $cle]) }}"
               class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-colors
                      {{ request('statut') === $cle ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300' }}">
                <span class="text-gray-600 dark:text-gray-300">{{ $e['libelle'] }}</span>
                <span class="font-extrabold {{ $e['n'] > 0 ? 'text-gray-800 dark:text-white' : 'text-gray-300 dark:text-slate-600' }}">{{ $e['n'] }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="content-card">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Entreprise, responsable, email, référence..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-64 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="statut_paiement" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les paiements</option>
                @foreach ($statutsP as $v => $l)<option value="{{ $v }}" @selected(request('statut_paiement') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q', 'statut', 'statut_paiement']))
                <a href="{{ route('erp.agents-ia.demandes') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Entreprise</th>
                    <th class="px-4 py-3 text-left font-semibold">Agent</th>
                    <th class="px-4 py-3 text-right font-semibold">Installation</th>
                    <th class="px-4 py-3 text-left font-semibold">Paiement</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Reçue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($demandes as $d)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.agents-ia.demande', $d) }}'">
                        <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $d->reference }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 dark:text-gray-200">{{ $d->entreprise }}</div>
                            <div class="text-xs text-gray-400">{{ $d->responsable }} &middot; {{ $d->telephone }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $d->agent_nom }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            @if ($d->sur_devis)<span class="text-xs text-gray-400">Sur devis</span>
                            @else {{ $d->montantAffiche((float) $d->prix_installation) ?? '—' }} @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $cp = match ($d->statut_paiement) {
                                    'recu'           => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'preuve_envoyee' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    'sur_devis'      => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300',
                                    default          => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $cp }}">{{ $d->statut_paiement_libelle }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $d->statut_libelle }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $d->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-robot text-4xl mb-3 block opacity-30"></i>
                            Aucune demande @if (request()->hasAny(['q', 'statut', 'statut_paiement'])) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($demandes->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $demandes->links() }}</div>
    @endif
</div>

@endsection
