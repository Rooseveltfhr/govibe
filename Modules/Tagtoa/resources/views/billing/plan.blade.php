@extends('tagtoa::layouts.dashboard')
@section('title', __('Abonnement'))
@section('page', __('Abonnement'))

@php
    $featLabels = ['site'=>'Site web','menu'=>'Menu','pay'=>'Paiements','links'=>'Liens','loyalty'=>'Fidélité','event'=>'Événements','pos'=>'Caisse (POS)'];
    $curCfg = $plans[$current] ?? [];
@endphp

@section('content')
{{-- Forfait actuel + usage --}}
<div class="card">
    <div class="h-row">
        <h2 style="flex:1">{{ __('Votre forfait') }}</h2>
        <span class="pill g" style="font-size:13px">{{ $curCfg['label'] ?? ucfirst($current) }}</span>
    </div>
    <table>
        <thead><tr><th>{{ __('Module') }}</th><th>{{ __('Utilisé') }}</th><th>{{ __('Limite') }}</th><th></th></tr></thead>
        <tbody>
        @foreach($usage as $f=>$u)
            @php $lim = $u['limit']; $used = $u['used']; $full = ($lim !== null && $used >= $lim); @endphp
            <tr>
                <td>{{ __($featLabels[$f] ?? ucfirst($f)) }}</td>
                <td>{{ $used }}</td>
                <td>{{ $lim === null ? __('Illimité') : ($lim === 0 ? __('Non inclus') : $lim) }}</td>
                <td style="text-align:right">
                    @if($lim === null)<span class="pill g">{{ __('OK') }}</span>
                    @elseif($full)<span class="pill r">{{ __('Limite atteinte') }}</span>
                    @else<span class="pill g">{{ __('OK') }}</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- Choix de forfait --}}
<div class="h-row" style="margin-top:26px"><h2>{{ __('Changer de forfait') }}</h2></div>
<div class="grid g3">
    @foreach($plans as $key=>$p)
        @php $isCur = $key === $current; @endphp
        <div class="card" style="{{ $key==='pro' ? 'border-color:var(--blue);box-shadow:0 6px 22px rgba(44,184,9,.12)' : '' }}">
            <div style="display:flex;justify-content:space-between;align-items:center">
                <b style="font-family:var(--fh);font-size:18px">{{ $p['label'] ?? ucfirst($key) }}</b>
                @if($key==='pro')<span class="pill a">{{ __('Populaire') }}</span>@endif
            </div>
            <div style="font-family:var(--fh);font-size:26px;font-weight:700;margin:8px 0">
                @if(($p['price'] ?? null) === null){{ __('Sur devis') }}
                @elseif((int)$p['price'] === 0){{ __('Gratuit') }}
                @else{{ number_format($p['price']) }} <span style="font-size:14px;color:var(--muted)">HTG/{{ __('mois') }}</span>@endif
            </div>
            <ul style="list-style:none;display:flex;flex-direction:column;gap:7px;margin:14px 0 18px;font-size:13.5px">
                @foreach($featLabels as $f=>$lbl)
                    @php $l = $p['limits'][$f] ?? 0; @endphp
                    <li style="display:flex;gap:8px;{{ $l === 0 ? 'opacity:.4' : '' }}">
                        <i class="fa-solid {{ $l === 0 ? 'fa-xmark' : 'fa-check' }}" style="color:{{ $l===0 ? 'var(--muted)' : 'var(--green)' }};margin-top:3px"></i>
                        {{ __($lbl) }} : {{ $l === null ? __('Illimité') : ($l === 0 ? __('Non inclus') : $l) }}
                    </li>
                @endforeach
            </ul>
            @if($isCur)
                <button class="btn btn-o" style="width:100%;justify-content:center" disabled>{{ __('Forfait actuel') }}</button>
            @else
                <form method="POST" action="{{ route('tagtoa.plan.subscribe') }}">
                    @csrf<input type="hidden" name="plan" value="{{ $key }}">
                    <button class="btn btn-p" style="width:100%;justify-content:center"><i class="fa-solid fa-arrow-up"></i> {{ __('Choisir ce forfait') }}</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
<p style="color:var(--muted);font-size:13px;margin-top:14px"><i class="fa-solid fa-circle-info"></i> {{ __('Le paiement automatique des forfaits arrive avec les passerelles. Pour l\'instant, le changement est immédiat.') }}</p>
@endsection
