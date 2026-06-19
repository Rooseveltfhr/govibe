@extends('tagtoa::layouts.dashboard')
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
                        <b style="font-family:var(--fh)">{{ $v[0] }}</b>
                    </div>
                    <p style="font-size:13px;color:var(--muted);margin-top:8px">{{ $v[2] }}</p>
                </label>
            @endforeach
        </div>

        <div class="row" style="margin-top:14px">
            <div><label class="lbl">{{ __('Commission (%)') }}</label><input class="inp" type="number" step="0.01" min="0" max="100" name="commission_percent" value="{{ old('commission_percent',$setting->commission_percent) }}"></div>
            <div><label class="lbl">{{ __('Frais fixe / transaction') }}</label><input class="inp" type="number" step="0.01" min="0" name="commission_fixed" value="{{ old('commission_fixed',$setting->commission_fixed) }}"></div>
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(['HTG','USD'] as $c)<option @selected(old('currency',$setting->currency)===$c)>{{ $c }}</option>@endforeach</select></div>
        </div>
        <button class="btn btn-p" style="margin-top:18px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
    </div>
</form>

<div class="grid g3" style="margin-top:16px">
    <div class="stat"><div class="ic"><i class="fa-solid fa-sack-dollar"></i></div><div class="v">{{ number_format($totals['gross'],2) }}</div><div class="k">{{ __('Ventes brutes') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fdecea;color:#9a2820"><i class="fa-solid fa-percent"></i></div><div class="v">{{ number_format($totals['fees'],2) }}</div><div class="k">{{ __('Commissions TAGTOA') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-hand-holding-dollar"></i></div><div class="v">{{ number_format($totals['net'],2) }}</div><div class="k">{{ __('Net marchand') }}</div></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('Journal des commissions') }}</h2></div>
    @if($commissions->isEmpty())
        <div class="empty"><i class="fa-regular fa-rectangle-list"></i>{{ __('Aucune commission enregistrée.') }}</div>
    @else
        <table>
            <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Module') }}</th><th>{{ __('Brut') }}</th><th>{{ __('Commission') }}</th><th>{{ __('Net') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($commissions as $c)
                <tr><td>{{ $c->source_type }} #{{ $c->source_id }}</td>
                    <td><span class="pill n">{{ $c->module }}</span></td>
                    <td>{{ number_format($c->gross_amount,2) }}</td>
                    <td style="color:var(--red)">{{ number_format($c->commission_amount,2) }}</td>
                    <td style="color:var(--green)">{{ number_format($c->net_amount,2) }}</td>
                    <td style="color:var(--muted)">{{ $c->created_at->format('d/m/y H:i') }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $commissions->links() }}</div>
    @endif
</div>
@endsection
