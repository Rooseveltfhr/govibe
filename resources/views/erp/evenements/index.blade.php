@extends('erp.layouts.app')

@section('title', 'Événements')
@section('page-title', 'Événements')
@section('page-subtitle', 'Créez un formulaire d\'inscription par activité')

@section('content')

{{-- Stats --}}
<div class="grid gap-4 mb-6" style="grid-template-columns: repeat(4,1fr);">
    @foreach([
        ['Événements', $stats['total'], 'bi-calendar-event', '#1e3a5f', '#dbeafe'],
        ['Actifs', $stats['actifs'], 'bi-broadcast', '#059669', '#d1fae5'],
        ['Inscriptions ouvertes', $stats['ouverts'], 'bi-door-open', '#d97706', '#fef3c7'],
        ['Total inscrits', $stats['reservations'], 'bi-people-fill', '#b91c1c', '#fee2e2'],
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

{{-- Créer un événement --}}
<div class="content-card mb-6" x-data="{ ouvert: {{ $errors->any() ? 'true' : 'false' }} }">
    <button @click="ouvert = !ouvert"
            class="w-full flex items-center justify-between px-5 py-4 text-left">
        <span class="font-semibold text-gray-800 dark:text-gray-100">
            <i class="bi bi-plus-circle text-red-500"></i>
            Créer un nouvel événement
        </span>
        <i class="bi" :class="ouvert ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
    </button>

    <div x-show="ouvert" x-cloak class="px-5 pb-5 border-t border-gray-100 dark:border-slate-700 pt-4">
        <form method="POST" action="{{ route('erp.evenements.store') }}" enctype="multipart/form-data">
            @csrf
            @include('erp.evenements._form', ['evenement' => null])
            <button type="submit" class="btn-primary mt-4">
                <i class="bi bi-check-lg"></i> Créer l'événement
            </button>
        </form>
    </div>
</div>

{{-- Liste --}}
<div class="content-card">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Événements</span>
        <span class="text-xs text-gray-400 ml-2">{{ $evenements->count() }}</span>
    </div>

    <div class="divide-y divide-gray-50 dark:divide-slate-700/60">
        @forelse ($evenements as $ev)
            <div class="p-5" x-data="{ edition: false }">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-bold text-gray-800 dark:text-gray-100 text-lg">{{ $ev->titre }}</h3>
                            @if ($ev->actif)
                                <span class="text-[10px] font-bold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded px-1.5 py-0.5">ACTIF</span>
                            @else
                                <span class="text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400 rounded px-1.5 py-0.5">MASQUÉ</span>
                            @endif
                            @if ($ev->inscriptions_ouvertes)
                                <span class="text-[10px] font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded px-1.5 py-0.5">INSCRIPTIONS OUVERTES</span>
                            @else
                                <span class="text-[10px] font-bold bg-gray-100 text-gray-500 dark:bg-slate-700 dark:text-gray-400 rounded px-1.5 py-0.5">FERMÉ</span>
                            @endif
                        </div>

                        @if ($ev->sous_titre)
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $ev->sous_titre }}</p>
                        @endif

                        <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-400">
                            @if ($ev->dates_libelle)
                                <span><i class="bi bi-calendar3"></i> {{ $ev->dates_libelle }}</span>
                            @endif
                            @if ($ev->lieu)
                                <span><i class="bi bi-geo-alt"></i> {{ $ev->lieu }}</span>
                            @endif
                            <span><i class="bi bi-people"></i> {{ $ev->reservations_count }} inscrit(s)</span>
                        </div>

                        {{-- L'URL à diffuser en publicité --}}
                        <div class="mt-3 flex items-center gap-2 flex-wrap">
                            <code class="text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded px-2 py-1 select-all">{{ route('evenements.show', $ev) }}</code>
                            <button type="button"
                                    onclick="navigator.clipboard.writeText('{{ route('evenements.show', $ev) }}'); this.textContent='Copié';"
                                    class="text-xs text-red-500 hover:underline">Copier le lien</button>
                            <a href="{{ route('evenements.show', $ev) }}" target="_blank" rel="noopener" class="text-xs text-gray-400 hover:text-red-500">
                                <i class="bi bi-box-arrow-up-right"></i> Ouvrir
                            </a>
                        </div>
                    </div>

                    <div class="flex gap-2 items-start shrink-0">
                        <a href="{{ route('erp.evenements.reservations', $ev) }}" class="btn-primary text-xs py-1.5 px-3">
                            <i class="bi bi-list-check"></i> Inscriptions ({{ $ev->reservations_count }})
                        </a>
                        <button @click="edition = !edition" class="btn-secondary text-xs py-1.5 px-3">
                            <i class="bi bi-pencil"></i> Modifier
                        </button>
                        <form method="POST" action="{{ route('erp.evenements.destroy', $ev) }}"
                              onsubmit="return confirm('Supprimer cet événement et ses {{ $ev->reservations_count }} inscription(s) ? Cette action est définitive.')">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-8 h-8 rounded-lg flex items-center justify-center text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 text-sm">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <div x-show="edition" x-cloak class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700">
                    <form method="POST" action="{{ route('erp.evenements.update', $ev) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        @include('erp.evenements._form', ['evenement' => $ev])
                        <div class="flex gap-2 mt-4">
                            <button type="submit" class="btn-primary text-sm">
                                <i class="bi bi-check-lg"></i> Enregistrer
                            </button>
                            <button type="button" @click="edition = false" class="btn-secondary text-sm">Annuler</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-5 py-12 text-center text-gray-400">
                <i class="bi bi-calendar-x text-4xl mb-3 block opacity-30"></i>
                Aucun événement. Créez-en un ci-dessus pour générer son formulaire d'inscription.
            </div>
        @endforelse
    </div>
</div>

@endsection
