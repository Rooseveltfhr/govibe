@extends('erp.layouts.app')

@section('title', 'Configuration des paiements')
@section('page-title', 'Configuration des paiements')
@section('page-subtitle', 'Passerelles, clés API et taux de change')

@section('content')

@if (session('success'))
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-green-50 text-green-700 border border-green-200 dark:bg-green-900/20 dark:text-green-400 dark:border-green-800">
        {{ session('success') }}
    </div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-xl px-4 py-3 text-sm bg-red-50 text-red-700 border border-red-200 dark:bg-red-900/20 dark:text-red-400">
        <ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<a href="{{ route('erp.transactions.index') }}" class="text-sm text-gray-400 hover:text-red-500 mb-4 inline-block">
    <i class="bi bi-arrow-left"></i> Transactions
</a>

{{-- Taux de change : il conditionne l'affichage des montants dans l'autre devise. --}}
<div class="content-card mb-6">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
        <span class="font-semibold text-gray-800 dark:text-gray-100">Taux de change</span>
        <span class="text-xs {{ $tauxActuel ? 'text-gray-400' : 'text-amber-600 dark:text-amber-400' }}">
            @if ($tauxActuel)
                1 USD = {{ rtrim(rtrim(number_format($tauxActuel, 4, ',', ' '), '0'), ',') }} HTG
            @else
                Aucun taux réglé — les montants ne s'affichent que dans leur devise d'origine
            @endif
        </span>
    </div>
    <div class="p-5">
        <form method="POST" action="{{ route('erp.transactions.taux') }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs text-gray-400 mb-1">De</label>
                <select name="devise_source" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    <option value="USD">USD</option><option value="HTG">HTG</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Vers</label>
                <select name="devise_cible" class="border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    <option value="HTG">HTG</option><option value="USD">USD</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Taux</label>
                <input type="number" step="0.000001" min="0.000001" name="taux" required placeholder="132.50"
                       class="w-36 border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <button type="submit" class="btn-primary text-sm">Enregistrer</button>
            <p class="text-xs text-gray-400 basis-full">
                Les taux précédents sont conservés : un paiement déjà ouvert garde le sien.
            </p>
        </form>

        @if ($taux->isNotEmpty())
            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-slate-700 text-xs text-gray-500 dark:text-gray-400 space-y-1">
                @foreach ($taux as $t)
                    <div>
                        1 {{ $t->devise_source }} = {{ rtrim(rtrim(number_format((float) $t->taux, 6, ',', ' '), '0'), ',') }} {{ $t->devise_cible }}
                        &middot; {{ $t->applique_depuis->format('d/m/Y H:i') }}
                        @if ($t->auteur) &middot; {{ $t->auteur->name }} @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

@foreach ($passerelles as $p)
<div class="content-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center overflow-hidden shrink-0">
                @if ($p->logo_url)
                    <img src="{{ $p->logo_url }}" alt="" class="max-w-full max-h-full object-contain">
                @else
                    <span class="text-[10px] font-bold text-gray-500">{{ $p->initiales }}</span>
                @endif
            </span>
            <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->nom }}</span>
            <span class="text-xs text-gray-400">{{ $p->code }}</span>
        </div>
        <div class="flex items-center gap-2">
            @if ($p->estApi())
                <span class="text-[10px] font-bold rounded px-1.5 py-0.5 {{ $p->api_prete ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                    {{ $p->api_prete ? 'CLÉS OK' : 'CLÉS MANQUANTES' }}
                </span>
            @endif
            <span class="text-[10px] font-bold rounded px-1.5 py-0.5 {{ $p->environnement === 'production' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-300' }}">
                {{ strtoupper($p->environnement) }}
            </span>
        </div>
    </div>

    <form method="POST" action="{{ route('erp.transactions.configurer', $p) }}" class="p-5 space-y-4">
        @csrf
        <div class="grid gap-3" style="grid-template-columns: repeat(3,1fr);">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Mode</label>
                <select name="mode" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    <option value="manuel" @selected($p->mode === 'manuel')>Manuel — preuve puis approbation</option>
                    <option value="api" @selected($p->mode === 'api')>Automatique — par API</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Pilote</label>
                <select name="pilote" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    <option value="">— Aucun —</option>
                    @foreach ($pilotes as $cle => $pilote)
                        <option value="{{ $cle }}" @selected($p->pilote === $cle)>{{ ucfirst($cle) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Environnement</label>
                <select name="environnement" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    <option value="test" @selected($p->environnement === 'test')>Test</option>
                    <option value="production" @selected($p->environnement === 'production')>Production — encaisse réellement</option>
                </select>
            </div>
        </div>

        @php $pilote = $pilotes[$p->pilote] ?? null; @endphp
        @if ($pilote)
            <div class="rounded-xl border border-gray-200 dark:border-slate-600 p-4 space-y-3">
                <p class="text-xs text-gray-400">
                    Clés chiffrées en base. Elles ne sont jamais réaffichées : laisser un champ vide
                    conserve la clé enregistrée.
                </p>
                @foreach ($pilote->champsRequis() as $champ => $libelle)
                    <div>
                        <label class="block text-xs text-gray-400 mb-1">
                            {{ $libelle }}
                            @if ($apercu = $p->apercuIdentifiant($champ))
                                <span class="text-gray-300 font-mono">— enregistrée : {{ $apercu }}</span>
                            @endif
                        </label>
                        <input type="password" name="identifiants[{{ $champ }}]" autocomplete="new-password"
                               placeholder="{{ $p->apercuIdentifiant($champ) ? 'Laisser vide pour conserver' : 'Coller la clé' }}"
                               class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
                    </div>
                @endforeach
            </div>
        @endif

        <div class="grid gap-3 items-end" style="grid-template-columns: repeat(4,1fr);">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Frais %</label>
                <input type="number" step="0.001" min="0" max="100" name="frais_pourcent" value="{{ (float) $p->frais_pourcent }}"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <div>
                <label class="block text-xs text-gray-400 mb-1">Frais fixe</label>
                <input type="number" step="0.01" min="0" name="frais_fixe" value="{{ (float) $p->frais_fixe }}"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm dark:bg-slate-700 dark:border-slate-600 dark:text-gray-200">
            </div>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 pb-2">
                <input type="hidden" name="disponible_public" value="0">
                <input type="checkbox" name="disponible_public" value="1" @checked($p->disponible_public)> Visible en ligne
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 pb-2">
                <input type="hidden" name="disponible_caisse" value="0">
                <input type="checkbox" name="disponible_caisse" value="1" @checked($p->disponible_caisse)> Disponible en caisse
            </label>
        </div>

        <button type="submit" class="btn-primary text-sm">Enregistrer {{ $p->nom }}</button>
    </form>
</div>
@endforeach

@endsection
