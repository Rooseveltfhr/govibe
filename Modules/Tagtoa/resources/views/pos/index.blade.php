@extends('tagtoa::layouts.dashboard')
@section('title', __('Caisse (POS)'))
@section('page', __('Caisse (POS)'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos caisses') }}</h2>
    <form method="POST" action="{{ route('tagtoa.pos.store') }}" style="display:flex;gap:8px">
        @csrf
        <input class="inp" name="name" placeholder="{{ __('Nom de la caisse') }}" required style="min-width:200px">
        <button class="btn btn-p"><i class="fa-solid fa-plus"></i></button>
    </form>
</div>
@if($terminals->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-cash-register"></i>{{ __('Aucune caisse. Créez-en une pour commencer à vendre.') }}</div></div>
@else
    <div class="grid g3">
        @foreach($terminals as $t)
            <div class="card">
                <b style="font-family:var(--fh);font-size:16px">{{ $t->name }}</b>
                <div style="color:var(--muted);font-size:13px;margin-top:6px">{{ $t->products_count }} {{ __('produits') }} · {{ $t->currency }}</div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.pos.register',$t->id) }}" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir') }}</a>
                    <a href="{{ route('tagtoa.pos.products',$t->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-boxes-stacked"></i></a>
                    <a href="{{ route('tagtoa.pos.report',$t->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-chart-simple"></i></a>
                </div>
            </div>
        @endforeach
    </div>
@endif
@endsection
