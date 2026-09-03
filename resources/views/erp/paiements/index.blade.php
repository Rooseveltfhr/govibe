@extends('erp.layouts.app')

@section('title', 'Moyens de paiement')
@section('page-title', 'Moyens de paiement')
@section('page-subtitle', 'Coordonnées affichées aux clients pour régler')

@section('content')

{{-- Stats --}}
<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Moyens', $stats['total'], 'bi-credit-card-2-front-fill', '#1e3a5f', '#dbeafe'],
        ['Actifs', $stats['actives'], 'bi-check-circle-fill', '#059669', '#d1fae5'],
        ['À compléter', $stats['incompletes'], 'bi-exclamation-triangle-fill', '#b45309', '#fef3c7'],
        ['Sans QR code', $stats['sans_qr'], 'bi-qr-code', '#7c3aed', '#ede9fe'],
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

@if ($errors->any())
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800">
        <strong>Corrigez les points suivants :</strong>
        <ul class="mt-1 ml-4 list-disc">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

@if ($stats['incompletes'] > 0)
    <div class="mb-6 rounded-xl px-4 py-3 text-sm bg-amber-50 text-amber-800 border border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800">
        <strong>{{ $stats['incompletes'] }} moyen(s) sans coordonnées.</strong>
        Ils sont masqués du site tant qu'un numéro de compte ou un lien n'est pas renseigné —
        un client ne peut pas payer sans cette information.
    </div>
@endif

<div class="mb-4 text-sm text-gray-500 dark:text-gray-400">
    Page publique :
    <a href="{{ route('paiement') }}" target="_blank" rel="noopener" class="text-red-500 hover:underline">
        {{ route('paiement') }} <i class="bi bi-box-arrow-up-right"></i>
    </a>
</div>

{{-- Ajouter --}}
<div class="content-card mb-6" x-data="{ ouvert: {{ $errors->any() && ! old('_edition') ? 'true' : 'false' }} }">
    <button @click="ouvert = !ouvert" class="w-full flex items-center justify-between px-5 py-4 text-left">
        <span class="font-semibold text-gray-800 dark:text-gray-100">
            <i class="bi bi-plus-circle text-red-500"></i>
            Ajouter un moyen de paiement
        </span>
        <i class="bi" :class="ouvert ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </button>
    <div x-show="ouvert" x-cloak class="px-5 pb-5 border-t border-gray-100 dark:border-slate-700 pt-4">
        <form method="POST" action="{{ route('erp.paiements.store') }}" enctype="multipart/form-data">
            @csrf
            @include('erp.paiements._form', ['passerelle' => null])
            <button type="submit" class="btn-primary mt-4">
                <i class="bi bi-check-lg"></i> Ajouter
            </button>
        </form>
    </div>
</div>

{{-- Liste --}}
<div class="content-card">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Moyens configurés</span>
        <span class="text-xs text-gray-400">{{ $passerelles->count() }}</span>
    </div>

    <div class="divide-y divide-gray-50 dark:divide-slate-700/60">
        @forelse ($passerelles as $p)
            <div class="p-5" x-data="{ edition: false }">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-4 min-w-0 flex-1">
                        {{-- Logo, ou initiales tant qu'aucun n'est téléversé --}}
                        <div class="w-12 h-12 rounded-xl bg-gray-100 dark:bg-slate-700 flex items-center justify-center overflow-hidden shrink-0 font-extrabold text-red-600">
                            @if ($p->logo_url)
                                <img src="{{ $p->logo_url }}" alt="" class="max-w-full max-h-full object-contain">
                            @else
                                {{ $p->initiales }}
                            @endif
                        </div>

                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-bold text-gray-800 dark:text-gray-100">{{ $p->nom }}</h3>
                                <span class="text-[10px] font-bold bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded px-1.5 py-0.5 uppercase">
                                    {{ $p->type_libelle }}
                                </span>
                                @if ($p->actif)
                                    <span class="text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">EN LIGNE</span>
                                @else
                                    <span class="text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400 rounded px-1.5 py-0.5">MASQUÉ</span>
                                @endif
                                @if ($p->est_incomplete)
                                    <span class="text-[10px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded px-1.5 py-0.5">À COMPLÉTER</span>
                                @endif
                            </div>

                            <div class="mt-1 text-sm text-gray-600 dark:text-gray-300 break-all">
                                @if ($p->titulaire)
                                    <span class="text-gray-400">Au nom de</span> {{ $p->titulaire }}
                                @endif
                                @if ($p->numero_compte)
                                    <code class="text-xs bg-gray-100 dark:bg-slate-700 rounded px-1.5 py-0.5 ml-1">{{ $p->numero_compte }}</code>
                                @endif
                                @if ($p->lien_paiement)
                                    <a href="{{ $p->lien_paiement }}" target="_blank" rel="noopener" class="text-xs text-red-500 hover:underline ml-1">
                                        lien de paiement <i class="bi bi-box-arrow-up-right"></i>
                                    </a>
                                @endif
                            </div>

                            <div class="mt-1.5 flex flex-wrap gap-3 text-xs text-gray-400">
                                @if ($p->reseau)<span><i class="bi bi-diagram-3"></i> {{ $p->reseau }}</span>@endif
                                <span><i class="bi bi-sort-numeric-down"></i> ordre {{ $p->ordre }}</span>
                                <span>
                                    <i class="bi bi-qr-code"></i>
                                    {{ $p->qr_code_url ? 'QR code en place' : 'aucun QR code' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-2 items-start shrink-0">
                        @if ($p->qr_code_url)
                            <img src="{{ $p->qr_code_url }}" alt="QR {{ $p->nom }}"
                                 class="w-14 h-14 object-contain rounded-lg border border-gray-200 dark:border-slate-600 bg-white">
                        @endif
                        <button @click="edition = !edition" class="btn-secondary text-xs py-1.5 px-3">
                            <i class="bi bi-pencil"></i> Modifier
                        </button>
                        <form method="POST" action="{{ route('erp.paiements.destroy', $p) }}"
                              onsubmit="return confirm('Supprimer « {{ $p->nom }} » ? Il disparaîtra du site.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div x-show="edition" x-cloak class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                    <form method="POST" action="{{ route('erp.paiements.update', $p) }}" enctype="multipart/form-data">
                        @csrf
                        @include('erp.paiements._form', ['passerelle' => $p])
                        <div class="flex gap-2 mt-4">
                            <button type="submit" class="btn-primary text-sm">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                            <button type="button" @click="edition = false" class="btn-secondary text-sm">Annuler</button>
                        </div>
                    </form>

                    @if ($p->qr_code || $p->logo)
                        <div class="flex gap-4 mt-3">
                            @foreach ([['qr_code', 'QR code'], ['logo', 'logo']] as [$champ, $libelle])
                                @if ($p->$champ)
                                    <form method="POST" action="{{ route('erp.paiements.fichier.destroy', $p) }}"
                                          onsubmit="return confirm('Supprimer le {{ $libelle }} ?')">
                                        @csrf @method('DELETE')
                                        <input type="hidden" name="champ" value="{{ $champ }}">
                                        <button type="submit" class="text-xs text-red-500 hover:underline">
                                            <i class="bi bi-trash3"></i> Supprimer le {{ $libelle }}
                                        </button>
                                    </form>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-gray-400">
                <i class="bi bi-credit-card text-4xl mb-3 block opacity-30"></i>
                Aucun moyen de paiement configuré.
            </div>
        @endforelse
    </div>
</div>

@endsection
