@extends('erp.layouts.app')

@section('title', $fiche->nom_organisation)
@section('page-title', $fiche->nom_organisation)
@section('page-subtitle', $fiche->reference)

@section('content')

@php
  $statuts   = \App\Models\FicheTechnique::statuts();
  $types     = \App\Models\FicheTechnique::typesOrganisation();
  $secteurs  = \App\Models\FicheTechnique::secteurs();
  $tailles   = \App\Models\FicheTechnique::taillesEmployes();
  $fonctions = \App\Models\FicheTechnique::fonctions();
  $actions   = \App\Models\FicheTechnique::prochainesActions();
  $niveaux   = \App\Models\FicheTechnique::niveauxScore();
  $potentiels= \App\Models\FicheTechnique::niveauxPotentiel();
  $questions = \App\Models\FicheTechnique::questionsCochables();
  $gestion   = \App\Models\FicheTechnique::fonctionsGestion();
  $typesSuivi= \App\Models\FicheSuivi::types();
@endphp

<div class="mb-4 flex items-center justify-between flex-wrap gap-3">
    <a href="{{ route('erp.fiches.index') }}" class="text-sm text-gray-400 hover:text-red-500">
        <i class="bi bi-arrow-left"></i> Retour aux fiches
    </a>
    <form method="POST" action="{{ route('erp.fiches.destroy', $fiche) }}"
          onsubmit="return confirm('Supprimer cette fiche et son historique de suivi ?')">
        @csrf @method('DELETE')
        <button type="submit" class="text-xs text-red-400 hover:underline">
            <i class="bi bi-trash3"></i> Supprimer la fiche
        </button>
    </form>
