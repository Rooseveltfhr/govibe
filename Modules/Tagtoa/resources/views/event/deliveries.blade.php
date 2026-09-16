@extends('tagtoa::layouts.dashboard')
@section('title', __('Statistiques de livraison'))
@section('page', $event->title)

@php
    $contextLabel = ['order_confirmation' => __('Confirmation d\'achat'), 'checkin_confirmation' => __('Confirmation d\'entrée')];
    $sent = $rows->where('status', 'sent')->sum('total');
    $notSent = $rows->where('status', 'not_sent')->sum('total');
    $total = $sent + $notSent;
    $rate = $total > 0 ? round($sent / $total * 100) : null;
@endphp

@section('content')
<a href="{{ route('tagtoa.event.dashboard.orders',$event->id) }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>

<div class="grid g3" style="margin-top:14px">
    <div class="stat"><div class="ic"><i class="fa-solid fa-paper-plane"></i></div><div class="v">{{ $total }}</div><div class="k">{{ __('Tentatives d\'envoi') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-check"></i></div><div class="v">{{ $sent }}</div><div class="k">{{ __('Livrées') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="v">{{ $notSent }}</div><div class="k">{{ __('Non livrées') }}</div></div>
</div>

@if($rate !== null)
<p style="color:var(--muted);font-size:12.5px;margin-top:10px">
    {{ __('Taux de livraison') }} : <b>{{ $rate }}%</b>
    {{ __('— une confirmation « non livrée » ne bloque jamais l\'achat ni l\'entrée : le billet et le check-in restent valides, seule la notification a échoué.') }}
</p>
@endif

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Par contexte et canal') }}</h2></div>
    @if($rows->isEmpty())
        <div class="empty"><i class="fa-regular fa-paper-plane"></i>{{ __('Aucun envoi pour le moment.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Contexte') }}</th><th>{{ __('Canal') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Total') }}</th></tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr>
                    <td>{{ $contextLabel[$r->context] ?? $r->context }}</td>
                    <td style="text-transform:capitalize">{{ $r->channel }}</td>
                    <td><span class="pill {{ $r->status === 'sent' ? 'g' : 'r' }}">{{ $r->status === 'sent' ? __('Livrée') : __('Non livrée') }}</span></td>
                    <td><b>{{ $r->total }}</b></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Envois récents') }}</h2></div>
    @if($recent->isEmpty())
        <div class="empty"><i class="fa-regular fa-paper-plane"></i>{{ __('Aucun envoi pour le moment.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Destinataire') }}</th><th>{{ __('Canal') }}</th><th>{{ __('Contexte') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($recent as $d)
                <tr>
                    <td>{{ $d->recipient }}</td>
                    <td style="text-transform:capitalize">{{ $d->channel }}</td>
                    <td>{{ $contextLabel[$d->context] ?? $d->context }}</td>
                    <td><span class="pill {{ $d->status === 'sent' ? 'g' : 'r' }}">{{ $d->status === 'sent' ? __('Livrée') : __('Non livrée') }}</span></td>
                    <td style="color:var(--muted)">{{ $d->created_at->format('d/m/y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $recent->links() }}</div>
    @endif
</div>
@endsection
