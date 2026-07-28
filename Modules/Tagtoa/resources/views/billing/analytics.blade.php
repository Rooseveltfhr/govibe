@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Analytics'))
@section('page', __('Analytics'))

@php
    $maxDaily = max(1, max(array_map('floatval', $daily ?: [0])));
    $modLabels = ['menu'=>'Menu','pos'=>'Caisse','event'=>'Événements','pay'=>'Paiements'];
@endphp

@section('content')
{{-- KPIs --}}
<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-bag-shopping"></i></div><div class="v">{{ $counts['orders'] }}</div><div class="k">{{ __('Commandes') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-eye"></i></div><div class="v">{{ $counts['views'] }}</div><div class="k">{{ __('Vues') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-bell"></i></div><div class="v">{{ $counts['pending'] }}</div><div class="k">{{ __('En attente') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-percent"></i></div><div class="v" style="font-size:18px">{{ Money::format($counts['commissions'],'HTG') }}</div><div class="k">{{ __('Commissions') }}</div></div>
</div>

{{-- Revenu par devise --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Revenu encaissé') }}</h2></div>
    @if(empty($revenue))
        <div class="empty" style="padding:24px"><i class="fa-solid fa-sack-dollar"></i>{{ __('Aucun revenu pour l\'instant.') }}</div>
    @else
        <div class="grid g3">
            @foreach($revenue as $cur=>$amt)
                <div class="stat"><div class="ic"><i class="fa-solid fa-sack-dollar"></i></div><div class="v" style="font-size:22px">{{ Money::format($amt,$cur) }}</div><div class="k">{{ $cur }}</div></div>
            @endforeach
        </div>
    @endif
</div>

{{-- Graphe 14 jours --}}
<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Revenu — 14 derniers jours') }}</h2></div>
    <div style="display:flex;align-items:flex-end;gap:5px;height:160px;padding-top:10px">
        @foreach($daily as $d=>$val)
            @php $h = (int) round(($val / $maxDaily) * 140); @endphp
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:6px;justify-content:flex-end" title="{{ $d }} : {{ Money::format($val,'HTG') }}">
                <div style="width:100%;max-width:26px;height:{{ max(2,$h) }}px;background:var(--blue);border-radius:6px 6px 0 0;opacity:{{ $val>0 ? 1 : .25 }}"></div>
                <span style="font-size:9.5px;color:var(--muted)">{{ \Illuminate\Support\Carbon::parse($d)->format('d/m') }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="grid g2" style="margin-top:16px">
    {{-- Par module --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Commandes par module') }}</h2></div>
        @php $maxMod = max(1, max(array_values($byModule ?: [0]))); @endphp
        @foreach($byModule as $mod=>$n)
            <div style="margin-bottom:12px">
                <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:4px"><span>{{ __($modLabels[$mod] ?? $mod) }}</span><b style="font-family:var(--fh)">{{ $n }}</b></div>
                <div style="height:8px;background:var(--bd);border-radius:999px;overflow:hidden"><div style="height:100%;width:{{ (int) round($n/$maxMod*100) }}%;background:var(--blue)"></div></div>
            </div>
        @endforeach
    </div>

    {{-- Top produits --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Top produits (Menu)') }}</h2></div>
        @if(empty($topItems))
            <div class="empty" style="padding:24px"><i class="fa-solid fa-bowl-food"></i>{{ __('Aucune vente de produit.') }}</div>
        @else
            <table>
                <thead><tr><th>{{ __('Produit') }}</th><th>{{ __('Qté') }}</th><th>{{ __('Total') }}</th></tr></thead>
                <tbody>
                @foreach($topItems as $it)
                    <tr><td>{{ $it->name }}</td><td>{{ $it->qty }}</td><td>{{ Money::format($it->total,'HTG') }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
