@extends('tagtoa::layouts.dashboard')
@section('title', __('Produits'))
@section('page', $terminal->name.' — '.__('Produits'))

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.pos.register',$terminal->id) }}" class="btn btn-d btn-sm"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir caisse') }}</a>
</div>
<form method="POST" action="{{ route('tagtoa.pos.products.save',$terminal->id) }}">
    @csrf
    <div class="card">
        <button type="button" class="btn btn-d btn-sm" onclick="addP()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</button>
        <div id="plist" style="margin-top:12px"></div>
    </div>
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

<template id="ptpl">
    <div class="prow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
        <input name="products[IDX][emoji]" class="inp" placeholder="🍔" style="max-width:64px">
        <input name="products[IDX][name]" class="inp" placeholder="{{ __('Nom') }}" style="max-width:170px">
        <input name="products[IDX][price]" type="number" step="0.01" class="inp" placeholder="{{ __('Prix') }}" style="max-width:100px">
        <input name="products[IDX][stock]" type="number" class="inp" placeholder="{{ __('Stock') }}" style="max-width:90px">
        <input name="products[IDX][color]" type="color" value="#2cb809" style="width:42px;height:42px;border:1px solid var(--bd);border-radius:8px">
        <label class="switch" style="flex:0"><input type="checkbox" name="products[IDX][is_active]" value="1" checked></label>
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.prow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>
@push('scripts')
<script>
var pIdx=0;
function addP(d){var h=document.getElementById('ptpl').innerHTML.replace(/IDX/g,pIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('plist').appendChild(r);
    if(d){r.querySelector('[name$="[emoji]"]').value=d.emoji||'';r.querySelector('[name$="[name]"]').value=d.name||'';r.querySelector('[name$="[price]"]').value=d.price||'';r.querySelector('[name$="[stock]"]').value=d.stock==null?'':d.stock;r.querySelector('[name$="[color]"]').value=d.color||'#2cb809';r.querySelector('[name$="[is_active]"]').checked=!!d.is_active;var i=document.createElement('input');i.type='hidden';i.name='products['+pIdx+'][id]';i.value=d.id;r.appendChild(i);}
    pIdx++;}
var ex=@json($terminal->products->map(fn($p)=>['id'=>$p->id,'emoji'=>$p->emoji,'name'=>$p->name,'price'=>$p->price,'stock'=>$p->stock,'color'=>$p->color,'is_active'=>$p->is_active]));
if(ex.length){ex.forEach(addP);}else{addP();}
</script>
@endpush
@endsection
