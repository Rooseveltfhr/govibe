@extends('tagtoa::layouts.dashboard')
@section('title', __('Rapport Z'))
@section('page', __('Rapport Z').' — '.$terminal->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <form method="GET" style="display:flex;gap:8px"><input class="inp" type="date" name="date" value="{{ $z['date'] }}"><button class="btn btn-o btn-sm">{{ __('Voir') }}</button></form>
</div>

<div class="grid g3">
    <div class="stat"><div class="ic"><i class="fa-solid fa-receipt"></i></div><div class="v">{{ $z['count'] }}</div><div class="k">{{ __('Ventes') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($z['total'],2) }}</div><div class="k">{{ __('Total') }} ({{ $terminal->currency }})</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-credit-card"></i></div><div class="v">{{ count($z['by_method']) }}</div><div class="k">{{ __('Méthodes') }}</div></div>
</div>

@if($z['by_method'])
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Ventilation par paiement') }}</h2></div>
    @foreach($z['by_method'] as $m=>$amt)
        <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--bd);padding:8px 0"><span style="text-transform:capitalize">{{ $m }}</span><b>{{ number_format($amt,2) }}</b></div>
    @endforeach
</div>
@endif

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Historique du jour') }}</h2></div>
    @if($sales->isEmpty())
        <div class="empty"><i class="fa-regular fa-receipt"></i>{{ __('Aucune vente.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Réf') }}</th><th>{{ __('Total') }}</th><th>{{ __('Paiement') }}</th><th>{{ __('Heure') }}</th></tr></thead>
            <tbody>@foreach($sales as $s)<tr><td><b>{{ $s->reference }}</b></td><td>{{ number_format($s->total,2) }}</td><td style="text-transform:capitalize">{{ collect($s->payments)->pluck('method')->implode(', ') }}</td><td style="color:var(--muted)">{{ optional($s->sold_at)->format('H:i') }}</td></tr>@endforeach</tbody>
        </table>
    @endif
</div>
@endsection
