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
        </div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/pay/</span><input class="inp" name="alias" value="{{ old('alias',$page->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Description') }}</label>
        <textarea class="inp" name="description" rows="2">{{ old('description',$page->description) }}</textarea>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$page->is_active ?? true))> {{ __('Page active (visible publiquement)') }}</label>
    </div>

    <div class="card">
        <div class="h-row"><h2>{{ __('Méthodes de paiement') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addM()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <div id="mlist"></div>
    </div>
    <button class="btn btn-p" style="margin-top:4px"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

<template id="mtpl">
    <div class="card mrow" style="background:var(--bg);margin-top:10px">
        <div class="row">
            <select name="methods[IDX][type]" class="sel" style="max-width:200px">
                @foreach($methods as $k=>$meta)<option value="{{ $k }}">{{ $meta['label'] }}</option>@endforeach
            </select>
            <input name="methods[IDX][label]" class="inp" placeholder="{{ __('Libellé (ex: Mon MonCash)') }}">
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.mrow').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="row" style="margin-top:8px">
            <input name="methods[IDX][account_holder]" class="inp" placeholder="{{ __('Nom du compte') }}">
            <input name="methods[IDX][institution]" class="inp" placeholder="{{ __('Institution (banque / wallet)') }}">
            <input name="methods[IDX][account_number]" class="inp" placeholder="{{ __('Compte / N° / wallet') }}">
        </div>
        <input name="methods[IDX][instructions]" class="inp" placeholder="{{ __('Instructions (optionnel)') }}" style="margin-top:8px">
        <div class="row" style="margin-top:8px;align-items:center">
            <div><label class="lbl" style="margin:0 0 4px">{{ __('QR (image)') }}</label><input type="file" name="methods[IDX][qr]" accept="image/*" class="inp"></div>
            <div><label class="lbl" style="margin:0 0 4px">{{ __('Logo (image)') }}</label><input type="file" name="methods[IDX][logo]" accept="image/*" class="inp"></div>
            <label class="switch" style="flex:0"><input type="checkbox" name="methods[IDX][requires_proof]" value="1" checked> {{ __('Preuve requise') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="methods[IDX][is_active]" value="1" checked> {{ __('Active') }}</label>
        </div>
    </div>
</template>
@push('scripts')
<script>
var mIdx=0;
function addM(d){var h=document.getElementById('mtpl').innerHTML.replace(/IDX/g,mIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('mlist').appendChild(r);
    if(d){r.querySelector('[name$="[type]"]').value=d.type;r.querySelector('[name$="[label]"]').value=d.label||'';r.querySelector('[name$="[account_holder]"]').value=d.account_holder||'';r.querySelector('[name$="[institution]"]').value=d.institution||'';r.querySelector('[name$="[account_number]"]').value=d.account_number||'';r.querySelector('[name$="[instructions]"]').value=d.instructions||'';r.querySelector('[name$="[requires_proof]"]').checked=!!d.requires_proof;r.querySelector('[name$="[is_active]"]').checked=!!d.is_active;var i=document.createElement('input');i.type='hidden';i.name='methods['+mIdx+'][id]';i.value=d.id;r.appendChild(i);}
    mIdx++;}
@php
    $methodData = $page->relationLoaded('methods')
        ? $page->methods->map(fn ($m) => ['id' => $m->id, 'type' => $m->type, 'label' => $m->label, 'account_holder' => $m->account_holder, 'institution' => $m->institution, 'account_number' => $m->account_number, 'instructions' => $m->instructions, 'requires_proof' => $m->requires_proof, 'is_active' => $m->is_active])->values()
        : [];
@endphp
var ex=@json($methodData);
if(ex.length){ex.forEach(addM);}else{addM();}
</script>
@endpush
@endsection
