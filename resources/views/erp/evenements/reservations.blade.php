@extends('erp.layouts.app')

@section('title', 'Inscriptions — ' . $evenement->titre)
@section('page-title', $evenement->titre)
@section('page-subtitle', 'Liste des inscriptions')

@section('content')

<div class="mb-4">
    <a href="{{ route('erp.evenements.index') }}" class="text-sm text-gray-400 hover:text-red-500">
        <i class="bi bi-arrow-left"></i> Retour aux événements
    </a>
</div>

{{-- Stats --}}
<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Inscrits', $stats['total'], 'bi-people-fill', '#b91c1c', '#fee2e2'],
        ['Présences confirmées', $stats['confirmes'], 'bi-check-circle-fill', '#059669', '#d1fae5'],
        ['Femmes', $stats['femmes'], 'bi-person-badge', '#7c3aed', '#ede9fe'],
        ['Villes représentées', $stats['villes'], 'bi-geo-alt-fill', '#d97706', '#fef3c7'],
    ] as [$label, $val, $icon, $color, $bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $label }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $val }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $icon }}" style="color:{{ $color }}"></i>
            </div>
        </div>
    </div>
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
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nom, email, WhatsApp, ville..."
                       class="pl-9 pr-4 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:border-red-400 w-60 dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <select name="statut_actuel" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                <option value="">Tous les statuts</option>
                @foreach (\App\Models\EvenementReservation::statutsActuels() as $v => $l)
                    <option value="{{ $v }}" {{ request('statut_actuel') === $v ? 'selected' : '' }}>{{ $l }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn-primary text-sm">Filtrer</button>
            @if (request('q') || request('statut_actuel'))
                <a href="{{ route('erp.evenements.reservations', $evenement) }}" class="btn-secondary text-sm">Réinitialiser</a>
            @endif
        </form>

        <a href="{{ route('erp.evenements.export', $evenement) }}" class="btn-secondary text-sm">
            <i class="bi bi-download"></i> Exporter en CSV
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-5 py-3 text-left font-semibold">Participante</th>
                    <th class="px-4 py-3 text-left font-semibold">Contact</th>
                    <th class="px-4 py-3 text-left font-semibold">Localisation</th>
                    <th class="px-4 py-3 text-left font-semibold">Profession</th>
                    <th class="px-4 py-3 text-left font-semibold">Statut</th>
                    <th class="px-4 py-3 text-left font-semibold">Inscrit le</th>
                    <th class="px-4 py-3 text-right font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($reservations as $r)
                    <tr class="hover:bg-gray-50/60 dark:hover:bg-slate-700/30 transition-colors">
                        <td class="px-5 py-3">
                            <div class="font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                                {{ $r->nom_complet }}
                                @if ($r->presence_confirmee)
                                    <span class="text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">PRÉSENTE</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                {{ $r->sexe_libelle ?: '—' }}@if ($r->situation_libelle) · {{ $r->situation_libelle }}@endif
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <a href="mailto:{{ $r->email }}" class="text-gray-600 dark:text-gray-300 hover:text-red-500 block">{{ $r->email }}</a>
                            <div class="text-xs text-gray-400 mt-0.5 flex gap-2">
                                {{-- wa.me exige un numéro sans espaces ni signes --}}
                                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $r->whatsapp) }}" target="_blank" rel="noopener" class="hover:text-red-500">
                                    <i class="bi bi-whatsapp"></i> {{ $r->whatsapp }}
                                </a>
                                @if ($r->telephone)<span>· {{ $r->telephone }}</span>@endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                            {{ $r->ville }}@if ($r->commune), {{ $r->commune }}@endif
                            <div class="text-gray-400">{{ $r->pays }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $r->profession ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($r->statut_libelle)
                                <span class="inline-block text-xs font-medium bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-lg px-2 py-1">
                                    {{ $r->statut_libelle }}
                                </span>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $r->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <div class="flex gap-1 justify-end items-center">
                                <form method="POST" action="{{ route('erp.evenements.presence', $r) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit"
                                            title="{{ $r->presence_confirmee ? 'Retirer la présence' : 'Confirmer la présence' }}"
                                            class="w-8 h-8 rounded-lg flex items-center justify-center text-sm {{ $r->presence_confirmee ? 'text-green-600 hover:bg-green-50 dark:hover:bg-green-900/20' : 'text-gray-400 hover:bg-gray-100 dark:hover:bg-slate-700' }}">
                                        <i class="bi {{ $r->presence_confirmee ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('erp.evenements.reservations.destroy', $r) }}"
                                      onsubmit="return confirm('Supprimer cette inscription ?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>

                    @if ($r->motivation)
                        <tr class="bg-gray-50/50 dark:bg-slate-700/20">
                            <td colspan="7" class="px-5 py-2 text-xs text-gray-500 dark:text-gray-400 italic">
                                <span class="font-semibold text-gray-600 dark:text-gray-300">Motivation :</span>
                                {{ $r->motivation }}
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                            <i class="bi bi-person-x text-4xl mb-3 block opacity-30"></i>
                            Aucune inscription
                            @if (request('q') || request('statut_actuel')) pour ces filtres. @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($reservations->hasPages())
        <div class="px-5 py-4 border-t border-gray-100 dark:border-slate-700">
            {{ $reservations->withQueryString()->links() }}
        </div>
    @endif
</div>

@endsection
