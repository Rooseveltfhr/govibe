@extends('tagtoa::layouts.dashboard')
@section('title', __('Boutique'))
@section('page', __('Boutique en ligne'))

@section('content')
<div class="h-row">
    <h2>{{ __('Mes boutiques') }}</h2>
    <span style="flex:1"></span>
    <a class="btn btn-p" href="{{ route('tagtoa.store.dashboard.create') }}"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle boutique') }}</a>
</div>

@if($stores->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-bag-shopping"></i>{{ __('Aucune boutique. Créez votre première boutique en ligne.') }}</div></div>
@else
    <div class="grid g2">
        @foreach($stores as $s)
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:start;gap:10px">
                    <b style="font-family:var(--fh);font-size:16px">{{ $s->name }}</b>
                    <span class="pill {{ $s->is_published ? 'g' : 'n' }}">{{ $s->is_published ? __('Publiée') : __('Brouillon') }}</span>
                </div>
                <div style="color:var(--muted);font-size:13px;margin-top:6px">
                    {{ $s->products_count }} {{ __('produits') }} · {{ $s->orders_count }} {{ __('commandes') }} · {{ $s->views }} {{ __('vues') }}
                </div>
                <div style="font-size:12.5px;margin-top:6px"><i class="fa-solid fa-link" style="color:var(--muted)"></i> <code style="font-size:12px">/store/{{ $s->alias }}</code></div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.store.dashboard.edit',$s->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ route('tagtoa.store.dashboard.orders',$s->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-receipt"></i> {{ __('Commandes') }}</a>
                    <a href="{{ url('/store/'.$s->alias) }}" target="_blank" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-arrow-up-right-from-square"></i> {{ __('Voir') }}</a>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $stores->links() }}</div>
@endif
@endsection
