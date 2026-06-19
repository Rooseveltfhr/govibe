{{-- TAGTOA BILLING — Modèle de revenu + commissions. ADAPTER @extends au layout admin. --}}
@extends('layouts.app')

@section('content')
<div class="container py-4" style="max-width:820px">
    <h4 class="mb-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">{{ __('Modèle de revenu TAGTOA') }}</h4>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('tagtoa.billing.update') }}">
        @csrf @method('PUT')
        <div class="card border-0 shadow-sm mb-4" style="border-radius:16px">
            <div class="card-body">
                <p class="text-muted small">{{ __('Choisissez comment TAGTOA génère ses revenus pour votre compte.') }}</p>
                <div class="row g-3">
                    @php $rm = old('revenue_model', $setting->revenue_model); @endphp
                    @foreach(['subscription'=>['Abonnement','fa-crown','Vous payez un forfait. Aucune commission sur vos ventes.'],'commission'=>['Commission','fa-percent','Pas de forfait. TAGTOA prélève un % sur chaque vente.'],'both'=>['Les deux','fa-layer-group','Forfait réduit + petite commission.']] as $k=>$v)
                        <div class="col-md-4">
                            <label class="card h-100 border {{ $rm===$k ? 'border-primary' : '' }}" style="cursor:pointer;border-radius:14px">
                                <div class="card-body text-center">
                                    <input type="radio" name="revenue_model" value="{{ $k }}" class="form-check-input mb-2" @checked($rm===$k)>
                                    <i class="fa-solid {{ $v[1] }} fa-lg d-block mb-2" style="color:#0055FF"></i>
                                    <b style="font-family:'Space Grotesk',sans-serif">{{ $v[0] }}</b>
                                    <p class="small text-muted mt-1 mb-0">{{ $v[2] }}</p>
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">{{ __('Commission (%)') }}</label>
                        <input name="commission_percent" type="number" step="0.01" min="0" max="100" class="form-control"
                               value="{{ old('commission_percent', $setting->commission_percent) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">{{ __('Frais fixe / transaction') }}</label>
                        <input name="commission_fixed" type="number" step="0.01" min="0" class="form-control"
                               value="{{ old('commission_fixed', $setting->commission_fixed) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold">{{ __('Devise') }}</label>
                        <select name="currency" class="form-select">
                            @foreach(['HTG','USD'] as $c)<option value="{{ $c }}" @selected(old('currency',$setting->currency)===$c)>{{ $c }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" style="background:#0055FF;border:0"><i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Enregistrer') }}</button>
            </div>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body"><div class="small text-muted">{{ __('Ventes brutes') }}</div><div class="fw-bold fs-5">{{ number_format($totals['fees'] + $totals['net'], 2) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body"><div class="small text-muted">{{ __('Commissions TAGTOA') }}</div><div class="fw-bold fs-5 text-danger">{{ number_format($totals['fees'], 2) }}</div></div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm" style="border-radius:14px"><div class="card-body"><div class="small text-muted">{{ __('Net marchand') }}</div><div class="fw-bold fs-5 text-success">{{ number_format($totals['net'], 2) }}</div></div></div></div>
    </div>

    <h6 class="fw-bold">{{ __('Journal des commissions') }}</h6>
    @if($commissions->isEmpty())
        <p class="text-muted small">{{ __('Aucune commission enregistrée.') }}</p>
    @else
        <table class="table table-sm align-middle">
            <thead><tr><th>{{ __('Source') }}</th><th>{{ __('Module') }}</th><th class="text-end">{{ __('Brut') }}</th><th class="text-end">{{ __('Commission') }}</th><th class="text-end">{{ __('Net') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @foreach($commissions as $c)
                <tr>
                    <td class="small">{{ $c->source_type }} #{{ $c->source_id }}</td>
                    <td><span class="badge bg-light text-dark">{{ $c->module }}</span></td>
                    <td class="text-end">{{ number_format($c->gross_amount,2) }}</td>
                    <td class="text-end text-danger">{{ number_format($c->commission_amount,2) }}</td>
                    <td class="text-end text-success">{{ number_format($c->net_amount,2) }}</td>
                    <td class="small text-muted">{{ $c->created_at->format('d/m/y H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $commissions->links() }}
    @endif
</div>
@endsection
