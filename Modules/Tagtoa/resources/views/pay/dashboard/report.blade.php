@extends('tagtoa::layouts.dashboard')
@section('title', __('Rapport'))
@section('page', __('Rapport').' — '.($page->title ?: $page->alias))

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pay.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
        <input class="inp" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="inp" type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn btn-o btn-sm">{{ __('Voir') }}</button>
    </form>
</div>

{{-- Ce qui a bougé SUR LA PÉRIODE. --}}
<div class="grid g4" style="margin-top:14px">
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($period['revenue'],2) }}</div><div class="k">{{ __('Revenu') }} ({{ $page->default_currency }})</div></div>
    <div class="stat"><div class="ic"><i class="fa-solid fa-circle-check"></i></div><div class="v">{{ $period['approved_count'] }}</div><div class="k">{{ __('Preuves approuvées') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-hourglass-half"></i></div><div class="v">{{ $period['pending_count'] }}</div><div class="k">{{ __('En attente') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-circle-xmark"></i></div><div class="v">{{ $period['rejected_count'] }}</div><div class="k">{{ __('Rejetées') }}</div></div>
</div>

<p style="color:var(--muted);font-size:12.5px;margin-top:10px">
    {{ __('Du :from au :to.', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')]) }}
</p>

{{-- Instantané depuis toujours — indépendant de la période choisie ci-dessus,
     voir PayReportService::conversion(). --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Conversion depuis toujours') }}</h2></div>
    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13.5px">
        <span>{{ __('Vues') }} : <b>{{ number_format($conversion['views']) }}</b></span>
        <span>{{ __('Preuves approuvées') }} : <b>{{ number_format($conversion['approved']) }}</b></span>
        @if($conversion['rate'] !== null)
            <span>{{ __('Taux de conversion') }} : <b>{{ $conversion['rate'] }}%</b></span>
        @endif
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin:10px 0 0">
        {{ __('Basé sur toutes les vues depuis la création du lien, pas sur la période choisie ci-dessus : une vue n\'est pas datée.') }}
    </p>
</div>

<div style="margin-top:16px;text-align:center">
    <a class="btn btn-o" href="{{ route('tagtoa.pay.dashboard.proofs',$page->id) }}"><i class="fa-solid fa-receipt"></i> {{ __('Voir le détail des preuves') }}</a>
</div>
@endsection
