@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Clients'))
@section('page', __('Clients'))

@php
    $chLabels = ['menu'=>'Menu','event'=>'Événements','pay'=>'Paiements','pos'=>'Caisse','loyalty'=>'Fidélité'];
@endphp

@section('content')
<form method="GET" class="h-row" style="gap:8px">
    <input class="inp" name="q" value="{{ $q }}" placeholder="{{ __('Rechercher par nom ou téléphone') }}" style="max-width:320px;flex:0">
    <button class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-magnifying-glass"></i> {{ __('Rechercher') }}</button>
    <span style="flex:1"></span>
    <span style="color:var(--muted);font-size:13px">{{ $shown }} / {{ $total }} {{ __('clients') }}</span>
</form>

@if(empty($customers))
    <div class="card"><div class="empty"><i class="fa-solid fa-users"></i>{{ __('Aucun client pour l\'instant. Vos clients apparaîtront ici dès leur première commande ou paiement.') }}</div></div>
@else
    <div class="card" style="padding:0;overflow:hidden">
        <table>
            <thead><tr><th>{{ __('Client') }}</th><th>{{ __('Téléphone') }}</th><th>{{ __('Commandes') }}</th><th>{{ __('Total dépensé') }}</th><th>{{ __('Sources') }}</th><th>{{ __('Dernière activité') }}</th></tr></thead>
            <tbody>
            @foreach($customers as $c)
                <tr>
                    <td><b style="font-family:var(--fh)">{{ $c['name'] ?: __('Client') }}</b>@if($c['email'])<div style="font-size:12px;color:var(--muted)">{{ $c['email'] }}</div>@endif</td>
                    <td>@if($c['phone'])<a href="https://wa.me/{{ preg_replace('/\D+/','',$c['phone']) }}" target="_blank" rel="noopener" style="color:var(--blue)">{{ $c['phone'] }}</a>@else <span style="color:var(--muted)">—</span>@endif</td>
                    <td>{{ $c['orders'] }}</td>
                    <td>{{ Money::format($c['spent'],'HTG') }}</td>
                    <td>@foreach(array_keys($c['channels']) as $ch)<span class="pill n" style="margin:1px">{{ __($chLabels[$ch] ?? $ch) }}</span>@endforeach</td>
                    <td style="color:var(--muted);font-size:13px">{{ $c['last'] ? \Illuminate\Support\Carbon::createFromTimestamp($c['last'])->format('d/m/y') : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($total > $shown)
        <p style="color:var(--muted);font-size:12.5px;margin-top:12px"><i class="fa-solid fa-circle-info"></i> {{ __('Affichage limité aux 200 clients les plus récents. Utilisez la recherche pour affiner.') }}</p>
    @endif
@endif
@endsection
