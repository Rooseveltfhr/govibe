@extends('tagtoa::layouts.dashboard')
@php $editing = $page->exists; @endphp
@section('title', $editing ? __('Modifier le lien') : __('Nouveau lien de paiement'))
@section('page', $editing ? __('Modifier le lien') : __('Nouveau lien de paiement'))

@section('content')
{{-- Formulaire volontairement court : les moyens de paiement se configurent une
     seule fois dans « Mes moyens de paiement », pas à chaque lien. --}}
<form method="POST" action="{{ $editing ? route('tagtoa.pay.dashboard.update',$page->id) : route('tagtoa.pay.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    <div class="card">
        <label class="lbl" style="margin-top:0">{{ __('Que voulez-vous faire ?') }}</label>
        <div class="ptype">
            @foreach(\Modules\Tagtoa\App\Models\Pay\PaymentPage::TYPES as $key => $label)
                <label class="ptype-opt">
                    <input type="radio" name="type" value="{{ $key }}"
                           @checked(old('type', $page->type ?: \Modules\Tagtoa\App\Models\Pay\PaymentPage::TYPE_INVOICE) === $key)>
                    <span>
                        <i class="fa-solid {{ $key === 'donation' ? 'fa-hand-holding-heart' : 'fa-file-invoice-dollar' }}"></i>
                        <b>{{ __($label) }}</b>
                        <em>{{ $key === 'donation' ? __('Le donateur choisit son montant') : __('Vous facturez un produit ou un service') }}</em>
                    </span>
                </label>
            @endforeach
        </div>

        <label class="lbl">{{ __('Nom du produit ou service') }}</label>
        <input class="inp" name="title" maxlength="160" required
               value="{{ old('title',$page->title) }}"
               placeholder="{{ __('Ex. Consultation, Robe brodée, Soutien au projet') }}">

        <label class="lbl">{{ __('Description') }} <span style="font-weight:400;color:var(--muted)">({{ __('optionnel') }})</span></label>
        <textarea class="inp" name="description" rows="3" maxlength="1000"
                  placeholder="{{ __('Ce que le client reçoit, les conditions, un délai…') }}">{{ old('description',$page->description) }}</textarea>

        <div class="row">
            <div>
                <label class="lbl">{{ __('Prix') }} <span style="font-weight:400;color:var(--muted)">({{ __('laisser vide = le client choisit') }})</span></label>
                <input class="inp" name="amount" type="number" step="0.01" min="0"
                       value="{{ old('amount',$page->amount) }}" placeholder="{{ __('Montant libre') }}">
            </div>
            <div>
                <label class="lbl">{{ __('Devise') }}</label>
                <select class="sel" name="default_currency">
                    @foreach(\Modules\Tagtoa\App\Support\Money::options() as $code=>$label)
                        <option value="{{ $code }}" @selected(old('default_currency',$page->default_currency ?: 'HTG')===$code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($editing)
            <label class="lbl">{{ __('Adresse du lien') }}</label>
            <div style="display:flex;align-items:center;gap:8px">
                <span style="color:var(--muted);font-size:14px">{{ url('/pay') }}/</span>
                <input class="inp" name="alias" value="{{ old('alias',$page->alias) }}">
            </div>
            <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$page->is_active ?? true))> {{ __('Lien actif') }}</label>
        @endif
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-p">
            <i class="fa-solid {{ $editing ? 'fa-floppy-disk' : 'fa-link' }}"></i>
            {{ $editing ? __('Enregistrer') : __('Créer et partager le lien') }}
        </button>
        @if($editing)
            <a href="{{ route('tagtoa.pay.dashboard.share',$page->id) }}" class="btn btn-o"><i class="fa-solid fa-share-nodes"></i> {{ __('Partager') }}</a>
        @endif
    </div>
</form>

<p style="color:var(--muted);font-size:13px;margin-top:14px">
    <i class="fa-solid fa-circle-info"></i>
    {{ __('Les moyens de paiement proposés sur ce lien viennent de vos réglages.') }}
    <a href="{{ route('tagtoa.pay.methods') }}" style="color:var(--blue,#2cb809);font-weight:600">{{ __('Configurer mes moyens de paiement') }}</a>
</p>

<style>
    .ptype{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:4px}
    @media (max-width:560px){ .ptype{grid-template-columns:1fr} }
    .ptype-opt input{position:absolute;opacity:0;width:0;height:0}
    .ptype-opt span{display:flex;flex-direction:column;gap:3px;border:1.5px solid var(--bd);border-radius:12px;padding:14px;cursor:pointer;transition:.15s}
    .ptype-opt span i{font-size:17px;color:var(--muted)}
    .ptype-opt span b{font-family:var(--fh,sans-serif);font-size:14.5px}
    .ptype-opt span em{font-style:normal;font-size:12.5px;color:var(--muted)}
    .ptype-opt input:checked + span{border-color:#2cb809;background:rgba(44,184,9,.06)}
    .ptype-opt input:checked + span i{color:#2cb809}
    .ptype-opt input:focus-visible + span{outline:2px solid #2cb809;outline-offset:2px}
</style>
@endsection
