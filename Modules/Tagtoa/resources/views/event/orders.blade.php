@extends('tagtoa::layouts.dashboard')
@section('title', __('Commandes'))
@section('page', $event->title)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.event.dashboard.orders.export',$event->id) }}" class="btn btn-o btn-sm"><i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}</a>
</div>

<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($analytics['revenue'],2) }}</div><div class="k">{{ __('Revenu') }} ({{ $event->currency }})</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-receipt"></i></div><div class="v">{{ $analytics['orders'] }}</div><div class="k">{{ __('Commandes') }}</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-ticket"></i></div><div class="v">{{ $analytics['tickets'] }}</div><div class="k">{{ __('Billets') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-door-open"></i></div><div class="v">{{ $analytics['checked_in'] }}</div><div class="k">{{ __('Entrés') }}</div></div>
</div>

<div class="card" style="margin-top:16px">
    @if($orders->isEmpty())
        <div class="empty"><i class="fa-regular fa-receipt"></i>{{ __('Aucune commande.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Réf') }}</th><th>{{ __('Acheteur') }}</th><th>{{ __('Total') }}</th><th>{{ __('Billets') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($orders as $o)
                <tr><td><b>{{ $o->reference }}</b></td><td>{{ $o->buyer_name }}<div style="color:var(--muted);font-size:12px">{{ $o->buyer_phone }}</div></td>
                    <td>{{ number_format($o->total,2) }}</td><td>{{ $o->tickets_count }}</td>
                    <td><span class="pill {{ $o->status===1?'g':($o->status===0?'a':'n') }}">{{ [0=>__('En attente'),1=>__('Payé'),2=>__('Annulé')][$o->status] ?? '' }}</span></td>
                    <td style="color:var(--muted)">{{ $o->created_at->format('d/m/y H:i') }}
                        @if($o->status === 0)
                        <form method="POST" action="{{ route('tagtoa.event.dashboard.orders.paid', [$event->id, $o->id]) }}" style="display:inline">@csrf
                            <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Encaisser') }}</button>
                        </form>
                        @endif
                    </td></tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $orders->links() }}</div>
    @endif
</div>
@endsection
