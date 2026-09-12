@extends('erp.layouts.app')

@section('title', 'Réservations LANDRY')
@section('page-title', 'Réservations LANDRY')
@section('page-subtitle', 'Service de lavage — demandes reçues')

@section('content')

@php $statuts = \App\Models\ReservationLandry::statuts(); $services = \App\Models\ReservationLandry::modesService(); @endphp

<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Réservations', $stats['total'], 'bi-basket-fill', '#1e3a5f', '#dbeafe', null],
        ['Nouvelles', $stats['nouvelles'], 'bi-hourglass-split', '#b45309', '#fef3c7', 'nouvelle'],
        ['Confirmées', $stats['confirmees'], 'bi-check-circle-fill', '#059669', '#d1fae5', 'confirmee'],
        ['Acceptent les frais', $stats['payantes'], 'bi-cash-coin', '#7c3aed', '#ede9fe', null],
    ] as [$label, $val, $icon, $color, $bg, $st])
    <a href="{{ $st ? route('erp.landry.index', ['statut' => $st]) : route('erp.landry.index') }}" class="stat-card block">
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

{{-- Où le service est demandé : c'est ce qui décide de la logistique. --}}
<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Où le service est demandé</span>
    </div>
    <div class="p-5 flex flex-wrap gap-2">
        @foreach ($repartition as $cle => $n)
            <a href="{{ route('erp.landry.index', ['mode_service' => $cle]) }}"
               class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs transition-colors
                      {{ request('mode_service') === $cle ? 'border-red-400 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600 hover:border-gray-300' }}">
                <span class="text-gray-600 dark:text-gray-300">{{ $services[$cle] ?? $cle }}</span>
                <span class="font-extrabold {{ $n > 0 ? 'text-gray-800 dark:text-white' : 'text-gray-300 dark:text-slate-600' }}">{{ $n }}</span>
            </a>
        @endforeach
    </div>
</div>

<div class="content-card">
    <div class="flex flex-col sm:flex-row gap-3 items-start sm:items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <form method="GET" class="flex flex-wrap gap-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, WhatsApp, adresse, référence..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-64 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="mode_service" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Partout</option>
                @foreach ($services as $v => $l)<option value="{{ $v }}" @selected(request('mode_service') === $v)>{{ $l }}</option>@endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request()->hasAny(['q','statut','mode_service','frais']))
                <a href="{{ route('erp.landry.index') }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>

        <div class="flex items-center gap-3">
            <a href="{{ route('landry.index') }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-box-arrow-up-right"></i> Page publique
            </a>
            <a href="{{ route('erp.landry.export') }}" class="btn-secondary text-sm">
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
                    <th class="px-4 py-3 text-left font-semibold">Service</th>
                    <th class="px-4 py-3 text-left font-semibold">Fréquence</th>
                    <th class="px-4 py-3 text-center font-semibold">Inscription</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Reçue le</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($reservations as $r)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 cursor-pointer"
                        onclick="window.location='{{ route('erp.landry.show', $r) }}'">
                        <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100 tabular-nums">{{ $r->reference }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 dark:text-gray-200">{{ $r->nom_complet }}</div>
                            <div class="text-xs text-gray-400">{{ $r->whatsapp }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-gray-600 dark:text-gray-300">{{ $r->mode_service_libelle }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $r->mode_facturation_libelle }}@if ($r->quantite_vetements) &middot; {{ $r->quantite_vetements }} pièces @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $r->frequence_libelle }}</td>
                        <td class="px-4 py-3 text-center">
                            @if ($r->accepte_frais_inscription)
                                <span class="text-xs font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-2 py-1">{{ $r->frais_affiche }}</span>
                            @else
                                <span class="text-xs text-amber-600 dark:text-amber-400">à informer</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $c = match ($r->statut) {
                                    'confirmee' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                    'annulee'   => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                    'contactee' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                    default     => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                                };
                            @endphp
                            <span class="text-xs font-bold rounded-lg px-2 py-1 {{ $c }}">{{ $r->statut_libelle }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-basket text-4xl mb-3 block opacity-30"></i>
                            Aucune réservation @if (request()->hasAny(['q','statut','mode_service'])) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reservations->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">{{ $reservations->links() }}</div>
    @endif
</div>

@endsection
