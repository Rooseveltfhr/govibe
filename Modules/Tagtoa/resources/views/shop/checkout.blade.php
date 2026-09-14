@extends('tagtoa::layouts.dashboard')
@section('title', __('Commander'))
@section('page', __('Où livrer ?'))

@push('head')
<style>
.ic{width:100%;padding:10px 12px;border:1.5px solid var(--bd);border-radius:10px;font:15px var(--fb);background:#fff}
.ic:focus{outline:0;border-color:var(--blue)}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px}
.pf .w2{grid-column:span 2}
.rec{display:flex;justify-content:space-between;gap:10px;padding:8px 0;font-size:14px}
.rec + .rec{border-top:1px solid var(--bd)}
</style>
@endpush

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.shop.index') }}" style="color:var(--muted);font-size:14px">
        <i class="fa-solid fa-arrow-left"></i> {{ __('Modifier le panier') }}
    </a>
</div>

<div class="card">
    <div class="h-row" style="margin-bottom:8px"><h2>{{ __('Récapitulatif') }}</h2></div>
    @foreach($chiffre['lignes'] as $l)
        <div class="rec">
            <span>{{ $l['item']->name }} <span style="color:var(--muted)">× {{ $l['qty'] }}</span></span>
            <b>{{ \Modules\Tagtoa\App\Support\Money::format($l['line_total'], $chiffre['devise']) }}</b>
        </div>
    @endforeach
    <div class="rec" style="font:700 16px var(--fh)">
        <span>{{ __('Sous-total') }}</span>
        <span>{{ \Modules\Tagtoa\App\Support\Money::format($chiffre['subtotal'], $chiffre['devise']) }}</span>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-top:8px">
        {{-- Dire ce qu'on ne sait pas encore vaut mieux qu'annoncer un chiffre
             qu'il faudra démentir. --}}
        <i class="fa-solid fa-circle-info"></i>
        {{ __('Le transport sera ajouté par TAGTOA à la confirmation, selon votre ville. Rien n\'est prélevé maintenant.') }}
    </p>
</div>

<form method="POST" action="{{ route('tagtoa.shop.store') }}" class="card">
    @csrf
    {{-- Posée UNE FOIS au rendu : rechargée, réenvoyée, touchée deux fois par
         un doigt impatient sur une connexion qui repart — c'est la même clé,
         donc une seule commande, donc un seul carton livré. --}}
    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">

    <div class="h-row" style="margin-bottom:10px"><h2>{{ __('Livraison') }}</h2></div>

    <div class="pf">
        <div class="w2">
            <label class="lbl" for="cn">{{ __('Qui reçoit ?') }}</label>
            <input class="ic" id="cn" name="contact_name" required maxlength="120"
                   value="{{ old('contact_name', optional($commerce)->name) }}">
        </div>
        <div>
            <label class="lbl" for="cp">{{ __('Téléphone') }}</label>
            <input class="ic" id="cp" name="contact_phone" required maxlength="40" inputmode="tel"
                   value="{{ old('contact_phone', optional($commerce)->phone) }}">
        </div>
        <div>
            <label class="lbl" for="cv">{{ __('Ville') }}</label>
            <input class="ic" id="cv" name="city" maxlength="80" value="{{ old('city') }}"
                   placeholder="{{ __('Port-au-Prince') }}">
        </div>
        <div class="w2">
            <label class="lbl" for="ca">{{ __('Adresse') }}</label>
            <input class="ic" id="ca" name="address" required maxlength="255"
                   value="{{ old('address', optional($commerce)->address) }}">
        </div>
        <div class="w2">
            <label class="lbl" for="no">{{ __('Note') }}</label>
            <input class="ic" id="no" name="note" maxlength="255"
                   placeholder="{{ __('Facultatif : repère, horaire, précision') }}">
        </div>
    </div>

    <button class="btn btn-p" style="margin-top:16px">
        <i class="fa-solid fa-paper-plane"></i> {{ __('Envoyer la commande') }}
    </button>
</form>
@endsection
