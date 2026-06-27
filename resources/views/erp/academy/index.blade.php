@extends('erp.layouts.app')
@section('title','Academy ERP')
@section('page-title','GOVIBE Academy')
@section('page-subtitle','Formations et inscriptions')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([['Formations',$stats['formations'],'bi-mortarboard-fill','#1e3a5f','#dbeafe'],['Inscriptions',$stats['inscriptions'],'bi-person-check-fill','#059669','#d1fae5'],['Présents',$stats['presents'],'bi-person-bounding-box','#0891b2','#e0f2fe'],['Actives',$stats['active'],'bi-play-circle-fill','#7c3aed','#ede9fe']] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    {{-- Formations --}}
    <div class="content-card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white">Formations</h3>
            <a href="{{ route('admin.formations.create') }}" class="btn-gold text-xs">
                <i class="bi bi-plus-lg mr-1"></i> Nouvelle
            </a>
        </div>
        @forelse($formations as $f)
        <div class="flex items-center gap-4 px-5 py-3.5 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ $f->nom }}</p>
                <p class="text-xs text-gray-400">{{ $f->lieu ?? '' }} @if($f->date_debut)· {{ \Carbon\Carbon::parse($f->date_debut)->format('d/m/Y') }}@endif</p>
            </div>
            <div class="text-right flex-shrink-0">
                <p class="text-sm font-bold text-gray-800 dark:text-white">{{ $f->inscriptions_count }}</p>
                <p class="text-xs text-gray-400">inscrits</p>
            </div>
            <span class="badge text-xs {{ $f->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                {{ $f->active ? 'Active' : 'Inactive' }}
            </span>
        </div>
        @empty
        <div class="px-5 py-10 text-center text-gray-400">
            <i class="bi bi-mortarboard text-3xl block mb-2 opacity-30"></i>
            <p class="text-sm">Aucune formation. <a href="{{ route('admin.formations.create') }}" class="text-blue-600 hover:underline">Créer la première</a></p>
        </div>
        @endforelse
    </div>

    {{-- Recent inscriptions --}}
    <div class="content-card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
            <h3 class="font-semibold text-gray-800 dark:text-white">Inscriptions récentes</h3>
            <a href="{{ route('admin.inscriptions.index') }}" class="text-xs text-blue-600 hover:underline">Voir tout</a>
        </div>
        @forelse($recentInscriptions as $ins)
        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-50 dark:border-slate-700/50 last:border-0">
            <div class="avatar avatar-navy w-8 h-8 text-xs flex-shrink-0">
                {{ strtoupper(substr($ins->nom_complet,0,1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 dark:text-white truncate">{{ $ins->nom_complet }}</p>
                <p class="text-xs text-gray-400">{{ $ins->formation->nom ?? '—' }}</p>
            </div>
            <span class="text-xs text-gray-400">{{ $ins->created_at->diffForHumans() }}</span>
        </div>
        @empty
        <div class="px-5 py-10 text-center text-gray-400">
            <i class="bi bi-person-plus text-3xl block mb-2 opacity-30"></i>
            <p class="text-sm">Aucune inscription récente.</p>
        </div>
        @endforelse
    </div>
</div>
@endsection
