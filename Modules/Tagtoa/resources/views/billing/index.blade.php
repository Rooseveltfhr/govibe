@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Revenu & forfait'))
@section('page', __('Revenu & forfait'))

@section('content')
<form method="POST" action="{{ url('/tagtoa/billing') }}">
    @csrf @method('PUT')
    <div class="card">
        <div class="h-row"><h2>{{ __('Comment TAGTOA est rémunéré') }}</h2></div>
        <p style="color:var(--muted);font-size:14px;margin-top:-8px">{{ __('Choisissez votre modèle. Vous pouvez changer à tout moment.') }}</p>

        @php $rm = old('revenue_model', $setting->revenue_model); @endphp
        <div class="grid g3" style="margin-top:14px">
            @foreach([
                'subscription'=>['Abonnement','fa-crown','Vous payez un forfait. Aucune commission sur vos ventes.'],
                'commission'=>['Commission','fa-percent','Pas de forfait. TAGTOA prélève un % sur chaque vente.'],
                'both'=>['Les deux','fa-layer-group','Forfait réduit + petite commission.'],
            ] as $k=>$v)
                <label class="card" style="cursor:pointer;border-color:{{ $rm===$k ? 'var(--blue)' : 'var(--bd)' }};{{ $rm===$k ? 'box-shadow:0 6px 22px rgba(0,85,255,.12)' : '' }}">
                    <div style="display:flex;align-items:center;gap:10px">
                        <input type="radio" name="revenue_model" value="{{ $k }}" @checked($rm===$k)>
                        <i class="fa-solid {{ $v[1] }}" style="color:var(--blue);font-size:18px"></i>
                        <b style="font-family:var(--fh)">{{ __($v[0]) }}</b>
                    </div>
                    <p style="font-size:13px;color:var(--muted);margin-top:8px">{{ __($v[2]) }}</p>
                </label>
            @endforeach
        </div>

        <div class="row" style="margin-top:14px">
            <div><label class="lbl">{{ __('Commission (%)') }}</label><input class="inp" type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ old('commission_percent',$setting->commission_percent) }}"></div>
            <div><label class="lbl">{{ __('Frais fixe / transaction') }}</label><input class="inp" type="number" step="0.01" min="0" name="commission_fixed" value="{{ old('commission_fixed',$setting->commission_fixed) }}"></div>
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(Money::options() as $code=>$label)<option value="{{ $code }}" @selected(old('currency',$setting->currency)===$code)>{{ $label }}</option>@endforeach</select></div>
        </div>
        <button class="btn btn-p" style="margin-top:18px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
    </div>
</form>

{{-- Relevé par devise --}}
<div class="h-row" style="margin-top:26px">
    <h2 style="flex:1">{{ __('Relevé des commissions') }}</h2>
    <a href="{{ route('tagtoa.billing.export') }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-file-csv"></i> {{ __('Export CSV') }}</a>
    @if($accruedCount)
        <form method="POST" action="{{ route('tagtoa.billing.settle') }}" onsubmit="return confirm('{{ __('Marquer toutes les commissions à régler comme réglées ?') }}')" style="flex:0">
            @csrf<button class="btn btn-p btn-sm"><i class="fa-solid fa-circle-check"></i> {{ __('Régler les commissions') }} ({{ $accruedCount }})</button>
        </form>
    @endif
</div>

@if($summary->isEmpty())
    <div class="card"><div class="empty"><i class="fa-solid fa-sack-dollar"></i>{{ __('Aucune commission enregistrée.') }}</div></div>
@else
    @foreach($summary as $s)
        <div class="card" style="margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
                <b style="font-family:var(--fh);font-size:15px">{{ $s->currency }}</b>
                <span class="pill n">{{ $s->n }} {{ __('transactions') }}</span>
            </div>
            <div class="grid g4">
                <div class="stat"><div class="ic"><i class="fa-solid fa-sack-dollar"></i></div><div class="v" style="font-size:20px">{{ Money::format($s->gross,$s->currency) }}</div><div class="k">{{ __('Ventes brutes') }}</div></div>
                <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-percent"></i></div><div class="v" style="font-size:20px">{{ Money::format($s->fees,$s->currency) }}</div><div class="k">{{ __('Commissions TAGTOA') }}</div></div>
                <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-hourglass-half"></i></div><div class="v" style="font-size:20px">{{ Money::format($s->accrued,$s->currency) }}</div><div class="k">{{ __('À régler') }}</div></div>
                <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div><div class="v" style="font-size:20px">{{ Money::format($s->settled,$s->currency) }}</div><div class="k">{{ __('Réglé') }}</div></div>
            </div>
        </div>
    @endforeach
@endif

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Journal des commissions') }}</h2></div>
    @if($commissions->isEmpty())
        <div class="empty"><i class="fa-regular fa-rectangle-list"></i>{{ __('Aucune commission enregistrée.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Module') }}</th><th>{{ __('Brut') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Net') }}</th><th>{{ __('Statut') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($commissions as $c)
                @php $cm = $c->status_meta; @endphp
                <tr><td>{{ $c->source_type }} #{{ $c->source_id }}</td>
                    <td><span class="pill n">{{ $c->module }}</span></td>
                    <td>{{ Money::format($c->gross_amount,$c->currency) }}</td>
                    <td style="color:var(--red)">{{ Money::format($c->commission_amount,$c->currency) }}</td>
                    <td style="color:var(--green)">{{ Money::format($c->net_amount,$c->currency) }}</td>
                    <td><span class="pill {{ $cm['pill'] }}">{{ __($cm['label']) }}</span></td>
                    <td style="color:var(--muted)">{{ optional($c->created_at)->format('d/m/y H:i') }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $commissions->links() }}</div>
    @endif
</div>
@endsection
