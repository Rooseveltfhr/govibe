@extends('tagtoa::layouts.dashboard')
@section('title', __('Rapport de fidélisation'))
@section('page', __('Rapport de fidélisation').' — '.$program->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.loyalty.dashboard.cards',$program->id) }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
        <input class="inp" type="date" name="from" value="{{ $from->toDateString() }}">
        <input class="inp" type="date" name="to" value="{{ $to->toDateString() }}">
        <button class="btn btn-o btn-sm">{{ __('Voir') }}</button>
    </form>
</div>

{{-- Ce qui a bougé SUR LA PÉRIODE : sert à comparer un mois à l'autre. --}}
<div class="grid g4" style="margin-top:14px">
    <div class="stat"><div class="ic"><i class="fa-solid fa-star"></i></div><div class="v">{{ number_format($period['points_issued']) }}</div><div class="k">{{ __('Points émis') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-gift"></i></div><div class="v">{{ number_format($period['points_redeemed']) }}</div><div class="k">{{ __('Points utilisés') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($period['amount_topped_up'],2) }}</div><div class="k">{{ __('Rechargé') }} ({{ $program->currency }})</div></div>
    <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-arrow-down"></i></div><div class="v">{{ number_format($period['amount_redeemed'],2) }}</div><div class="k">{{ __('Débité') }} ({{ $program->currency }})</div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Mouvements de la période') }}</h2></div>
    <div style="display:flex;gap:24px;flex-wrap:wrap;font-size:13.5px">
        <span>{{ __('Recharges') }} : <b>{{ $period['top_up_count'] }}</b></span>
        <span>{{ __('Débits') }} : <b>{{ $period['redeem_count'] }}</b></span>
        <span>{{ __('Récompenses échangées') }} : <b>{{ $period['reward_redemptions'] }}</b></span>
        <span>{{ __('Nouvelles cartes') }} : <b>{{ $period['new_cards'] }}</b></span>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin:10px 0 0">
        {{ __('Du :from au :to.', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')]) }}
    </p>
</div>

{{-- Qui compose la base de cartes AUJOURD'HUI — indépendant de la période
     choisie ci-dessus, voir LoyaltyReportService::segments(). --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Segmentation client') }}</h2></div>
    <div class="tw" style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:14.5px">
            <thead><tr>
                <th style="text-align:left;padding:9px 12px;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.08em">{{ __('Segment') }}</th>
                <th style="text-align:right;padding:9px 12px;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.08em">{{ __('Cartes') }}</th>
            </tr></thead>
            <tbody>
            @foreach($segments as $segment => $count)
                <tr style="border-top:1px solid var(--bd)">
                    <td style="padding:11px 12px">
                        <span class="pill {{ match($segment) { 'vip' => 'g', 'inactif' => 'r', 'nouveau' => 'a', default => 'n' } }}">
                            {{ \Modules\Tagtoa\App\Support\Loyalty\CustomerSegment::label($segment) }}
                        </span>
                    </td>
                    <td style="padding:11px 12px;text-align:right;font-variant-numeric:tabular-nums;font-weight:600">{{ number_format($count) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin:10px 0 0">
        {{ __('Basée sur l\'état actuel des cartes, pas sur la période choisie ci-dessus.') }}
    </p>
</div>
@endsection
