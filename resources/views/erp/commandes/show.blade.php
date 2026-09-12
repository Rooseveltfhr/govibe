@extends('erp.layouts.app')

@section('title', 'Commande '.$commande->reference)
@section('page-title', $commande->reference)
@section('page-subtitle', $commande->plan_nom.' — '.$commande->service_libelle)

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800">
        @foreach ($errors->all() as $erreur)<div>{{ $erreur }}</div>@endforeach
    </div>
@endif

<a href="{{ route('erp.commandes.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les commandes
</a>

<div class="grid gap-5" style="grid-template-columns: 1.1fr .9fr;">

    <div class="flex flex-col gap-5">

        {{-- ── Ce qui a été commandé ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">La commande</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @php
                    $lignes = [
                        'Service' => $commande->service_libelle,
                        'Offre' => $commande->plan_nom,
                        'Facturation' => $commande->cycle_libelle,
                        'Nom de domaine' => $commande->domaine,
                        'Situation du domaine' => $commande->origine_domaine_libelle,
                        'Durée' => $commande->duree_annees
                            ? $commande->duree_annees.' an'.($commande->duree_annees > 1 ? 's' : '')
                            : null,
                        'Reçue le' => $commande->created_at->format('d/m/Y à H:i'),
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

                @if (! $commande->plan_id)
                    {{-- Le plan a été retiré du catalogue : l'abonnement ne peut
                         plus être ouvert depuis cette commande. Le dire ici
                         évite de chercher pourquoi le bouton refuse. --}}
                    <div class="mt-2 rounded-xl px-3 py-2 text-xs bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800">
                        L'offre de cette commande n'existe plus dans le catalogue. Recréez-la
                        pour pouvoir mettre le service en route.
                    </div>
                @endif

                @if ($commande->besoins)
                    <div class="pt-3 mt-2 border-t border-gray-100 dark:border-slate-700">
                        <span class="text-gray-400 block mb-1">Ce que le client a écrit</span>
                        <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $commande->besoins }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Le client ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le client</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">Nom</span>
                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $commande->nom_complet }}</span>
                </div>
                @if ($commande->entreprise)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Entreprise</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $commande->entreprise }}</span>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">E-mail</span>
                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $commande->email }}</span>
                </div>
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">WhatsApp</span>
                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $commande->whatsapp }}</span>
                </div>
                @if ($commande->client)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Fiche client</span>
                        <a href="{{ route('erp.crm.clients.show', $commande->client) }}" class="text-red-500 underline text-right">
                            {{ $commande->client->name }}
                        </a>
                    </div>
                @endif

                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $commande->whatsapp) }}" target="_blank" rel="noopener"
                   class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
                   style="background:#25D366">
                    <i class="bi bi-whatsapp"></i> Écrire au client
                </a>
            </div>
        </div>

        {{-- ── Le règlement ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le règlement</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">À régler</span>
                    <span class="text-gray-800 dark:text-gray-100 font-bold text-right tabular-nums">{{ $commande->montant_affiche }}</span>
                </div>
                @if ($commande->montant_origine_affiche)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Montant facturé</span>
                        <span class="text-gray-600 dark:text-gray-300 text-right tabular-nums">
                            {{ $commande->montant_origine_affiche }}
                            <span class="text-xs text-gray-400 block">taux {{ rtrim(rtrim(number_format((float) $commande->taux_change, 6, ',', ' '), '0'), ',') }}</span>
                        </span>
                    </div>
                @endif
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">Moyen</span>
                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">
                        {{ $commande->passerelle_nom ?: '—' }}
                        <span class="text-xs text-gray-400 block">{{ $commande->mode_paiement === 'api' ? 'Automatique' : 'Manuel' }}</span>
                    </span>
                </div>

                @if ($commande->paiement)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Transaction</span>
                        <a href="{{ route('erp.transactions.show', $commande->paiement) }}" class="text-red-500 underline text-right">
                            {{ $commande->paiement->reference }}
                            <span class="text-xs text-gray-400 block">{{ $commande->paiement->statut_libelle }}</span>
                        </a>
                    </div>
                @endif

                @if ($commande->preuve)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Preuve envoyée</span>
                        <a href="{{ route('erp.preuves.show', $commande->preuve) }}" class="text-red-500 underline text-right">
                            {{ $commande->preuve->reference }}
                        </a>
                    </div>
                @endif

                @if (! $commande->estPayee())
                    <div class="mt-2 rounded-xl px-3 py-2 text-xs bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800">
                        Le règlement n'est pas constaté. Approuvez la transaction dans
                        <strong>Transactions</strong> : la commande passera seule en « paiement reçu ».
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-5">

        {{-- ── Mise en service ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Mise en service</span>
            </div>
            <div class="p-5 text-sm">
                @if ($commande->estActivee())
                    <p class="text-gray-500 dark:text-gray-400 mb-3">
                        L'abonnement est ouvert : c'est lui qui facture désormais.
                    </p>
                    <a href="{{ route('erp.abonnements.show', $commande->abonnement) }}" class="btn-secondary w-full text-sm text-center block">
                        Voir l'abonnement {{ $commande->abonnement->reference }}
                    </a>
                @else
                    <p class="text-gray-500 dark:text-gray-400 mb-3">
                        Ouvre l'abonnement au tarif de cette commande, crée la fiche client
                        si elle n'existe pas, et passe la commande en livrée.
                    </p>
                    <form method="POST" action="{{ route('erp.commandes.activer', $commande) }}"
                          onsubmit="return confirm('Ouvrir l\'abonnement pour cette commande ? La facturation récurrente démarre.')">
                        @csrf
                        <button type="submit" class="btn-primary w-full text-sm" @disabled(! $commande->plan_id)>
                            <i class="bi bi-play-circle"></i> Mettre le service en route
                        </button>
                    </form>
                    @if (! $commande->estPayee())
                        <p class="text-xs text-gray-400 mt-2 text-center">
                            Possible uniquement si l'offre comporte un essai, ou une fois le paiement constaté.
                        </p>
                    @endif
                @endif
            </div>
        </div>

        {{-- ── Suivi ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Suivi</span>
            </div>
            <form method="POST" action="{{ route('erp.commandes.update', $commande) }}" class="p-5 space-y-3">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Statut</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\CommandeAbonnement::statuts() as $v => $l)
                            <option value="{{ $v }}" @selected($commande->statut === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Notes internes</label>
                    <textarea name="notes_internes" rows="5" maxlength="2000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">{{ $commande->notes_internes }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-sm">Enregistrer</button>

                @if ($commande->traitee_le)
                    <p class="text-xs text-gray-400 text-center">
                        Traitée le {{ $commande->traitee_le->format('d/m/Y à H:i') }}
                        @if ($commande->agent) par {{ $commande->agent->name }} @endif
                    </p>
                @endif
            </form>
        </div>
    </div>
</div>

@endsection
