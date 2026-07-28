@extends('erp.layouts.app')
@section('title','Ressources Humaines')
@section('page-title','Ressources Humaines')
@section('page-subtitle','Gestion des employés GOVIBE')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([['Total employés',$stats['total'],'bi-people-fill','#1e3a5f','#dbeafe'],['Actifs',$stats['active'],'bi-person-check-fill','#059669','#d1fae5'],['En congé',$stats['onLeave'],'bi-calendar2-minus','#d97706','#fef3c7'],['Nouveaux',$stats['new'],'bi-person-plus-fill','#7c3aed','#ede9fe']] as [$l,$v,$i,$c,$bg])
    <div class="stat-card">
        <div class="flex items-center justify-between">
            <div><p class="text-gray-400 text-xs mb-1">{{ $l }}</p><p class="text-2xl font-extrabold text-gray-800 dark:text-white">{{ $v }}</p></div>
            <div class="w-10 h-10 rounded-xl flex items-center justify-center" style="background:{{ $bg }}"><i class="bi {{ $i }}" style="color:{{ $c }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="content-card">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-slate-700">
        <h3 class="font-semibold text-gray-800 dark:text-white">Employés</h3>
        <button class="btn-gold text-sm opacity-60 cursor-not-allowed" disabled><i class="bi bi-plus-lg mr-1"></i> Ajouter (bientôt)</button>
    </div>
    <div class="px-5 py-16 text-center text-gray-400">
        <i class="bi bi-people text-5xl block mb-4 opacity-20"></i>
        <h3 class="font-semibold text-gray-600 dark:text-gray-300 mb-2">Module RH en développement</h3>
        <p class="text-sm max-w-md mx-auto">La gestion des employés, des congés, des contrats et de la paie sera disponible prochainement.</p>
    </div>
</div>
@endsection
