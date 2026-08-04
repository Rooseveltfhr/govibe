@extends('erp.layouts.app')
@section('title','Anniversaires')
@section('page-title','Anniversaires')
@section('page-subtitle','Vœux automatiques — Clients & Inscrits')

@section('content')

{{-- Stats --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Aujourd\'hui',        $stats['aujourd_hui'],              'bi-cake2-fill',       '#DC2626','#fee2e2'],
        ['Cette semaine',       $stats['semaine'],                  'bi-calendar-week-fill','#7c3aed','#ede9fe'],
        ['Clients avec date',   $stats['total_clients_avec_date'],  'bi-person-heart',     '#059669','#d1fae5'],
        ['Inscrits avec date',  $stats['total_inscrits_avec_date'], 'bi-mortarboard-fill', '#1e3a5f','#dbeafe'],
    ] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-xs mb-1">{{ $l }}</p>
                <p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p>
            </div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}">
                <i class="bi {{ $i }}" style="color:{{ $c }}"></i>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    {{-- ─ Anniversaires aujourd'hui ─────────────────────────────── --}}
    <div class="content-card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <div>
                <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                    <span class="text-lg">🎂</span> Aujourd'hui — {{ now()->format('d/m') }}
                </h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    {{ $clientsAujourdhui->count() + $inscritsAujourdhui->count() }} personne(s)
                </p>
            </div>
        </div>

        @php $allToday = $clientsAujourdhui->map(fn($c) => ['type'=>'client','id'=>$c->id,'nom'=>$c->name,'phone'=>$c->phone,'email'=>$c->email,'age'=>now()->year - ($c->date_naissance?->year ?? now()->year)])
            ->merge($inscritsAujourdhui->map(fn($i) => ['type'=>'inscrit','id'=>$i->id,'nom'=>$i->nom_complet,'phone'=>$i->telephone,'email'=>$i->email,'age'=>now()->year - ($i->date_naissance?->year ?? now()->year)])) @endphp

        @forelse($allToday as $person)
        <div class="flex items-center gap-4 px-5 py-4 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
            <div class="w-10 h-10 rounded-full flex items-center justify-center text-base flex-shrink-0" style="background:#fee2e2">
                🎂
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-800 dark:text-white text-sm">{{ $person['nom'] }}</p>
                <div class="flex items-center gap-2 mt-0.5">
                    <span class="badge text-xs {{ $person['type'] === 'client' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                        {{ $person['type'] === 'client' ? 'Client' : 'Inscrit' }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $person['age'] }} ans · {{ $person['phone'] ?? $person['email'] ?? '—' }}</span>
                </div>
            </div>
            <form method="POST" action="{{ route('erp.crm.anniversaires.send') }}">
                @csrf
                <input type="hidden" name="type" value="{{ $person['type'] }}">
                <input type="hidden" name="id" value="{{ $person['id'] }}">
                <button type="submit"
                        class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-700 border border-red-200 rounded-lg hover:bg-red-100 transition-colors"
                        title="Envoyer vœux maintenant">
                    <i class="bi bi-send-fill"></i> Envoyer
                </button>
            </form>
        </div>
        @empty
        <div class="px-5 py-12 text-center text-gray-400">
            <div class="text-4xl mb-3">🎈</div>
            <p class="text-sm font-medium">Aucun anniversaire aujourd'hui</p>
            <p class="text-xs mt-1 text-gray-300">Les vœux seront envoyés automatiquement à 9h00</p>
        </div>
        @endforelse
    </div>

    {{-- ─ Prochains 7 jours ────────────────────────────────────── --}}
    <div class="content-card">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                <span class="text-lg">📅</span> 7 prochains jours
            </h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ $prochains->count() }} anniversaire(s) à venir</p>
        </div>

        @forelse($prochains->sortBy('jours') as $p)
        <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-purple-50 dark:bg-purple-900/20 flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-bold text-purple-600">{{ $p['date'] }}</span>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-medium text-gray-800 dark:text-white text-sm truncate">{{ $p['nom'] }}</p>
                <p class="text-xs text-gray-400">{{ $p['type'] }} · dans {{ $p['jours'] }} jour(s)</p>
            </div>
            <span class="text-xs text-gray-300">{{ $p['phone'] ?? '—' }}</span>
        </div>
        @empty
        <div class="px-5 py-12 text-center text-gray-400">
            <div class="text-3xl mb-2 opacity-30">📅</div>
            <p class="text-sm">Aucun anniversaire dans les 7 prochains jours</p>
        </div>
        @endforelse
    </div>
</div>

{{-- ─ Info automatisation ──────────────────────────────────────── --}}
<div class="mt-6 content-card">
    <div class="px-6 py-5 flex flex-col md:flex-row items-start md:items-center gap-4">
        <div class="w-12 h-12 rounded-xl bg-green-50 flex items-center justify-center flex-shrink-0">
            <i class="bi bi-robot text-green-600 text-xl"></i>
        </div>
        <div class="flex-1">
            <h4 class="font-semibold text-gray-800 dark:text-white">Automatisation active</h4>
            <p class="text-sm text-gray-500 mt-0.5">
                Chaque jour à <strong>9h00</strong>, le système détecte automatiquement tous les anniversaires et envoie
                un message WhatsApp + email personnalisé. Aucune action manuelle requise.
            </p>
            <div class="flex flex-wrap gap-3 mt-3">
                <span class="badge bg-green-100 text-green-700 text-xs">
                    <i class="bi bi-check-circle-fill mr-1"></i> WhatsApp activé
                </span>
                <span class="badge bg-blue-100 text-blue-700 text-xs">
                    <i class="bi bi-envelope-fill mr-1"></i> Email activé
                </span>
                <span class="badge bg-gray-100 text-gray-600 text-xs">
                    <i class="bi bi-clock-fill mr-1"></i> Tous les jours à 09:00
                </span>
            </div>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="text-xs text-gray-400 mb-2">Tester la commande</p>
            <code class="text-xs bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 px-3 py-1.5 rounded-lg font-mono">
                php artisan birthday:wishes --dry-run
            </code>
        </div>
    </div>
</div>

{{-- ─ Conseil : ajouter les dates ──────────────────────────────── --}}
@if($stats['total_clients_avec_date'] === 0 && $stats['total_inscrits_avec_date'] === 0)
<div class="mt-4 bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
    <i class="bi bi-exclamation-triangle-fill text-amber-500 text-lg flex-shrink-0 mt-0.5"></i>
    <div>
        <p class="font-medium text-amber-800 text-sm">Aucune date de naissance enregistrée</p>
        <p class="text-xs text-amber-700 mt-1">
            Pour activer les vœux automatiques, ajoutez la date de naissance dans la fiche de chaque client CRM.
            Les inscrits aux formations sont déjà couverts si leur date a été saisie lors de l'inscription.
        </p>
        <a href="{{ route('erp.crm.clients.index') }}"
           class="inline-flex items-center gap-1 text-xs text-amber-700 font-semibold hover:underline mt-2">
            <i class="bi bi-person-plus"></i> Aller aux clients →
        </a>
    </div>
</div>
@endif

@endsection
