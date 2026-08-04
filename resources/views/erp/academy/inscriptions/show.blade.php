@extends('erp.layouts.app')
@section('title','Inscription — ' . $inscription->nom_complet)
@section('page-title','Détails inscription')
@section('page-subtitle','Fiche participant — Academy GOVIBE')

@section('content')

<div class="mb-4">
    <a href="{{ route('erp.academy.inscriptions.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
        <i class="bi bi-arrow-left"></i> Retour aux inscriptions
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main info --}}
    <div class="lg:col-span-2 space-y-5">

        {{-- Identity card --}}
        <div class="content-card">
            <div class="flex items-start justify-between px-6 py-5 border-b border-gray-100 dark:border-slate-700">
                <div class="flex items-center gap-4">
                    <div class="avatar avatar-navy w-14 h-14 text-xl">
                        {{ strtoupper(substr($inscription->nom_complet, 0, 1)) }}
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800 dark:text-white">{{ $inscription->nom_complet }}</h2>
                        <p class="text-sm text-gray-400 font-mono">{{ $inscription->numero_inscription }}</p>
                    </div>
                </div>
                <span class="badge text-xs {{ $inscription->present ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    <i class="bi {{ $inscription->present ? 'bi-check-circle-fill' : 'bi-circle' }} mr-1"></i>
                    {{ $inscription->present ? 'Présent(e)' : 'Absent(e)' }}
                </span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-0 divide-y md:divide-y-0 divide-gray-50 dark:divide-slate-700/50">
                @foreach([
                    ['Sexe',          $inscription->sexe           ?? '—', 'bi-gender-ambiguous'],
                    ['Date naissance', $inscription->date_naissance?->format('d/m/Y') ?? '—', 'bi-calendar3'],
                    ['Téléphone',     $inscription->telephone       ?? '—', 'bi-telephone'],
                    ['Email',         $inscription->email           ?? '—', 'bi-envelope'],
                    ['Département',   $inscription->departement     ?? '—', 'bi-geo-alt'],
                    ['Ville',         $inscription->ville           ?? '—', 'bi-building'],
                    ['Profession',    $inscription->profession      ?? '—', 'bi-briefcase'],
                    ['Niveau étude',  $inscription->niveau_etude    ?? '—', 'bi-mortarboard'],
                    ['Source info',   $inscription->source_info     ?? '—', 'bi-megaphone'],
                ] as [$label, $value, $icon])
                <div class="px-5 py-4 border-b border-gray-50 dark:border-slate-700/50">
                    <p class="text-xs text-gray-400 mb-1 flex items-center gap-1">
                        <i class="bi {{ $icon }} text-gray-300"></i> {{ $label }}
                    </p>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 break-all">{{ $value }}</p>
                </div>
                @endforeach
            </div>

            @if($inscription->objectif || $inscription->attentes)
            <div class="border-t border-gray-100 dark:border-slate-700 divide-y divide-gray-50 dark:divide-slate-700/50">
                @if($inscription->objectif)
                <div class="px-6 py-4">
                    <p class="text-xs text-gray-400 mb-1 font-medium uppercase tracking-wide">Objectif</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->objectif }}</p>
                </div>
                @endif
                @if($inscription->attentes)
                <div class="px-6 py-4">
                    <p class="text-xs text-gray-400 mb-1 font-medium uppercase tracking-wide">Attentes</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->attentes }}</p>
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Formation card --}}
        @if($inscription->formation)
        <div class="content-card">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <i class="bi bi-mortarboard-fill text-navy"></i>
                    Formation associée
                </h3>
            </div>
            <div class="px-6 py-5 grid grid-cols-2 md:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-400 mb-1">Intitulé</p>
                    <p class="text-sm font-semibold text-gray-800 dark:text-white">{{ $inscription->formation->nom }}</p>
                </div>
                @if($inscription->formation->lieu)
                <div>
                    <p class="text-xs text-gray-400 mb-1">Lieu</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->formation->lieu }}</p>
                </div>
                @endif
                @if($inscription->formation->date_debut)
                <div>
                    <p class="text-xs text-gray-400 mb-1">Date début</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->formation->date_debut->format('d/m/Y') }}</p>
                </div>
                @endif
                @if($inscription->formation->date_fin)
                <div>
                    <p class="text-xs text-gray-400 mb-1">Date fin</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->formation->date_fin->format('d/m/Y') }}</p>
                </div>
                @endif
                <div>
                    <p class="text-xs text-gray-400 mb-1">Statut</p>
                    <span class="badge text-xs {{ $inscription->formation->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $inscription->formation->active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                @if($inscription->formation->max_participants)
                <div>
                    <p class="text-xs text-gray-400 mb-1">Capacité max</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $inscription->formation->max_participants }} places</p>
                </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    {{-- Sidebar actions --}}
    <div class="space-y-5">

        {{-- Actions --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white">Actions</h3>
            </div>
            <div class="p-5 space-y-3">
                {{-- Toggle présence --}}
                <form method="POST" action="{{ route('erp.academy.inscriptions.presence', $inscription) }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-semibold text-sm transition-all
                                {{ $inscription->present
                                    ? 'bg-red-50 text-red-700 hover:bg-red-100 border border-red-200'
                                    : 'bg-green-50 text-green-700 hover:bg-green-100 border border-green-200' }}">
                        <i class="bi {{ $inscription->present ? 'bi-person-x-fill' : 'bi-person-check-fill' }}"></i>
                        {{ $inscription->present ? 'Marquer absent(e)' : 'Marquer présent(e)' }}
                    </button>
                </form>

                {{-- Attestation PDF --}}
                <a href="{{ route('erp.academy.inscriptions.attestation', $inscription) }}"
                   class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-semibold text-sm
                          bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200 transition-all">
                    <i class="bi bi-file-earmark-pdf-fill"></i>
                    Télécharger attestation
                </a>

                {{-- Delete --}}
                <form method="POST" action="{{ route('erp.academy.inscriptions.destroy', $inscription) }}"
                      onsubmit="return confirm('Supprimer définitivement cette inscription ?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 py-2.5 px-4 rounded-xl font-semibold text-sm
                                   bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition-all">
                        <i class="bi bi-trash-fill"></i>
                        Supprimer l'inscription
                    </button>
                </form>
            </div>
        </div>

        {{-- Timestamps --}}
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white text-sm">Historique</h3>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <div class="flex items-start gap-3">
                    <i class="bi bi-calendar-plus text-gray-300 mt-0.5"></i>
                    <div>
                        <p class="text-xs text-gray-400">Inscrit le</p>
                        <p class="font-medium text-gray-700 dark:text-gray-300">
                            {{ $inscription->created_at?->format('d/m/Y à H:i') ?? '—' }}
                        </p>
                    </div>
                </div>
                @if($inscription->scanned_at)
                <div class="flex items-start gap-3">
                    <i class="bi bi-qr-code-scan text-green-400 mt-0.5"></i>
                    <div>
                        <p class="text-xs text-gray-400">Présence enregistrée le</p>
                        <p class="font-medium text-gray-700 dark:text-gray-300">
                            {{ $inscription->scanned_at->format('d/m/Y à H:i') }}
                        </p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- QR code --}}
        @if($inscription->qr_code)
        <div class="content-card">
            <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
                <h3 class="font-semibold text-gray-800 dark:text-white text-sm">QR Code</h3>
            </div>
            <div class="p-5 text-center">
                <img src="{{ $inscription->qr_code }}" alt="QR" class="mx-auto w-32 h-32">
                <p class="text-xs text-gray-400 mt-2">Scanner pour check-in</p>
            </div>
        </div>
        @endif

    </div>
</div>

@endsection
