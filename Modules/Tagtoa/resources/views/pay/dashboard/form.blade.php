@extends('tagtoa::layouts.dashboard')
@php $editing = $page->exists; @endphp
@section('title', $editing ? __('Modifier la page') : __('Nouvelle page'))
@section('page', $editing ? __('Modifier la page de paiement') : __('Nouvelle page de paiement'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.pay.dashboard.update',$page->id) : route('tagtoa.pay.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card">
        <div class="row">
            <div><label class="lbl">{{ __('Titre') }}</label><input class="inp" name="title" value="{{ old('title',$page->title) }}" placeholder="{{ __('Payez Jean Baptiste') }}"></div>
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="default_currency">@foreach(\Modules\Tagtoa\App\Support\Money::options() as $code=>$label)<option value="{{ $code }}" @selected(old('default_currency',$page->default_currency ?: 'HTG')===$code)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Prix fixe') }} <span style="font-weight:400;color:var(--muted)">({{ __('vide = le client choisit le montant') }})</span></label><input class="inp" name="amount" type="number" step="0.01" min="0" value="{{ old('amount',$page->amount) }}" placeholder="{{ __('Montant libre') }}"></div>
        </div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/pay/</span><input class="inp" name="alias" value="{{ old('alias',$page->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Description') }}</label>
        <textarea class="inp" name="description" rows="2">{{ old('description',$page->description) }}</textarea>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$page->is_active ?? true))> {{ __('Page active (visible publiquement)') }}</label>
    </div>

    @php
        // Méthodes déjà enregistrées, indexées par passerelle (clé du catalogue).
        $saved = $page->relationLoaded('methods') ? $page->methods->keyBy('type') : collect();
    @endphp

    {{-- ---------- Passerelles AUTOMATIQUES (API) ---------- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Paiement automatique (API)') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Le client paie en ligne et la commande est validée toute seule. Activez seulement les passerelles dont les identifiants sont configurés.') }}
        </p>
        @foreach($catalog['auto'] as $type => $meta)
            @include('tagtoa::pay.dashboard.partials.gateway-row', ['type' => $type, 'meta' => $meta, 'm' => $saved->get($type), 'driverSpecs' => $driverSpecs])
        @endforeach
    </div>

    {{-- ---------- Passerelles MANUELLES (preuve) ---------- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Paiement manuel (vos comptes)') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Le client paie directement sur votre compte, puis envoie une preuve que vous approuvez. L\'argent ne passe jamais par TAGTOA.') }}
        </p>
        @foreach($catalog['manual'] as $type => $meta)
            @include('tagtoa::pay.dashboard.partials.gateway-row', ['type' => $type, 'meta' => $meta, 'm' => $saved->get($type), 'driverSpecs' => $driverSpecs])
        @endforeach
    </div>

    <button class="btn btn-p" style="margin-top:4px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

{{-- ---------- Comptes d'encaissement automatique (niveau marchand) ----------
     Section SÉPARÉE du formulaire de la page : ces identifiants valent pour
     toutes vos pages de paiement, pas seulement celle-ci. Un <form> ne peut de
     toute façon pas en contenir un autre. --}}
