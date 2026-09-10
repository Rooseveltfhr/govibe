@extends('erp.layouts.app')

@section('title', 'Paiement '.$paiement->reference)
@section('page-title', $paiement->reference)
@section('page-subtitle', $paiement->passerelle_nom.' — '.$paiement->montant_affiche)

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.transactions.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les transactions
</a>

<div class="grid gap-5" style="grid-template-columns: 1fr 1fr;">

    <div class="flex flex-col gap-5">
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le paiement</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @foreach ([
                    'Montant'      => $paiement->montant_affiche,
                    'Équivalent'   => $paiement->montant_converti_affiche,
                    'Taux appliqué'=> $paiement->taux_change ? rtrim(rtrim(number_format((float) $paiement->taux_change, 6, ',', ' '), '0'), ',') : null,
                    'Moyen'        => $paiement->passerelle_nom,
                    'Mode'         => $paiement->mode_libelle,
                    'Statut'       => $paiement->statut_libelle,
                    'Transaction'  => $paiement->reference_externe,
                    'Ouvert le'    => $paiement->created_at->format('d/m/Y à H:i'),
                    'Payé le'      => $paiement->paye_le?->format('d/m/Y à H:i'),
                ] as $label => $valeur)
                    @if ($valeur)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">{{ $label }}</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                        </div>
                    @endif
                @endforeach

                @if ($paiement->echec_motif)
                    <div class="pt-2 mt-2 border-t border-gray-100 dark:border-slate-700">
                        <span class="text-gray-400 block mb-1">Motif</span>
                        <p class="text-amber-700 dark:text-amber-400">{{ $paiement->echec_motif }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if (! $paiement->estFige())
            <div class="content-card">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Décision</span>
                </div>
                <div class="p-5 space-y-3">
                    @if ($paiement->mode === 'api')
                        <form method="POST" action="{{ route('erp.transactions.reverifier', $paiement) }}">
                            @csrf
                            <button type="submit" class="btn-secondary w-full text-sm">
                                <i class="bi bi-arrow-clockwise"></i> Revérifier auprès de la passerelle
                            </button>
                        </form>
                        <p class="text-xs text-gray-400">
                            Interroge {{ $paiement->passerelle_nom }} à nouveau. C'est la passerelle qui tranche, pas nous.
                        </p>
                    @endif

                    <form method="POST" action="{{ route('erp.transactions.approuver', $paiement) }}" class="space-y-2 pt-2 border-t border-gray-100 dark:border-slate-700">
                        @csrf
                        <label class="block text-xs text-gray-400">Référence du reçu <span class="text-gray-300">— facultatif</span></label>
                        <input type="text" name="reference_externe" maxlength="120"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        <button type="submit" class="btn-primary w-full text-sm"
                                onclick="return confirm('Confirmer la réception de {{ $paiement->montant_affiche }} ?')">
                            Marquer comme reçu
                        </button>
                    </form>

                    <form method="POST" action="{{ route('erp.transactions.rejeter', $paiement) }}" class="space-y-2 pt-2 border-t border-gray-100 dark:border-slate-700">
                        @csrf
                        <label class="block text-xs text-gray-400">Motif du rejet</label>
                        <input type="text" name="motif" required maxlength="500"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        <button type="submit" class="btn-secondary w-full text-sm">Rejeter</button>
                    </form>
                </div>
            </div>
        @else
            <div class="content-card">
                <div class="p-5 text-sm text-gray-500 dark:text-gray-400">
                    <i class="bi bi-lock-fill"></i>
                    Ce paiement est figé — il ne peut plus être modifié.
                    @if ($paiement->approuve_par)
                        Validé par {{ $paiement->approuve_par }} le {{ $paiement->approuve_le?->format('d/m/Y à H:i') }}.
                    @endif
                </div>
            </div>
        @endif
    </div>

    <div class="content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <span class="font-semibold text-gray-800 dark:text-gray-100">Historique</span>
            <span class="text-xs text-gray-400 ml-2">chaque changement d'état, et par qui</span>
        </div>
        <div class="p-5 space-y-3">
            @forelse ($paiement->evenements as $e)
                <div class="flex gap-3 pb-3 border-b border-gray-50 dark:border-slate-700/60 last:border-0 last:pb-0">
                    <span class="w-2 h-2 rounded-full mt-1.5 shrink-0" style="background:{{ $e->statut_apres === 'reussi' ? '#059669' : ($e->statut_apres === 'echoue' ? '#b91c1c' : '#94a3b8') }}"></span>
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

@endsection
