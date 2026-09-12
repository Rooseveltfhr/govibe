@extends('erp.layouts.app')

@section('title', 'Réservation '.$reservation->reference)
@section('page-title', $reservation->reference)
@section('page-subtitle', $reservation->nom_complet.' — '.$reservation->mode_service_libelle)

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif

<a href="{{ route('erp.landry.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Toutes les réservations
</a>

<div class="grid gap-5" style="grid-template-columns: 1.1fr .9fr;">

    <div class="content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <span class="font-semibold text-gray-800 dark:text-gray-100">La demande</span>
        </div>
        <div class="p-5 text-sm space-y-2.5">
            @php
                $lignes = [
                    'Nom complet'   => $reservation->nom_complet,
                    'WhatsApp'      => $reservation->whatsapp,
                    'Point de repère' => $reservation->point_repere,
                    'Service'       => $reservation->mode_service_libelle,
                    'Facturation'   => $reservation->mode_facturation_libelle,
                    'Quantité'      => $reservation->quantite_vetements ? $reservation->quantite_vetements.' vêtements' : null,
                    'Fréquence'     => $reservation->frequence_libelle,
                    'Paiement'      => $reservation->mode_paiement_libelle,
                    'Inscription'   => $reservation->accepte_frais_inscription
                        ? 'Acceptée — '.$reservation->frais_affiche
                        : "Refusée — souhaite plus d'informations",
                    'Reçue le'      => $reservation->created_at->format('d/m/Y à H:i'),
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

            <div class="pt-3 mt-2 border-t border-gray-100 dark:border-slate-700">
                <span class="text-gray-400 block mb-1">Adresse</span>
                <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $reservation->adresse }}</p>
            </div>

            @if ($reservation->instructions_recuperation)
                <div class="pt-3 border-t border-gray-100 dark:border-slate-700">
                    <span class="text-gray-400 block mb-1">Instructions pour la récupération</span>
                    <p class="text-gray-700 dark:text-gray-200 whitespace-pre-line">{{ $reservation->instructions_recuperation }}</p>
                </div>
            @endif

            <a href="https://wa.me/{{ preg_replace('/\D+/', '', $reservation->whatsapp) }}" target="_blank" rel="noopener"
               class="mt-3 flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white"
               style="background:#25D366">
                <i class="bi bi-whatsapp"></i> Écrire au client
            </a>
        </div>
    </div>

    <div class="flex flex-col gap-5">
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <span class="font-semibold text-gray-800 dark:text-gray-100">Suivi</span>
            </div>
            <form method="POST" action="{{ route('erp.landry.update', $reservation) }}" class="p-5 space-y-3">
                @csrf @method('PATCH')

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Statut</label>
                    <select name="statut" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                        @foreach (\App\Models\ReservationLandry::statuts() as $v => $l)
                            <option value="{{ $v }}" @selected($reservation->statut === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs text-gray-400 mb-1.5">Notes internes</label>
                    <textarea name="notes_internes" rows="4" maxlength="2000"
                              class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">{{ $reservation->notes_internes }}</textarea>
                </div>

                <button type="submit" class="btn-primary w-full text-sm">Enregistrer</button>

                @if ($reservation->traitee_le)
                    <p class="text-xs text-gray-400 text-center">
                        Traitée le {{ $reservation->traitee_le->format('d/m/Y à H:i') }}
                        @if ($reservation->agent) par {{ $reservation->agent->name }} @endif
                    </p>
                @endif
            </form>
        </div>

        <form method="POST" action="{{ route('erp.landry.destroy', $reservation) }}"
              onsubmit="return confirm('Supprimer cette réservation ? Action définitive.')">
            @csrf @method('DELETE')
            <button type="submit" class="text-xs text-gray-400 hover:text-red-500">
                <i class="bi bi-trash"></i> Supprimer cette réservation
            </button>
        </form>
    </div>
</div>

@endsection
