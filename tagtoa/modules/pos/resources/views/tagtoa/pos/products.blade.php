{{-- TAGTOA POS — gestion des produits (boutons). ADAPTER @extends. --}}
@extends('layouts.app')
@section('content')
<div class="container py-4" style="max-width:820px">
    <a href="{{ route('tagtoa.pos.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}</a>
    <div class="d-flex justify-content-between align-items-center my-3">
        <h4 class="fw-bold mb-0" style="font-family:'Space Grotesk',sans-serif">{{ $terminal->name }} — {{ __('Produits') }}</h4>
        <a href="{{ route('tagtoa.pos.register',$terminal->id) }}" class="btn btn-sm btn-dark"><i class="fa-solid fa-cash-register me-1"></i>{{ __('Ouvrir caisse') }}</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('tagtoa.pos.products.save',$terminal->id) }}">
        @csrf
        <button type="button" class="btn btn-sm btn-dark mb-2" onclick="addP()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</button>
        <div id="plist"></div>
        <button class="btn btn-primary w-100 mt-3" style="background:#0055FF;border:0;padding:12px"><i class="fa-solid fa-floppy-disk me-1"></i>{{ __('Enregistrer') }}</button>
    </form>
</div>

<template id="p-tpl">
    <div class="card border-0 shadow-sm mb-2 p-row" style="border-radius:12px"><div class="card-body py-2 d-flex gap-2 align-items-center flex-wrap">
        <input name="products[IDX][emoji]" class="form-control form-control-sm" placeholder="🍔" style="max-width:64px">
        <input name="products[IDX][name]" class="form-control form-control-sm" placeholder="{{ __('Nom') }}" style="max-width:180px">
        <input name="products[IDX][price]" type="number" step="0.01" class="form-control form-control-sm" placeholder="{{ __('Prix') }}" style="max-width:100px">
        <input name="products[IDX][stock]" type="number" class="form-control form-control-sm" placeholder="{{ __('Stock') }}" style="max-width:90px">
        <input name="products[IDX][color]" type="color" class="form-control form-control-sm form-control-color" value="#0055FF">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="products[IDX][is_active]" value="1" checked><label class="form-check-label small">{{ __('Actif') }}</label></div>
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="this.closest('.p-row').remove()"><i class="fa-solid fa-trash"></i></button>
    </div></div>
</template>
<script>
var pIdx=0;
function addP(d){
    var h=document.getElementById('p-tpl').innerHTML.replace(/IDX/g,pIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;
    document.getElementById('plist').appendChild(r);
    if(d){r.querySelector('[name$="[emoji]"]').value=d.emoji||'';r.querySelector('[name$="[name]"]').value=d.name||'';r.querySelector('[name$="[price]"]').value=d.price||'';r.querySelector('[name$="[stock]"]').value=d.stock==null?'':d.stock;r.querySelector('[name$="[color]"]').value=d.color||'#0055FF';r.querySelector('[name$="[is_active]"]').checked=!!d.is_active;
        var i=document.createElement('input');i.type='hidden';i.name='products['+pIdx+'][id]';i.value=d.id;r.appendChild(i);}
    pIdx++;
}
var ex=@json($terminal->products->map(fn($p)=>['id'=>$p->id,'emoji'=>$p->emoji,'name'=>$p->name,'price'=>$p->price,'stock'=>$p->stock,'color'=>$p->color,'is_active'=>$p->is_active]));
if(ex.length){ex.forEach(addP);}else{addP();}
</script>
@endsection
