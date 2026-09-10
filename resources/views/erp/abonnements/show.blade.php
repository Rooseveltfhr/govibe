@extends('erp.layouts.app')

@section('title', 'Abonnement '.$abonnement->reference)
@section('page-title', $abonnement->reference)
@section('page-subtitle', ($abonnement->client?->name ?? '').' — '.$abonnement->plan_nom)

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.abonnements.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Tous les abonnements
</a>

<div class="grid gap-5" style="grid-template-columns: 1.1fr .9fr;">

    <div class="flex flex-col gap-5">
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le contrat</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @php
                    $lignes = [
                        'Client'         => $abonnement->client?->name,
                        'Plan'           => $abonnement->plan_nom,
                        'Service'        => $abonnement->service_libelle,
                        'Cycle'          => $abonnement->cycle_libelle,
                        'Prix hors taxe' => number_format($abonnement->montant_ht, 2, ',', ' ').' '.$abonnement->devise,
                        'Taxe'           => ((float) $abonnement->tca_taux).' % — '.number_format($abonnement->montant_taxe, 2, ',', ' ').' '.$abonnement->devise,
                        'Total'          => number_format($abonnement->montant_ttc, 2, ',', ' ').' '.$abonnement->devise,
                        'Statut'         => $abonnement->statut_libelle,
                        'Début'          => $abonnement->date_debut?->format('d/m/Y'),
                        "Fin d'essai"    => $abonnement->essai_fin?->format('d/m/Y'),
                        'Période en cours' => ($abonnement->periode_debut && $abonnement->periode_fin)
                            ? $abonnement->periode_debut->format('d/m/Y').' → '.$abonnement->periode_fin->format('d/m/Y')
                            : null,
                        'Prochaine facture' => $abonnement->date_prochaine_facture?->format('d/m/Y'),
                        'Résilié le'     => $abonnement->resilie_le?->format('d/m/Y'),
                        'Effectif au'    => $abonnement->resiliation_effective_le?->format('d/m/Y'),
                    ];
                @endphp
                @foreach ($lignes as $label => $valeur)
                    @if ($valeur)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">{{ $label }}</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                        </div>
                    @endif
                @endforeach

                @if ($abonnement->motif_resiliation)
                    <div class="pt-2 mt-2 border-t border-gray-100 dark:border-slate-700">
                        <span class="text-gray-400 block mb-1">Motif de résiliation</span>
                        <p class="text-gray-700 dark:text-gray-200">{{ $abonnement->motif_resiliation }}</p>
                    </div>
                @endif
            </div>
        </div>

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Factures</span>
            </div>
            @if ($abonnement->factures->isEmpty())
                <div class="p-5 text-sm text-gray-400">Aucune facture émise.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 dark:border-slate-700 text-xs text-gray-400 uppercase tracking-wide">
                            <th class="px-5 py-3 text-left font-semibold">Référence</th>
                            <th class="px-4 py-3 text-left font-semibold">Période</th>
                            <th class="px-4 py-3 text-left font-semibold">Statut</th>
                            <th class="px-4 py-3 text-right font-semibold">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 dark:divide-slate-700/60">
                        @foreach ($abonnement->factures as $f)
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-800 dark:text-gray-100">{{ $f->reference }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                    @if ($f->periode_debut) {{ $f->periode_debut->format('d/m/Y') }} → {{ $f->periode_fin?->format('d/m/Y') }} @else — @endif
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{{ ucfirst((string) $f->status) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-200">
                                    {{ number_format((float) $f->total, 2, ',', ' ') }} {{ $f->devise }}
                                    @if ($f->montant_converti)
                                        <span class="block text-xs text-gray-400">≈ {{ number_format((float) $f->montant_converti, 2, ',', ' ') }} {{ $f->devise_convertie }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-5">
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Actions</span>
            </div>
            <div class="p-5 space-y-3">
                @if ($abonnement->estEnService() && $abonnement->renouvellement_auto)
                    <form method="POST" action="{{ route('erp.abonnements.facturer', $abonnement) }}">
                        @csrf
                        <button type="submit" class="btn-primary w-full text-sm">
                            <i class="bi bi-receipt"></i> Émettre la facture de la période
                        </button>
                    </form>
                    <p class="text-xs text-gray-400">
                        La tâche quotidienne le fait toute seule à l'échéance. Ce bouton sert à
                        forcer l'émission si elle n'a pas eu lieu — une période qui n'a pas
                        commencé ne peut pas être facturée.
                    </p>
                @endif

                <form method="POST" action="{{ route('erp.abonnements.statut', $abonnement) }}" class="space-y-2 pt-2 border-t border-gray-100 dark:border-slate-700">
                    @csrf @method('PATCH')
                    <label class="block text-xs text-gray-400">Statut</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\Abonnement::statuts() as $v => $l)
                            <option value="{{ $v }}" @selected($abonnement->statut === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn-secondary w-full text-sm">Enregistrer</button>
                </form>

                @if ($abonnement->renouvellement_auto)
                    <form method="POST" action="{{ route('erp.abonnements.resilier', $abonnement) }}" class="space-y-2 pt-2 border-t border-gray-100 dark:border-slate-700"
                          onsubmit="return confirm('Résilier cet abonnement ? Le service ira au bout de la période déjà payée.')">
                        @csrf
                        <label class="block text-xs text-gray-400">Motif de résiliation</label>
                        <input type="text" name="motif" required maxlength="1000"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        <button type="submit" class="btn-secondary w-full text-sm">Résilier</button>
                    </form>
                @endif
            </div>
        </div>

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Historique</span>
            </div>
            <div class="p-5 space-y-3">
                @forelse ($abonnement->evenements as $e)
                    <div class="flex gap-3 pb-3 border-b border-gray-50 dark:border-slate-700/60 last:border-0 last:pb-0">
                        <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background:{{ $e->statut_apres === 'actif' ? '#059669' : ($e->statut_apres === 'suspendu' ? '#b91c1c' : '#94a3b8') }}"></span>
                        <div class="text-sm flex-1">
                            <div class="text-gray-800 dark:text-gray-100">{{ str_replace('_', ' ', $e->type) }}</div>
                            <div class="text-xs text-gray-400">
                                {{ $e->statut_avant ?: '—' }} → {{ $e->statut_apres ?: '—' }}
                                &middot; {{ $e->source }}
                                @if ($e->user) &middot; {{ $e->user->name }} @endif
                                &middot; {{ $e->created_at?->format('d/m/Y H:i') }}
                            </div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">Aucun événement.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
