@extends('tagtoa::layouts.dashboard')
@section('title', __('Forfaits TAGTOA'))
@section('page', __('Forfaits TAGTOA'))

@section('content')

<div class="card" style="margin-bottom:18px">
    <div class="h-row"><h2><i class="fa-solid fa-layer-group"></i> {{ __('Prix & limites des forfaits') }}</h2></div>
    <p style="color:var(--muted);margin:0">{{ __('Modifiez ici les prix et les limites de chaque forfait TAGTOA. Ces valeurs remplacent la configuration par défaut. Limite vide = illimité · 0 = bloqué.') }}</p>
</div>

<form method="POST" action="{{ route('tagtoa.superadmin.plans.update') }}">
    @csrf
    @method('PUT')
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:16px">
        @foreach($plans as $key => $p)
            <div class="card">
                <div class="h-row"><h2 style="text-transform:capitalize">{{ $key }}</h2></div>
                <input type="hidden" name="plans[{{ $key }}][_k]" value="{{ $key }}">
                <label class="lbl">{{ __('Nom affiché') }}</label>
                <input class="inp" name="plans[{{ $key }}][label]" value="{{ $p['label'] ?? ucfirst($key) }}" maxlength="60">
                <label class="lbl">{{ __('Prix / mois') }} ({{ __('vide = sur devis') }})</label>
                <input class="inp" name="plans[{{ $key }}][price]" type="number" step="0.01" min="0" value="{{ $p['price'] ?? '' }}" placeholder="{{ __('Sur devis') }}">

                <div style="margin-top:14px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)">{{ __('Limites') }}</div>
                @foreach($features as $f)
                    @php $lim = $p['limits'][$f] ?? null; @endphp
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:8px">
                        <span style="font-size:14px;text-transform:capitalize">{{ $f }}</span>
                        <input class="inp" style="width:110px" name="plans[{{ $key }}][limits][{{ $f }}]" type="number" min="0" max="100000" value="{{ $lim === null ? '' : $lim }}" placeholder="∞">
                    </div>
                @endforeach
            </div>
        @endforeach
    </div>
    <button class="btn btn-p" type="submit" style="margin-top:18px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer les forfaits') }}</button>
</form>
@endsection
