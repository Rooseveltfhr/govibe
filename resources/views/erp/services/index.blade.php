@extends('erp.layouts.app')
@section('title','Catalogue Services')
@section('page-title','Catalogue de services')
@section('page-subtitle','Services GOVIBE disponibles')

@section('content')
<div class="flex items-center justify-between mb-6">
    <div></div>
    <a href="{{ route('erp.admin.services.index') }}" class="btn-gold text-sm">
        <i class="bi bi-gear mr-1"></i> Gérer les services
    </a>
</div>

@foreach($categories as $cat)
@php $catServices = $services->filter(fn($s) => $s->category_id == $cat->id); @endphp
@if($catServices->count())
<div class="mb-8">
    <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3 flex items-center gap-2">
        @if($cat->icon)<i class="bi {{ $cat->icon }}" style="color:{{ $cat->color ?? '#1e3a5f' }}"></i>@endif
        {{ $cat->name }}
        <span class="text-xs text-gray-400">({{ $catServices->count() }})</span>
    </h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($catServices as $s)
        <div class="content-card p-5 hover:shadow-md transition-shadow">
            <div class="flex items-start justify-between mb-3">
                <h4 class="font-semibold text-gray-800 dark:text-white">{{ $s->name }}</h4>
                <span class="badge text-xs {{ $s->is_active ? 'status-active' : 'status-inactive' }}">
                    {{ $s->is_active ? 'Actif' : 'Inactif' }}
                </span>
            </div>
            @if($s->description)
            <p class="text-xs text-gray-400 mb-3 leading-relaxed">{{ $s->description }}</p>
            @endif
            <div class="flex items-center justify-between">
                <span class="font-bold text-gray-800 dark:text-white">HTG {{ number_format($s->price,0,'.',',') }}</span>
                <span class="text-xs text-gray-400">/ {{ ['hour'=>'heure','day'=>'jour','month'=>'mois','project'=>'projet','session'=>'session'][$s->unit] ?? $s->unit }}</span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif
@endforeach

@php $uncategorized = $services->whereNull('category_id'); @endphp
@if($uncategorized->count())
<div class="mb-8">
    <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-3">Sans catégorie</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($uncategorized as $s)
        <div class="content-card p-5">
            <h4 class="font-semibold text-gray-800 dark:text-white mb-2">{{ $s->name }}</h4>
            @if($s->description)<p class="text-xs text-gray-400 mb-3">{{ $s->description }}</p>@endif
            <span class="font-bold text-gray-800 dark:text-white">HTG {{ number_format($s->price,0,'.',',') }}</span>
            <span class="text-xs text-gray-400"> / {{ ['hour'=>'heure','day'=>'jour','month'=>'mois','project'=>'projet','session'=>'session'][$s->unit] ?? $s->unit }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($services->isEmpty())
<div class="content-card px-5 py-16 text-center text-gray-400">
    <i class="bi bi-stars text-5xl block mb-4 opacity-20"></i>
    <h3 class="font-semibold text-gray-600 dark:text-gray-300 mb-2">Aucun service actif</h3>
    <p class="text-sm mb-4">Commencez par ajouter des services depuis le panneau admin.</p>
    <a href="{{ route('erp.admin.services.index') }}" class="btn-gold text-sm">Gérer les services</a>
</div>
@endif
@endsection
