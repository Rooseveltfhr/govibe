@extends('tagtoa::layouts.dashboard')
@php $editing = $store->exists; @endphp
@section('title', $editing ? __('Modifier la boutique') : __('Nouvelle boutique'))
@section('page', $editing ? __('Modifier la boutique') : __('Nouvelle boutique'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.store.dashboard.update',$store->id) : route('tagtoa.store.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card">
        <label class="lbl">{{ __('Nom de la boutique') }} *</label>
        <input class="inp" name="name" required value="{{ old('name',$store->name) }}">
        <div class="row">
            <div><label class="lbl">{{ __('Slogan') }}</label><input class="inp" name="tagline" value="{{ old('tagline',$store->tagline) }}"></div>
            <div><label class="lbl">{{ __('Devise') }}</label>
                <select class="sel" name="currency">@foreach(['HTG','USD','EUR','DOP','CAD'] as $c)<option @selected(old('currency',$store->currency ?: 'HTG')===$c)>{{ $c }}</option>@endforeach</select></div>
        </div>
        <label class="lbl">{{ __('Description') }}</label>
        <textarea class="inp" name="description" rows="3">{{ old('description',$store->description) }}</textarea>
        <div class="row">
            <div><label class="lbl">{{ __('WhatsApp') }}</label><input class="inp" name="whatsapp" value="{{ old('whatsapp',$store->whatsapp) }}" placeholder="+509…"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone" value="{{ old('phone',$store->phone) }}"></div>
        </div>
        <label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$store->address) }}">
        <label class="lbl">{{ __('Infos livraison / retrait') }}</label><input class="inp" name="delivery_note" value="{{ old('delivery_note',$store->delivery_note) }}" placeholder="{{ __('Ex: Livraison Port-au-Prince 250 HTG') }}">
        <div class="row">
            <div><label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*"></div>
            <div><label class="lbl">{{ __('Bannière') }}</label><input class="inp" type="file" name="cover" accept="image/*"></div>
        </div>
        <label class="lbl">{{ __('Page de paiement (optionnel)') }}</label>
        <select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$store->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select>
        <label class="switch" style="margin-top:12px"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published',$store->is_published))> {{ __('Publier la boutique') }}</label>
    </div>

    @if($editing)
    <div class="card">
        <div class="h-row"><h2>{{ __('Produits') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addP()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</button></div>
        <div id="plist"></div>
        <p style="color:var(--muted);font-size:13px;margin-top:8px">{{ __('Astuce : cochez « Vedette » pour mettre un produit en avant. Laissez le stock vide pour illimité.') }}</p>
    </div>
    @else
        <p style="color:var(--muted);font-size:14px;margin:10px 0">{{ __('Enregistrez, puis ajoutez vos produits.') }}</p>
    @endif
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

@if($editing)
<template id="ptpl">
    <div class="prow card" style="margin-top:10px;padding:14px">
        <div class="row">
            <div style="flex:2"><input name="products[IDX][name]" class="inp" placeholder="{{ __('Nom du produit') }}"></div>
            <div><input name="products[IDX][price]" type="number" step="0.01" class="inp" placeholder="{{ __('Prix') }}"></div>
            <div><input name="products[IDX][compare_price]" type="number" step="0.01" class="inp" placeholder="{{ __('Prix barré') }}"></div>
        </div>
        <div class="row" style="margin-top:8px">
            <div><input name="products[IDX][category]" class="inp" placeholder="{{ __('Catégorie') }}"></div>
            <div><input name="products[IDX][stock]" type="number" class="inp" placeholder="{{ __('Stock (∞)') }}"></div>
            <div><input name="products[IDX][image]" type="file" accept="image/*" class="inp"></div>
        </div>
        <input name="products[IDX][description]" class="inp" placeholder="{{ __('Description courte') }}" style="margin-top:8px">
        <div style="display:flex;gap:16px;align-items:center;margin-top:10px">
            <label class="switch" style="flex:0"><input type="hidden" name="products[IDX][is_available]" value="0"><input type="checkbox" name="products[IDX][is_available]" value="1" checked> {{ __('Disponible') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="products[IDX][is_featured]" value="1"> {{ __('Vedette') }}</label>
            <button type="button" class="btn btn-o btn-sm" style="flex:0;margin-left:auto;color:var(--red)" onclick="this.closest('.prow').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
    </div>
</template>
@push('scripts')
<script>
var pIdx=0;
function addP(d){var h=document.getElementById('ptpl').innerHTML.replace(/IDX/g,pIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('plist').appendChild(r);
    if(d){r.querySelector('[name$="[name]"]').value=d.name||'';r.querySelector('[name$="[price]"]').value=d.price||'';r.querySelector('[name$="[compare_price]"]').value=d.compare_price||'';r.querySelector('[name$="[category]"]').value=d.category||'';r.querySelector('[name$="[stock]"]').value=d.stock==null?'':d.stock;r.querySelector('[name$="[description]"]').value=d.description||'';r.querySelector('[name$="[is_available]"]').checked=!!d.is_available;r.querySelector('[name$="[is_featured]"]').checked=!!d.is_featured;var i=document.createElement('input');i.type='hidden';i.name='products['+pIdx+'][id]';i.value=d.id;r.appendChild(i);}
    pIdx++;}
@php
    $exProducts = $store->relationLoaded('products')
        ? $store->products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => $p->price, 'compare_price' => $p->compare_price, 'category' => $p->category, 'stock' => $p->stock, 'description' => $p->description, 'is_available' => $p->is_available, 'is_featured' => $p->is_featured])->values()
        : [];
@endphp
var ex=@json($exProducts);
if(ex.length){ex.forEach(addP);}else{addP();}
</script>
@endpush
@endif
@endsection
