@extends('erp.layouts.app')

@section('title', 'Inscription '.$inscription->reference)
@section('page-title', $inscription->reference)
@section('page-subtitle', $inscription->nom_complet.' — '.($inscription->session?->titre ?? 'formation'))

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.formations.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les inscriptions
</a>

<div class="grid gap-5" style="grid-template-columns: 1.15fr .85fr;">

    <div class="content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
            <span class="font-semibold text-gray-800 dark:text-gray-100">Preuve de paiement</span>
            @if ($inscription->fichier)
                <a href="{{ route('erp.formations.fichier', $inscription) }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
                    <i class="bi bi-box-arrow-up-right"></i> Ouvrir en grand
                </a>
            @endif
        </div>
        <div class="p-5">
            @if (! $inscription->fichier)
                <p class="text-sm text-gray-400">Aucun fichier joint.</p>
            @elseif ($inscription->fichier_mime === 'application/pdf')
                <a href="{{ route('erp.formations.fichier', $inscription) }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 rounded-xl border border-gray-200 dark:border-slate-600 px-4 py-4 hover:border-red-400">
                    <i class="bi bi-filetype-pdf text-3xl text-red-600"></i>
                    <span>
                        <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $inscription->fichier_nom_origine }}</span>
                        <span class="block text-xs text-gray-400">{{ $inscription->taille_lisible }} — ouvrir le PDF</span>
                    </span>
                </a>
            @else
                <img src="{{ route('erp.formations.fichier', $inscription) }}" alt="Preuve {{ $inscription->reference }}"
                     class="w-full rounded-xl border border-gray-200 dark:border-slate-600">
                <p class="text-xs text-gray-400 mt-2">{{ $inscription->fichier_nom_origine }} — {{ $inscription->taille_lisible }}</p>
            @endif
        </div>
    </div>

    <div class="flex flex-col gap-5">
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Le participant</span>
            </div>
            <div class="p-5 text-sm space-y-2.5">
                @foreach ([
                    'Nom complet' => $inscription->nom_complet,
                    'WhatsApp'    => $inscription->whatsapp,
                    'Formation'   => $inscription->session?->titre,
                    'Suivi'       => $inscription->mode_lisible,
                    'Montant'     => $inscription->montant_affiche,
                    'Moyen'       => $inscription->moyen_paiement_nom ?: $inscription->moyen_paiement,
                    'Inscrit le'  => $inscription->created_at->format('d/m/Y à H:i'),
                ] as $label => $valeur)
                    @if ($valeur)
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-400">{{ $label }}</span>
                            <span class="text-gray-800 dark:text-gray-100 font-medium text-right">{{ $valeur }}</span>
                        </div>
                    @endif
                @endforeach

                <a href="https://wa.me/{{ preg_replace('/\D+/', '', $inscription->whatsapp) }}" target="_blank" rel="noopener"
                   class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
                   style="background:#25D366">
                    <i class="bi bi-whatsapp"></i> Répondre sur WhatsApp
                </a>
            </div>
        </div>

        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Vérification</span>
            </div>
            <form method="POST" action="{{ route('erp.formations.update', $inscription) }}" class="p-5 space-y-3">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Statut</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\InscriptionSession::statuts() as $v => $l)
                            <option value="{{ $v }}" @selected($inscription->statut === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Commentaire interne</label>
                    <textarea name="commentaire_admin" rows="3" maxlength="2000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">{{ $inscription->commentaire_admin }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-sm">Enregistrer</button>

                @if ($inscription->verifiee_le)
                    <p class="text-xs text-gray-400 text-center">
                        Vérifiée le {{ $inscription->verifiee_le->format('d/m/Y à H:i') }}
                        @if ($inscription->verifiee_par) par {{ $inscription->verifiee_par }} @endif
                    </p>
                @endif
            </form>
        </div>

        <form method="POST" action="{{ route('erp.formations.destroy', $inscription) }}"
              onsubmit="return confirm('Supprimer cette inscription et sa preuve ? Action définitive.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-trash"></i> Supprimer cette inscription
            </button>
        </form>
    </div>
</div>

@endsection
