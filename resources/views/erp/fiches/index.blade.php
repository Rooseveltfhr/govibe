@extends('erp.layouts.app')

@section('title', 'Fiches techniques')
@section('page-title', 'Fiches techniques')
@section('page-subtitle', 'Prospection terrain et diagnostic commercial')

@section('content')

@php
  $statuts = \App\Models\FicheTechnique::statuts();
  $types   = \App\Models\FicheTechnique::typesOrganisation();
  $secteurs= \App\Models\FicheTechnique::secteurs();
@endphp

{{-- Ce que l'équipe regarde en premier : pas le volume de fiches, mais
     combien sont qualifiées et combien attendent encore un suivi. --}}
<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(3,1fr);">
    @foreach([
        ['Fiches collectées', $stats['total'], 'bi-clipboard-data-fill', '#1e3a5f', '#dbeafe', null],
        ['Prospects qualifiés', $stats['qualifies'], 'bi-star-fill', '#059669', '#d1fae5', 'qualifies'],
        ['Sans aucun suivi', $stats['sans_suivi'], 'bi-hourglass-split', '#b45309', '#fef3c7', 'sans_suivi'],
    ] as [$label, $val, $icon, $color, $bg, $vue])
    <a href="{{ $vue ? route('erp.fiches.index', ['vue' => $vue]) : route('erp.fiches.index') }}" class="stat-card block">
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

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(3,1fr);">
    @foreach([
        ['Suivies au moins une fois', $stats['suivies'], 'bi-chat-left-text-fill', '#7c3aed', '#ede9fe', null],
        ['Relances dues', $stats['a_relancer'], 'bi-bell-fill', '#b91c1c', '#fee2e2', 'a_relancer'],
        ['Devenues clients', $stats['clients'], 'bi-trophy-fill', '#059669', '#d1fae5', null],
    ] as [$label, $val, $icon, $color, $bg, $vue])
    <a href="{{ $vue ? route('erp.fiches.index', ['vue' => $vue]) : route('erp.fiches.index') }}" class="stat-card block">
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

{{-- Tunnel : où les dossiers s'arrêtent --}}
<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Tunnel commercial</span>
        <span class="text-xs text-gray-400 ml-2">là où s'arrêtent les dossiers</span>
    </div>
    <div class="p-5 flex flex-wrap gap-2">
        @foreach ($tunnel as $libelle => $n)
            @php $cle = array_search($libelle, $statuts, true); @endphp
            <a href="{{ route('erp.fiches.index', ['statut' => $cle]) }}"
               class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-colors
                      {{ request('statut') === $cle ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300' }}">
                <span class="text-gray-600 dark:text-gray-300">{{ $libelle }}</span>
                <span class="font-extrabold {{ $n > 0 ? 'text-gray-800 dark:text-white' : 'text-gray-300 dark:text-slate-600' }}">{{ $n }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            @if (request('vue'))<input type="hidden" name="vue" value="{{ request('vue') }}">@endif
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Organisation, contact, commune, réf..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-60 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="type_organisation" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les types</option>
                @foreach ($types as $v => $l)<option value="{{ $v }}" @selected(request('type_organisation') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="agent" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les agents</option>
                @foreach ($agents as $a)<option value="{{ $a }}" @selected(request('agent') === $a)>{{ $a }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q', 'statut', 'type_organisation', 'agent', 'vue']))
                <a href="{{ route('erp.fiches.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            <a href="{{ route('fiche-technique.create') }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-box-arrow-up-right"></i> Formulaire agent
            </a>
            <a href="{{ route('erp.fiches.export') }}" class="btn-secondary text-sm">
                <i class="bi bi-download"></i> Exporter
            </a>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Organisation</th>
                    <th class="px-4 py-3 text-left font-semibold">Contact</th>
                    <th class="px-4 py-3 text-center font-semibold">Score</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-center font-semibold">Suivis</th>
                    <th class="px-4 py-3 text-left font-semibold">Relance</th>
                    <th class="px-4 py-3 text-left font-semibold">Agent</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($fiches as $f)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.fiches.show', $f) }}'">
                        <td class="px-5 py-3">
                            <div class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2 flex-wrap">
                                {{ $f->nom_organisation }}
                                @if ($f->est_qualifie)
                                    <span class="text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">QUALIFIÉ</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $types[$f->type_organisation] ?? $f->type_organisation }}
                                @if ($f->secteur) &middot; {{ $secteurs[$f->secteur] ?? $f->secteur }} @endif
                                @if ($f->commune) &middot; {{ $f->commune }} @endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-600 dark:text-gray-300">{{ $f->contact_nom ?: '—' }}</div>
                            <div class="text-xs text-gray-400">{{ $f->contact_telephone ?: $f->telephone }}</div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $couleur = match ($f->priorite) {
                                    'haute'   => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'moyenne' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                    'basse'   => 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300',
                                    default   => 'bg-gray-50 text-gray-400 dark:bg-slate-800 dark:text-slate-500',
                                };
                            @endphp
                            <span class="inline-block text-xs font-extrabold rounded-lg px-2 py-1 tabular-nums {{ $couleur }}"
                                  title="Besoin {{ $f->score_besoin }} · Potentiel {{ $f->score_potentiel }}">
                                {{ $f->score_total }}/8
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $statuts[$f->statut] ?? $f->statut }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($f->suivis_count > 0)
                                <span class="inline-block text-xs font-bold bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400 rounded-full px-2 py-0.5">{{ $f->suivis_count }}</span>
                            @else
                                <span class="text-xs text-amber-600 dark:text-amber-400">aucun</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs">
                            @if ($f->date_relance)
                                <span class="{{ $f->date_relance->isPast() ? 'text-red-600 font-semibold dark:text-red-400' : 'text-gray-400' }}">
                                    {{ $f->date_relance->format('d/m/Y') }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $f->agent ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-clipboard-x text-4xl mb-3 block opacity-30"></i>
                            Aucune fiche
                            @if (request()->hasAny(['q', 'statut', 'type_organisation', 'agent', 'vue'])) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($fiches->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">
            {{ $fiches->withQueryString()->links() }}
        </div>
    @endif
</div>

@endsection