</div>

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800">
        <ul class="ml-4 list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid gap-6" style="grid-template-columns: 1fr 380px;">

    {{-- ══ Colonne principale : la fiche ══ --}}
    <div>
        {{-- Identité --}}
        <div class="content-card mb-5">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Identification</span>
                <span class="text-[10px] font-bold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded px-1.5 py-0.5 uppercase">
                    {{ $types[$fiche->type_organisation] ?? $fiche->type_organisation }}
                </span>
                @if ($fiche->est_qualifie)
                    <span class="text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">QUALIFIÉ</span>
                @endif
            </div>
            <div class="p-5 grid gap-x-6 gap-y-3" style="grid-template-columns: repeat(2,1fr);">
                @foreach ([
                    'Nom commercial' => $fiche->nom_commercial,
                    'Secteur' => $secteurs[$fiche->secteur] ?? $fiche->secteur,
                    'Adresse' => $fiche->adresse,
                    'Commune' => $fiche->commune,
                    'Téléphone' => $fiche->telephone,
                    'Email' => $fiche->email,
                    'Employés' => $tailles[$fiche->taille_employes] ?? $fiche->taille_employes,
                    'Année de création' => $fiche->reponse('annee_creation'),
                    'Site web' => $fiche->reponse('site_web_url'),
                    'Facebook' => $fiche->reponse('facebook'),
                    'Instagram' => $fiche->reponse('instagram'),
                    'WhatsApp Business' => $fiche->reponse('whatsapp_business'),
                ] as $k => $v)
                    @if (filled($v))
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400">{{ $k }}</div>
                            <div class="text-sm text-gray-800 dark:text-gray-200 break-words">{{ $v }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Contact et décideur --}}
        <div class="content-card mb-5">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Personne rencontrée</span>
            </div>
            <div class="p-5 grid gap-x-6 gap-y-3" style="grid-template-columns: repeat(2,1fr);">
                @foreach ([
                    'Nom' => $fiche->contact_nom,
                    'Fonction' => $fonctions[$fiche->contact_fonction] ?? $fiche->contact_fonction,
                    'Téléphone' => $fiche->contact_telephone,
                    'Email' => $fiche->contact_email,
                    'Est décideur' => $fiche->reponse('est_decideur_brut') === 'partiel' ? 'Partiellement' : ($fiche->est_decideur === null ? null : ($fiche->est_decideur ? 'Oui' : 'Non')),
                    'Nom du décideur' => $fiche->decideur_nom,
                    'Contact décideur' => $fiche->decideur_contact,
                    'Meilleur moment' => $fiche->reponse('moment_contact'),
                ] as $k => $v)
                    @if (filled($v))
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400">{{ $k }}</div>
                            <div class="text-sm text-gray-800 dark:text-gray-200">{{ $v }}</div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- Le questionnaire, section par section --}}
        @php
            $blocsTexte = [
                "Comprendre l'activité" => [
                    'Description' => 'description_activite', 'Produits / services' => 'produits_services',
                    'Principaux clients' => 'principaux_clients', 'Clients par mois' => 'clients_par_mois',
                ],
                'Problèmes identifiés' => [
                    'Tâche la plus chronophage' => 'tache_chronophage',
                    "Tâche générant le plus d'erreurs" => 'tache_erreurs',
                    'Information difficile à retrouver' => 'info_difficile',
                    'À automatiser (1)' => 'automatiser_1', 'À automatiser (2)' => 'automatiser_2',
                    'À automatiser (3)' => 'automatiser_3',
                    'Problème opérationnel principal' => 'probleme_principal',
                    "Frein à l'efficacité" => 'frein_efficacite',
                    'À améliorer sous 6-12 mois' => 'amelioration_12_mois',
                ],
                'Numérique et vente' => [
                    'Site web' => 'a_site_web', 'Site fonctionnel' => 'site_fonctionnel',
                    'Site à jour' => 'site_a_jour', 'Gestion des réseaux' => 'gestion_reseaux',
                    'Besoin POS' => 'besoin_pos', 'Suivi des ventes' => 'suivi_ventes',
                    'Gestion des stocks' => 'gestion_stocks_besoin',
                    'Plusieurs points de vente' => 'plusieurs_points_vente',
                    'Nombre de points de vente' => 'nombre_points_vente',
                ],
                'TAGTOA' => [
                    'Déjà QR / NFC' => 'deja_qr_nfc', 'Cartes imprimées' => 'cartes_imprimees',
                    'Cartes NFC potentielles' => 'nb_cartes_nfc', 'Intérêt' => 'interet_tagtoa',
                ],
                'KLASYO' => [
                    'Organise des formations' => 'organise_formations',
                    'Fréquence' => 'frequence_formations', 'Intérêt' => 'interet_klasyo',
                ],
                'Écoles' => [
                    "Nombre d'élèves" => 'nb_eleves', "Nombre d'enseignants" => 'nb_enseignants',
                    'Plateforme éducative' => 'a_plateforme_educative',
                    'Participants par formation' => 'nb_participants_formation',
                ],
                'Budget' => [
                    'Budget numérique' => 'a_budget', 'Montant approximatif' => 'budget_approx',
                    'Prêt à investir' => 'pret_investir',
                ],
                'Observation terrain' => [
                    'Niveau de digitalisation' => 'niveau_digitalisation',
                    'Outils utilisés' => 'outils_utilises',
                    'Problème observé' => 'probleme_observe',
                    'Opportunité GOVIBE' => 'opportunite_govibe',
                    'Photos autorisées' => 'photos_autorisees',
                    'Documents disponibles' => 'documents_disponibles',
                ],
            ];
            // Toutes les listes à cocher, à plat, pour les rattacher au bon bloc.
            $cochables = [];
            foreach ($questions as $groupe) {
                foreach ($groupe['champs'] as $cle => $champ) {
                    $cochables[$cle] = $champ['label'];
                }
            }
            $cochables['solutions'] = 'Solutions potentielles';
        @endphp

        @foreach ($blocsTexte as $titre => $champs)
            @php
                $lignes = collect($champs)->filter(fn ($cle) => filled($fiche->reponse($cle)));
            @endphp
            @if ($lignes->isNotEmpty())
                <div class="content-card mb-5">
                    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                        <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $titre }}</span>
                    </div>
                    <div class="p-5 space-y-3">
                        @foreach ($lignes as $label => $cle)
                            <div>
                                <div class="text-[10px] uppercase tracking-wide text-gray-400">{{ $label }}</div>
                                <div class="text-sm text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ $fiche->reponseLisible($cle) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Listes à cocher --}}
        @php $listes = collect($cochables)->filter(fn ($l, $cle) => filled($fiche->reponse($cle))); @endphp
        @if ($listes->isNotEmpty())
            <div class="content-card mb-5">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Réponses à choix multiples</span>
                </div>
                <div class="p-5 space-y-3">
                    @foreach ($listes as $cle => $label)
                        <div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">{{ $label }}</div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ((array) $fiche->reponse($cle) as $v)
                                    <span class="text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-full px-2.5 py-0.5">{{ $v }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Ce qui reste manuel : la matière première de la proposition --}}
        @php $etats = (array) $fiche->reponse('gestion', []); @endphp
        @if (array_filter($etats))
            <div class="content-card mb-5">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Gestion interne</span>
                    <span class="text-xs text-gray-400 ml-2">ce qui est encore fait à la main</span>
                </div>
                <div class="p-5 flex flex-wrap gap-2">
                    @foreach ($gestion as $cle => $libelle)
                        @php $etat = $etats[$cle] ?? null; @endphp
                        @if ($etat)
                            <span class="text-xs rounded-lg px-2.5 py-1 border
                                {{ $etat === 'manuel' ? 'border-amber-300 bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800'
                                   : ($etat === 'non' ? 'border-red-200 bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800'
                                   : 'border-green-200 bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800') }}">
                                {{ $libelle }} · {{ ucfirst($etat) }}
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($fiche->observation_agent)
            <div class="content-card mb-5">
                <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                    <span class="font-semibold text-gray-800 dark:text-gray-100">Observations de l'agent</span>
                </div>
                <div class="p-5 text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $fiche->observation_agent }}</div>
            </div>
        @endif
    </div>

    {{-- ══ Colonne latérale : qualification et suivi ══ --}}
    <div>
        {{-- Qualification --}}
        <div class="content-card mb-5">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Qualification</span>
            </div>
            <div class="p-5">
                <div class="flex items-center gap-4 mb-4">
                    <div class="text-center">
                        <div class="text-3xl font-extrabold text-gray-800 dark:text-white tabular-nums">{{ $fiche->score_total }}<span class="text-lg text-gray-400">/8</span></div>
                        <div class="text-[10px] uppercase tracking-wide text-gray-400">Score</div>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                        Besoin : <strong>{{ $niveaux[$fiche->score_besoin] ?? $fiche->score_besoin }}</strong><br>
                        Potentiel : <strong>{{ $potentiels[$fiche->score_potentiel] ?? $fiche->score_potentiel }}</strong>
                    </div>
                </div>

                <form method="POST" action="{{ route('erp.fiches.qualification', $fiche) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Besoin numérique</label>
                        <select name="score_besoin" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            @foreach ($niveaux as $n => $l)<option value="{{ $n }}" @selected($fiche->score_besoin === $n)>{{ $n }} — {{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Potentiel commercial</label>
                        <select name="score_potentiel" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            @foreach ($potentiels as $n => $l)<option value="{{ $n }}" @selected($fiche->score_potentiel === $n)>{{ $n }} — {{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Statut</label>
                        <select name="statut" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            @foreach ($statuts as $v => $l)<option value="{{ $v }}" @selected($fiche->statut === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Prochaine action</label>
                        <select name="prochaine_action" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            <option value="">—</option>
                            @foreach ($actions as $v => $l)<option value="{{ $v }}" @selected($fiche->prochaine_action === $v)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3 grid gap-2" style="grid-template-columns:1fr 1fr;">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Relance</label>
                            <input type="date" name="date_relance" value="{{ $fiche->date_relance?->format('Y-m-d') }}"
                                   class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Responsable</label>
                            <input type="text" name="responsable_assigne" value="{{ $fiche->responsable_assigne }}"
                                   class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary text-xs w-full py-1.5">
                        <i class="bi bi-check-lg"></i> Mettre à jour
                    </button>
                </form>

                <div class="mt-4 pt-3 border-t border-gray-100 dark:border-slate-700 text-xs text-gray-400 space-y-1">
                    <div>Collectée par <strong class="text-gray-600 dark:text-gray-300">{{ $fiche->agent ?: 'inconnu' }}</strong></div>
                    <div>Le {{ $fiche->created_at->format('d/m/Y à H:i') }}</div>
                </div>
            </div>
        </div>

        {{-- Ajouter un suivi --}}
        <div class="content-card mb-5">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Ajouter un suivi</span>
            </div>
            <div class="p-5">
                <form method="POST" action="{{ route('erp.fiches.suivi', $fiche) }}">
                    @csrf
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            @foreach ($typesSuivi as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Ce qui a été fait ou dit <span class="text-red-500">*</span></label>
                        <textarea name="message" rows="3" required
                                  placeholder="Appelé le directeur, présenté KLASYO..."
                                  class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200 resize-y"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Réponse du prospect</label>
                        <textarea name="reponse_prospect" rows="2"
                                  placeholder="Ce que le prospect a répondu"
                                  class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200 resize-y"></textarea>
                    </div>
                    <div class="mb-3 grid gap-2" style="grid-template-columns:1fr 1fr;">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Agent</label>
                            <input type="text" name="agent" placeholder="Votre nom"
                                   class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Relance</label>
                            <input type="date" name="date_relance"
                                   class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="block text-xs text-gray-500 mb-1">Faire passer au statut</label>
                        <select name="statut_apres" class="w-full border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1.5 text-sm dark:bg-slate-700 dark:text-gray-200">
                            <option value="">Laisser inchangé</option>
                            @foreach ($statuts as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-primary text-xs w-full py-1.5">
                        <i class="bi bi-plus-lg"></i> Enregistrer le suivi
                    </button>
                </form>
            </div>
        </div>

        {{-- Historique --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Historique</span>
                <span class="text-xs text-gray-400">{{ $fiche->suivis->count() }}</span>
            </div>
            <div class="divide-y divide-gray-50 dark:divide-slate-700/60">
                @forelse ($fiche->suivis as $s)
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="text-[10px] font-bold rounded px-1.5 py-0.5 uppercase
                                {{ $s->est_echange ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300' }}">
                                {{ $s->type_libelle }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $s->created_at->format('d/m/Y H:i') }}</span>
                            @if ($s->agent)<span class="text-xs text-gray-400">· {{ $s->agent }}</span>@endif
                        </div>
                        <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-line">{{ $s->message }}</p>
                        @if ($s->reponse_prospect)
                            <div class="mt-2 pl-3 border-l-2 border-gray-200 dark:border-slate-600">
                                <div class="text-[10px] uppercase tracking-wide text-gray-400">Réponse du prospect</div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 whitespace-pre-line">{{ $s->reponse_prospect }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">
                        <i class="bi bi-chat-left text-2xl mb-2 block opacity-30"></i>
                        Aucun suivi. Ce prospect n'a pas encore été repris.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>

<style>
  @media (max-width: 1100px) {
    .content-card + .content-card { margin-top: 0; }
    [style*="grid-template-columns: 1fr 380px"] { grid-template-columns: 1fr !important; }
  }
  @media (max-width: 640px) {
    [style*="grid-template-columns: repeat(2,1fr)"] { grid-template-columns: 1fr !important; }
  }
</style>

@endsection
