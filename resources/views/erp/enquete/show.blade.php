@extends('erp.layouts.app')

@section('title', 'Réponse '.$reponse->reference)
@section('page-title', $reponse->reference)
@section('page-subtitle', $reponse->intitule.' — '.$reponse->typeLibelle($langue))

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.enquete.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les réponses
</a>

<div class="grid gap-5" style="grid-template-columns: 1.15fr .85fr;">

    <div class="flex flex-col gap-5">

        {{-- ── Les difficultés : ce pour quoi l'enquête existe ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Ce qui coince</span>
            </div>
            <div class="p-5 text-sm">
                @if ($reponse->difficultesLibelles($langue))
                    <div class="flex flex-wrap gap-2">
                        @foreach ($reponse->difficultesLibelles($langue) as $difficulte)
                            <span class="rounded-lg px-2.5 py-1.5 text-xs bg-red-50 text-red-700 border border-red-100 dark:bg-red-900/20 dark:text-red-400 dark:border-red-900">
                                {{ $difficulte }}
                            </span>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-400">Aucune difficulté cochée.</p>
                @endif

                @if ($reponse->freinsLibelles($langue))
                    <div class="pt-4 mt-4 border-t border-gray-100 dark:border-slate-700">
                        <span class="text-gray-400 block mb-1.5 text-xs uppercase tracking-wide">Ce qui l'empêcherait d'adopter un outil</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($reponse->freinsLibelles($langue) as $frein)
                                <span class="rounded-lg px-2 py-1 text-xs bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-900">
                                    {{ $frein }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @foreach ([
                    'Pourquoi il a abandonné son outil précédent' => $reponse->essai_raison,
                    'Ce qui fait perdre le plus de temps' => $reponse->perte_de_temps,
                    'Ce qui fait perdre de l\'argent ou des clients' => $reponse->pertes,
                    'La priorité si une seule chose pouvait changer' => $reponse->priorite,
                ] as $label => $texte)
                    @if ($texte)
                        <div class="pt-4 mt-4 border-t border-gray-100 dark:border-slate-700">
                            <span class="text-gray-400 block mb-1 text-xs uppercase tracking-wide">{{ $label }}</span>
                            <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $texte }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- ── L'activité ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">L'activité</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @php
                    $lignes = [
                        'Entreprise' => $reponse->entreprise,
                        'Type' => $reponse->typeLibelle($langue),
                        'Ville' => $reponse->ville,
                        'Département' => $reponse->departementLibelle($langue),
                        'Effectif' => $reponse->effectifLibelle($langue),
                        'Ancienneté' => $reponse->ancienneteLibelle($langue),
                        'Nombre de sites' => $reponse->pointsVenteLibelle($langue),
                        'Volume par jour' => $reponse->volumeLibelle($langue),
                        'Internet sur place' => $reponse->connexionLibelle($langue),
                        'Budget envisagé' => $reponse->budgetLibelle($langue),
                        'Voudrait commencer' => $reponse->echeanceLibelle($langue),
                        'A déjà essayé un outil' => $reponse->essaiLibelle($langue),
                        'Répondu en' => \App\Enquete\Questionnaire::langues()[$reponse->langue] ?? $reponse->langue,
                        'Reçue le' => $reponse->created_at->format('d/m/Y à H:i'),
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
            </div>
        </div>

        {{-- ── Comment elle est tenue ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Comment elle est tenue aujourd'hui</span>
            </div>
            <div class="p-5 text-sm space-y-4">
                @foreach ([
                    'Matériel disponible' => $reponse->materielLibelles($langue),
                    'Outils actuels' => $reponse->outilsLibelles($langue),
                    'Encaissement' => $reponse->encaissementsLibelles($langue),
                    'Présence en ligne' => $reponse->presencesLibelles($langue),
                    'Solutions qui l\'intéressent' => $reponse->solutionsLibelles($langue),
                ] as $label => $valeurs)
                    <div>
                        <span class="text-gray-400 block mb-1.5 text-xs uppercase tracking-wide">{{ $label }}</span>
                        @if ($valeurs)
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($valeurs as $valeur)
                                    <span class="rounded-lg px-2 py-1 text-xs bg-gray-50 text-gray-600 border border-gray-200 dark:bg-slate-700 dark:text-gray-300 dark:border-slate-600">
                                        {{ $valeur }}
                                    </span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-gray-300 dark:text-slate-600 text-xs">—</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="flex flex-col gap-5">

        {{-- ── Contact ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Contact</span>
            </div>
            <div class="p-5 text-sm">
                @if ($reponse->souhaite_contact)
                    <p class="mb-3 rounded-xl px-3 py-2 text-xs bg-violet-50 text-violet-700 border border-violet-200 dark:bg-violet-900/20 dark:text-violet-400 dark:border-violet-900">
                        Cette personne a demandé à être recontactée.
                    </p>
                    <div class="space-y-2.5">
                        @foreach (['Nom' => $reponse->nom_contact, 'WhatsApp' => $reponse->whatsapp, 'E-mail' => $reponse->email] as $label => $valeur)
                            @if ($valeur)
                                <div class="flex justify-between gap-3">
                                    <span class="text-gray-400">{{ $label }}</span>
                                    <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                    @if ($reponse->whatsapp)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $reponse->whatsapp) }}" target="_blank" rel="noopener"
                           class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
                           style="background:#25D366">
                            <i class="bi bi-whatsapp"></i> Écrire
                        </a>
                    @endif
                @else
                    {{-- Les coordonnées ne sont pas enregistrées quand le rappel
                         est refusé : il n'y a donc rien à afficher ici. --}}
                    <p class="text-gray-500 dark:text-gray-400">
                        Cette personne a répondu à l'enquête sans demander à être recontactée.
                        Aucune coordonnée n'a été conservée.
                    </p>
                @endif
            </div>
        </div>

        {{-- ── Suivi ── --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Suivi</span>
            </div>
            <form method="POST" action="{{ route('erp.enquete.update', $reponse) }}" class="p-5 space-y-3">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Statut</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach ($statuts as $v => $l)
                            <option value="{{ $v }}" @selected($reponse->statut === $v)>{{ $l['fr'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Notes internes</label>
                    <textarea name="notes_internes" rows="5" maxlength="2000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">{{ $reponse->notes_internes }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-sm">Enregistrer</button>

                @if ($reponse->traitee_le)
                    <p class="text-xs text-gray-400 text-center">
                        Traitée le {{ $reponse->traitee_le->format('d/m/Y à H:i') }}
                        @if ($reponse->agent) par {{ $reponse->agent->name }} @endif
                    </p>
                @endif
            </form>
        </div>

        <form method="POST" action="{{ route('erp.enquete.destroy', $reponse) }}"
              onsubmit="return confirm('Supprimer cette réponse ? Action définitive.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-trash"></i> Supprimer cette réponse
            </button>
        </form>
    </div>
</div>

@endsection
