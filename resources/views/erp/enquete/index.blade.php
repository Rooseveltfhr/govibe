@extends('erp.layouts.app')

@section('title', 'Enquête sur les besoins')
@section('page-title', 'Enquête sur les besoins')
@section('page-subtitle', 'Ce que les entreprises haïtiennes nous disent de leurs difficultés')

@section('content')

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Réponses', $stats['total'], 'bi-clipboard-data-fill', '#1e3a5f', '#dbeafe', null],
        ['Nouvelles', $stats['nouvelles'], 'bi-envelope-fill', '#b45309', '#fef3c7', 'nouvelle'],
        ['À rappeler', $stats['a_rappeler'], 'bi-telephone-fill', '#7c3aed', '#ede9fe', null],
        ['En créole', $stats['creole'], 'bi-translate', '#059669', '#d1fae5', null],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.enquete.index', ['statut' => $st]) : route('erp.enquete.index', $label === 'À rappeler' ? ['rappel' => 'oui'] : []) }}"
       class="stat-card block">
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

{{-- ── Le dépouillement : ce qui fait d'une pile de fiches une enquête ── --}}
@if ($difficultes || $solutions || $freins || $materiel)
<div class="grid gap-5 mb-6" style="grid-template-columns: 1fr 1fr;">
    @foreach ([
        ['Ce qui coince le plus', $difficultes, '#dc2626'],
        ['Ce qui intéresse le plus', $solutions, '#1e3a8c'],
        ['Ce qui empêcherait d\'adopter', $freins, '#b45309'],
        ['Le matériel déjà disponible', $materiel, '#059669'],
    ] as [$titre, $palmares, $couleur])
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $titre }}</span>
                @if (request('type'))
                    <span class="text-xs text-gray-400">{{ $types[request('type')]['fr'] ?? request('type') }}</span>
                @endif
            </div>
            <div class="p-5 space-y-3">
                @forelse ($palmares as $ligne)
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-gray-600 dark:text-gray-300">{{ $ligne['libelle'] }}</span>
                            <span class="text-gray-400 tabular-nums">{{ $ligne['part'] }}% &middot; {{ $ligne['total'] }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-gray-100 dark:bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full" style="width:{{ max($ligne['part'], 2) }}%;background:{{ $couleur }}"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Pas encore assez de réponses.</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
@endif

{{-- Par type d'activité : c'est ce qui dit où porter l'effort produit. --}}
@if ($parType)
<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Par type d'activité</span>
        <span class="text-xs text-gray-400 ml-2">cliquez pour filtrer le dépouillement</span>
    </div>
    <div class="p-5 flex flex-wrap gap-2">
        @foreach ($parType as $ligne)
            <a href="{{ route('erp.enquete.index', ['type' => $ligne['cle']]) }}"
               class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-colors
                      {{ request('type') === $ligne['cle'] ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300' }}">
                <span class="text-gray-600 dark:text-gray-300">{{ $ligne['libelle'] }}</span>
                <span class="font-extrabold text-gray-800 dark:text-white">{{ $ligne['total'] }}</span>
            </a>
        @endforeach
        @if (request('type'))
            <a href="{{ route('erp.enquete.index') }}" class="text-xs text-gray-400 hover:text-red-500 self-center ml-1">
                <i class="bi bi-x-circle"></i> Tous
            </a>
        @endif
    </div>
</div>
@endif

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Entreprise, nom, WhatsApp, ville..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-64 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l['fr'] }}</option>@endforeach
            </select>
            <select name="type" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Toutes les activités</option>
                @foreach ($types as $v => $l)<option value="{{ $v }}" @selected(request('type') === $v)>{{ $l['fr'] }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q','statut','type','rappel']))
                <a href="{{ route('erp.enquete.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            <a href="{{ route('enquete.show') }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-box-arrow-up-right"></i> Page publique
            </a>
            <a href="{{ route('erp.enquete.export') }}" class="btn-secondary text-sm">
                <i class="bi bi-download"></i> Exporter
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Référence</th>
                    <th class="px-4 py-3 text-left font-semibold">Entreprise</th>
                    <th class="px-4 py-3 text-left font-semibold">Activité</th>
                    <th class="px-4 py-3 text-left font-semibold">Difficultés</th>
                    <th class="px-4 py-3 text-center font-semibold">Rappel</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Reçue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($reponses as $r)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.enquete.show', $r) }}'">
                        <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100 tabular-nums">
                            {{ $r->reference }}
                            @if ($r->langue === 'ht')
                                <span class="ml-1 text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">HT</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 dark:text-gray-200">{{ $r->intitule }}</div>
                            @if ($r->ville)<div class="text-xs text-gray-400">{{ $r->ville }}</div>@endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $r->typeLibelle($langue) }}</td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Str::limit(implode(' · ', $r->difficultesLibelles($langue)), 70) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($r->souhaite_contact)
                                <span class="text-xs font-bold bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400 rounded px-2 py-1">Oui</span>
                            @else
                                <span class="text-xs text-gray-300 dark:text-slate-600">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $couleur = match ($r->statut) {
                                    'contactee' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'archivee' => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400',
                                    'lue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $couleur }}">{{ $r->statutLibelle($langue) }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-clipboard-data text-4xl mb-3 block opacity-30"></i>
                            Aucune réponse @if (request()->hasAny(['q','statut','type','rappel'])) pour ces filtres. @endif
                            <div class="text-xs mt-2">
                                Diffusez
                                <a href="{{ route('enquete.show') }}" target="_blank" rel="noopener" class="text-red-500 underline">la page de l'enquête</a>
                                pour commencer à recevoir des réponses.
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reponses->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $reponses->links() }}</div>
    @endif
</div>

@endsection
