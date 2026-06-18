{{-- TAGTOA PAY — Dashboard : créer / modifier une page + ses méthodes
     ADAPTER @extends au layout admin du projet. Bootstrap + vanilla JS. --}}
@extends('layouts.app')

@section('content')
@php $editing = $page->exists; @endphp
<div class="container py-4" style="max-width:760px">
    <a href="{{ route('tagtoa.pay.dashboard.index') }}" class="text-decoration-none small">
        <i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}
    </a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">
        {{ $editing ? __('Modifier la page PAY') : __('Nouvelle page PAY') }}
    </h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST"
          action="{{ $editing ? route('tagtoa.pay.dashboard.update', $page->id) : route('tagtoa.pay.dashboard.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card border-0 shadow-sm mb-3" style="border-radius:16px">
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Vcard associé') }} *</label>
                    <select name="vcard_id" class="form-select" required>
                        <option value="">{{ __('— Choisir —') }}</option>
                        @foreach($vcards as $v)
                            <option value="{{ $v->id }}" @selected(old('vcard_id', $page->vcard_id) == $v->id)>
                                {{ $v->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Titre') }}</label>
                    <input name="title" class="form-control" value="{{ old('title', $page->title) }}"
                           placeholder="{{ __('Payez Jean Baptiste') }}">
                </div>
                <div class="row">
                    <div class="col-8 mb-3">
                        <label class="form-label small fw-bold">{{ __('Alias (URL)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">tagtoa.com/pay/</span>
                            <input name="alias" class="form-control" value="{{ old('alias', $page->alias) }}"
                                   placeholder="{{ __('auto si vide') }}">
                        </div>
                    </div>
                    <div class="col-4 mb-3">
                        <label class="form-label small fw-bold">{{ __('Devise') }}</label>
                        <select name="default_currency" class="form-select">
                            @foreach(['HTG','USD'] as $c)
                                <option value="{{ $c }}" @selected(old('default_currency', $page->default_currency ?: 'HTG') == $c)>{{ $c }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="2">{{ old('description', $page->description) }}</textarea>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1"
                           id="act" @checked(old('is_active', $page->is_active ?? true))>
                    <label class="form-check-label" for="act">{{ __('Page active (visible publiquement)') }}</label>
                </div>
            </div>
        </div>

        {{-- Méthodes de paiement --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">{{ __('Méthodes de paiement') }}</h6>
            <button type="button" class="btn btn-sm btn-dark" onclick="tpAddMethod()">
                <i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}
            </button>
        </div>
        <div id="methods-list"></div>

        <button type="submit" class="btn btn-primary w-100 mt-3" style="background:#0055FF;border:0;padding:12px">
            <i class="fa-solid fa-floppy-disk me-1"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

{{-- Template d'une ligne méthode --}}
<template id="method-tpl">
    <div class="card border-0 shadow-sm mb-2 method-row" style="border-radius:14px">
        <div class="card-body">
            <div class="d-flex gap-2 mb-2">
                <select name="methods[IDX][type]" class="form-select form-select-sm" style="max-width:200px">
                    @foreach($paymentMethods as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
                <input name="methods[IDX][label]" class="form-control form-control-sm" placeholder="{{ __('Libellé (ex: Mon MonCash)') }}">
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="this.closest('.method-row').remove()">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
            <div class="row g-2">
                <div class="col-md-6"><input name="methods[IDX][account_holder]" class="form-control form-control-sm" placeholder="{{ __('Bénéficiaire') }}"></div>
                <div class="col-md-6"><input name="methods[IDX][account_number]" class="form-control form-control-sm" placeholder="{{ __('Compte / N° / wallet') }}"></div>
                <div class="col-12"><input name="methods[IDX][instructions]" class="form-control form-control-sm" placeholder="{{ __('Instructions (optionnel)') }}"></div>
                <div class="col-md-6">
                    <label class="small text-muted">{{ __('QR (image, optionnel)') }}</label>
                    <input type="file" name="methods[IDX][qr]" accept="image/*" class="form-control form-control-sm">
                </div>
                <div class="col-md-6 d-flex align-items-end gap-3">
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="methods[IDX][requires_proof]" value="1" checked><label class="form-check-label small">{{ __('Preuve requise') }}</label></div>
                    <div class="form-check"><input class="form-check-input" type="checkbox" name="methods[IDX][is_active]" value="1" checked><label class="form-check-label small">{{ __('Active') }}</label></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
var tpIdx = 0;
function tpAddMethod(data){
    var html = document.getElementById('method-tpl').innerHTML.replace(/IDX/g, tpIdx);
    var div = document.createElement('div'); div.innerHTML = html;
    var row = div.firstElementChild;
    document.getElementById('methods-list').appendChild(row);
    if(data){
        row.querySelector('[name$="[type]"]').value = data.type;
        row.querySelector('[name$="[label]"]').value = data.label || '';
        row.querySelector('[name$="[account_holder]"]').value = data.account_holder || '';
        row.querySelector('[name$="[account_number]"]').value = data.account_number || '';
        row.querySelector('[name$="[instructions]"]').value = data.instructions || '';
        row.querySelector('[name$="[requires_proof]"]').checked = !!data.requires_proof;
        row.querySelector('[name$="[is_active]"]').checked = !!data.is_active;
        // id caché pour update
        var hid=document.createElement('input'); hid.type='hidden';
        hid.name='methods['+tpIdx+'][id]'; hid.value=data.id; row.appendChild(hid);
    }
    tpIdx++;
}
// Pré-remplissage en édition
var existing = @json($page->relationLoaded('methods') ? $page->methods->map(fn($m)=>[
    'id'=>$m->id,'type'=>$m->type,'label'=>$m->label,'account_holder'=>$m->account_holder,
    'account_number'=>$m->account_number,'instructions'=>$m->instructions,
    'requires_proof'=>$m->requires_proof,'is_active'=>$m->is_active,
]) : []);
if(existing.length){ existing.forEach(tpAddMethod); } else { tpAddMethod(); }
</script>
@endsection
