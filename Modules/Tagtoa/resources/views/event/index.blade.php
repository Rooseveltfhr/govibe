@extends('tagtoa::layouts.dashboard')
@section('title', __('Événements'))
@section('page', __('Événements'))

@section('content')
<div class="h-row">
    <h2>{{ __('Vos événements') }}</h2>
    <a href="{{ route('tagtoa.event.dashboard.create') }}" class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Nouvel événement') }}</a>
</div>
@if($events->isEmpty())
    <div class="card"><div class="empty"><i class="fa-regular fa-calendar"></i>{{ __('Aucun événement. Créez votre billetterie NFC/QR.') }}</div></div>
@else
    <div class="grid g2">
        @foreach($events as $e)
            <div class="card">
                <div style="display:flex;justify-content:space-between"><b style="font-family:var(--fh);font-size:16px">{{ $e->title }}</b><span class="pill {{ $e->is_published ? 'g' : 'n' }}">{{ $e->is_published ? __('Publié') : __('Brouillon') }}</span></div>
                <div style="color:var(--muted);font-size:13px;margin-top:6px">{{ $e->starts_at ? $e->starts_at->format('d/m/Y H:i') : '—' }} · {{ $e->tickets_count }} {{ __('billets') }} · {{ $e->orders_count }} {{ __('commandes') }}</div>
                <div class="row" style="margin-top:14px;gap:8px">
                    <a href="{{ route('tagtoa.event.dashboard.edit',$e->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.orders',$e->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-chart-line"></i> {{ __('Commandes') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.scanner',$e->id) }}" class="btn btn-d btn-sm" style="flex:0"><i class="fa-solid fa-qrcode"></i> {{ __('Scanner') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.wallet',$e->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-wallet"></i> {{ __('Wallet') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.checkin.report',$e->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-chart-simple"></i> {{ __('Rapport') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.badges',$e->id) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-id-badge"></i> {{ __('Badges') }}</a>
                    <a href="{{ route('tagtoa.event.dashboard.staff',$e->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-users"></i> {{ __('Staff') }}</a>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px">{{ $events->links() }}</div>
@endif
@endsection
