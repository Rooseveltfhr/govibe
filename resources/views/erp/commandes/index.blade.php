@extends('erp.layouts.app')

@section('title', 'Commandes en ligne')
@section('page-title', 'Commandes en ligne')
@section('page-subtitle', 'Sites web, hébergement et noms de domaine commandés depuis le site')

@section('content')

@php
    $statuts = \App\Models\CommandeAbonnement::statuts();
    $services = \App\Models\Plan::services();
@endphp

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Commandes', $stats['total'], 'bi-bag-fill', '#1e3a5f', '#dbeafe', null],
        ['À traiter', $stats['a_traiter'], 'bi-hourglass-split', '#b45309', '#fef3c7', null],
        ['Réglées, à mettre en route', $stats['payees'], 'bi-cash-coin', '#7c3aed', '#ede9fe', 'paiement_recu'],
        ['Livrées', $stats['livrees'], 'bi-check-circle-fill', '#059669', '#d1fae5', 'livree'],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.commandes.index', ['statut' => $st]) : route('erp.commandes.index') }}" class="stat-card block">
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

{{-- La répartition par service dit où porter l'effort : un nom de domaine ne se
     met pas en route comme un site. --}}
<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Par service</span>
    </div>
    <div class="p-5 flex flex-wrap gap-2">
        @foreach ($repartition as $cle => $ligne)
            <a href="{{ route('erp.commandes.index', ['service' => $cle]) }}"
               class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-colors
                      {{ request('service') === $cle ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300' }}">
                <span class="text-gray-600 dark:text-gray-300">{{ $ligne['libelle'] }}</span>
                <span class="font-extrabold {{ $ligne['total'] > 0 ? 'text-gray-800 dark:text-white' : 'text-gray-300 dark:text-slate-600' }}">{{ $ligne['total'] }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, e-mail, domaine, référence..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-64 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="service" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les services</option>
                @foreach ($services as $v => $l)<option value="{{ $v }}" @selected(request('service') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q','statut','service','mode']))
                <a href="{{ route('erp.commandes.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            <a href="{{ route('abonnements.service', \App\Models\Plan::segments()['site_web']) }}" target="_blank" rel="noopener"
               class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-box-arrow-up-right"></i> Page publique
            </a>
            <a href="{{ route('erp.commandes.export') }}" class="btn-secondary text-sm">
                <i class="bi bi-download"></i> Exporter
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Client</th>
                    <th class="px-4 py-3 text-left font-semibold">Offre</th>
                    <th class="px-4 py-3 text-left font-semibold">Domaine</th>
                    <th class="px-4 py-3 text-right font-semibold">Montant</th>
                    <th class="px-4 py-3 text-left font-semibold">Paiement</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Reçue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($commandes as $c)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.commandes.show', $c) }}'">
                        <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $c->reference }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 dark:text-gray-200">{{ $c->entreprise ?: $c->nom_complet }}</div>
                            <div class="text-xs text-gray-400">{{ $c->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-600 dark:text-gray-300">{{ $c->plan_nom }}</div>
                            <div class="text-xs text-gray-400">{{ $c->service_libelle }} &middot; {{ $c->cycle_libelle }}</div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                            {{ $c->domaine ?: '—' }}
                            @if ($c->duree_annees)
                                <span class="text-gray-400">({{ $c->duree_annees }} an{{ $c->duree_annees > 1 ? 's' : '' }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                            {{ $c->montant_affiche }}
                            @if ($c->montant_origine_affiche)
                                <div class="text-xs text-gray-400">{{ $c->montant_origine_affiche }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            <div class="text-gray-600 dark:text-gray-300">{{ $c->passerelle_nom ?: '—' }}</div>
                            <div class="text-gray-400">
                                {{ $c->mode_paiement === 'api' ? 'Automatique' : 'Manuel' }}
                                @if ($c->preuve_paiement_id) &middot; preuve reçue @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $couleur = match ($c->statut) {
                                    'livree' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'annulee' => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'paiement_recu' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
                                    'en_traitement' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $couleur }}">{{ $c->statut_libelle }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-bag text-4xl mb-3 block opacity-30"></i>
                            Aucune commande @if (request()->hasAny(['q','statut','service'])) pour ces filtres. @endif
                            <div class="text-xs mt-2">
                                Les offres doivent être publiées dans
                                <a href="{{ route('erp.abonnements.plans') }}" class="text-red-500 underline">le catalogue de plans</a>
                                pour qu'un visiteur puisse commander.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($commandes->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $commandes->links() }}</div>
    @endif
</div>

@endsection
