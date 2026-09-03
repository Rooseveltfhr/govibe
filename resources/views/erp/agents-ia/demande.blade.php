@extends('erp.layouts.app')

@section('title', 'Demande '.$demande->reference)
@section('page-title', $demande->reference)
@section('page-subtitle', $demande->entreprise.' — '.$demande->agent_nom)

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.agents-ia.demandes') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les demandes
</a>

<div class="grid gap-5" style="grid-template-columns: 1.2fr .8fr;">

    <div class="flex flex-col gap-5">

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">L'entreprise</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @foreach ([
                    'Entreprise'  => $demande->entreprise,
                    'Responsable' => $demande->responsable,
                    'Email'       => $demande->email,
                    'Téléphone'   => $demande->telephone,
                    'Secteur'     => $demande->secteur,
                    'Localisation'=> trim(($demande->ville ? $demande->ville.', ' : '').$demande->pays, ', '),
                    'Site web'    => $demande->site_web,
                    'Reçue le'    => $demande->created_at->format('d/m/Y à H:i'),
                ] as $label => $valeur)
                    @if ($valeur)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">{{ $label }}</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                        </div>
                    @endif
                @endforeach

                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $demande->telephone) }}" target="_blank" rel="noopener"
                   class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
                   style="background:#25D366">
                    <i class="bi bi-whatsapp"></i> Répondre sur WhatsApp
                </a>
            </div>
        </div>

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le besoin</span>
            </div>
            <div class="p-5 text-sm space-y-4">
                @foreach ([
                    'Canal souhaité'         => $demande->canal_lisible,
                    'Volume de conversations'=> $demande->volume_lisible,
                    'Langues'                => $demande->langues,
                ] as $label => $valeur)
                    @if ($valeur)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">{{ $label }}</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                        </div>
                    @endif
                @endforeach

                @if ($demande->integrations_lisibles)
                    <div>
                        <span class="text-gray-400 block mb-1.5">Intégrations demandées</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($demande->integrations_lisibles as $i)
                                <span class="text-xs bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 rounded-lg px-2 py-1">{{ $i }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach ([
                    'Objectifs principaux'   => $demande->objectifs,
                    'À automatiser'          => $demande->a_automatiser,
                    'Besoin décrit'          => $demande->message,
                ] as $label => $texte)
                    @if ($texte)
                        <div class="pt-3 border-t border-gray-100 dark:border-slate-700">
                            <span class="text-gray-400 block mb-1">{{ $label }}</span>
                            <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line leading-relaxed">{{ $texte }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-5">

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Commande</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                <div class="flex justify-between gap-3">
                    <span class="text-gray-400">Agent</span>
                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $demande->agent_nom }}</span>
                </div>
                @if ($demande->sur_devis)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Tarification</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium">Sur devis</span>
                    </div>
                @else
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Installation</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium tabular-nums">{{ $demande->montantAffiche((float) $demande->prix_installation) ?? '—' }}</span>
                    </div>
                    @if ($demande->prix_mensuel !== null)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">Service mensuel</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium tabular-nums">{{ $demande->montantAffiche((float) $demande->prix_mensuel) }}</span>
                        </div>
                    @endif
                @endif
                @if ($demande->moyen_paiement_nom)
                    <div class="flex justify-between gap-3">
                        <span class="text-gray-400">Moyen choisi</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium">{{ $demande->moyen_paiement_nom }}</span>
                    </div>
                @endif
                @if ($demande->deploye_le)
                    <div class="flex justify-between gap-3 pt-2 border-t border-gray-100 dark:border-slate-700">
                        <span class="text-gray-400">En service depuis</span>
                        <span class="text-gray-800 dark:text-gray-100 font-medium">{{ $demande->deploye_le->format('d/m/Y') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Suivi et déploiement</span>
            </div>
            <form method="POST" action="{{ route('erp.agents-ia.demande.update', $demande) }}" class="p-5 space-y-3">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Statut du dossier</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\DemandeAgentIa::statuts() as $v => $l)
                            <option value="{{ $v }}" @selected($demande->statut === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Paiement</label>
                    <select name="statut_paiement" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\DemandeAgentIa::statutsPaiement() as $v => $l)
                            <option value="{{ $v }}" @selected($demande->statut_paiement === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">
                        Fournisseur d'infrastructure
                        <span class="text-gray-300">— interne, jamais affiché au client</span>
                    </label>
                    <input type="text" name="fournisseur" value="{{ $demande->fournisseur }}" maxlength="80"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Numéro WhatsApp de l'agent</label>
                    <input type="text" name="numero_whatsapp" value="{{ $demande->numero_whatsapp }}" maxlength="40"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">URL de l'agent</label>
                    <input type="text" name="url_agent" value="{{ $demande->url_agent }}" maxlength="255"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Notes internes</label>
                    <textarea name="notes_internes" rows="4" maxlength="5000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">{{ $demande->notes_internes }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-sm">Enregistrer</button>
            </form>
        </div>

        <form method="POST" action="{{ route('erp.agents-ia.demande.destroy', $demande) }}"
              onsubmit="return confirm('Supprimer cette demande ? Action définitive.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-trash"></i> Supprimer cette demande
            </button>
        </form>
    </div>
</div>

@endsection