@if(!empty($driverSpecs))
    <div class="card" style="margin-top:18px">
        <div class="h-row"><h2>{{ __('Vos comptes d\'encaissement automatique') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Branchez vos propres identifiants pour encaisser en ligne. L\'argent arrive directement sur votre compte — TAGTOA ne touche jamais les fonds. Ces réglages s\'appliquent à toutes vos pages de paiement.') }}
        </p>

        @foreach($driverSpecs as $driver => $spec)
            <form method="POST" action="{{ route('tagtoa.pay.dashboard.gateway.credentials', $driver) }}" class="gwcred">
                @csrf
                @php
                    // Calculé ici et pas dans une directive Blade : une fonction
                    // flèche à l'intérieur d'un @if casse le compilateur.
                    $credFilled = array_values($spec['filled'] ?? []);
                    $credTotal  = count($spec['keys'] ?? []);
                    $credDone   = count(array_filter($credFilled));
                @endphp
                <div class="gwcred-head">
                    <b>{{ $spec['label'] }}</b>
                    @if($credTotal > 0 && $credDone === $credTotal)
                        <span class="pill g">{{ __('Branché') }}</span>
                    @elseif($credDone > 0)
                        <span class="pill a">{{ __('Incomplet') }}</span>
                    @else
                        <span class="pill n">{{ __('Non branché') }}</span>
                    @endif
                </div>

                <div class="row" style="margin-top:8px">
                    @foreach($spec['keys'] as $key)
                        <div>
                            <label class="lbl" style="margin-top:0">
                                {{ \Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields::label($key) }}
                            </label>
                            <input class="inp" type="password" autocomplete="new-password"
                                   name="credentials[{{ $key }}]"
                                   placeholder="{{ ($spec['filled'][$key] ?? false) ? '••••••••  '.__('(laisser vide pour conserver)') : __('Coller la valeur…') }}">
                        </div>
                    @endforeach

                    @if($spec['has_mode'])
                        <div style="max-width:200px">
                            <label class="lbl" style="margin-top:0">{{ __('Environnement') }}</label>
                            <select class="sel" name="mode">
                                <option value="sandbox" @selected(($spec['mode'] ?? 'sandbox') === 'sandbox')>{{ __('Test (sandbox)') }}</option>
                                <option value="live" @selected(($spec['mode'] ?? '') === 'live')>{{ __('Production (live)') }}</option>
                            </select>
                        </div>
                    @endif
                </div>

                <div style="display:flex;align-items:center;gap:14px;margin-top:10px">
                    <button class="btn btn-d btn-sm"><i class="fa-solid fa-lock"></i> {{ __('Enregistrer') }}</button>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;color:var(--red)">
                        <input type="checkbox" name="clear" value="1"> {{ __('Effacer mes identifiants') }}
                    </label>
                </div>
            </form>
        @endforeach
    </div>
@endif

<style>
    .gwrow{border:1px solid var(--bd);border-radius:12px;margin-top:10px;overflow:hidden}
    .gwhead{display:flex;align-items:center;gap:12px;padding:12px 14px;cursor:pointer;margin:0}
    .gwicon{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:15px;flex:0 0 34px}
    .gwname{font-weight:700;font-size:14.5px;flex:1}
    .gwtag{font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;text-transform:uppercase;letter-spacing:.03em}
    .gwtag.ok{background:#eafaf3;color:#0e5f44}
    .gwtag.off{background:#fff5e6;color:#7a5200}
    .gwbody{padding:0 14px 14px;border-top:1px solid var(--bd)}
    /* Interrupteur ouvrir/fermer */
    .sw{position:relative;width:46px;height:26px;flex:0 0 46px}
    .sw input{opacity:0;width:0;height:0;position:absolute}
    .sw span{position:absolute;inset:0;background:#c9d2c6;border-radius:999px;transition:.18s}
    .sw span::before{content:"";position:absolute;width:20px;height:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.18s}
    .sw input:checked + span{background:#2cb809}
    .sw input:checked + span::before{transform:translateX(20px)}
    /* Comptes d'encaissement du marchand */
    .gwcred{border:1px solid var(--bd);border-radius:12px;padding:14px;margin-top:12px;background:var(--bg)}
    .gwcred-head{display:flex;align-items:center;gap:10px}
    .gwcred-head b{font-family:var(--fh,sans-serif);font-size:14.5px}
</style>
@push('scripts')
<script>
    function gwToggle(cb){
        var body = cb.closest('.gwrow').querySelector('.gwbody');
        if (body) { body.style.display = cb.checked ? '' : 'none'; }
    }
</script>
@endpush

@endsection
