@extends('tagtoa::layouts.dashboard')
@section('title', __('Fidélité'))
@section('page', __('Fidélité'))

@section('content')
<div class="h-row">
    <h2>{{ __('Programmes de fidélité') }}</h2>
    <a href="{{ route('tagtoa.loyalty.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouveau programme') }}</a>
</div>
@if($programs->isEmpty())
    <div class="card"><div class="empty"><i class="fa-regular fa-id-card"></i>{{ __('Aucun programme. Créez votre première carte de fidélité NFC.') }}</div></div>
@else
    <div class="grid g2">
        @foreach($programs as $p)
            <div class="card">
                <div style="display:flex;justify-content:space-between"><b style="font-family:var(--fh);font-size:16px">{{ $p->name }}</b><span class="pill {{ $p->is_active ? 'g' : 'n' }}">{{ $p->is_active ? __('Active') : __('Inactive') }}</span></div>
                <div style="color:var(--muted);font-size:13px;margin-top:6px">{{ $p->points_per_dollar }} pts / {{ $p->currency }} · {{ $p->cards_count }} {{ __('cartes') }}</div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.loyalty.dashboard.cards',$p->id) }}" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-credit-card"></i> {{ __('Cartes') }}</a>
                    <a href="{{ route('tagtoa.loyalty.dashboard.edit',$p->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $programs->links() }}</div>
@endif
@endsection
