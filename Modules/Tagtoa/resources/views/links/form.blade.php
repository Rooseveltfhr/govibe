@extends('tagtoa::layouts.dashboard')
@php $editing = $page->exists; @endphp
@section('title', $editing ? __('Modifier la page') : __('Nouvelle page'))
@section('page', $editing ? __('Modifier la page de liens') : __('Nouvelle page de liens'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.links.dashboard.update',$page->id) : route('tagtoa.links.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card">
        <div class="row">
            <div><label class="lbl">{{ __('Titre') }}</label><input class="inp" name="title" value="{{ old('title',$page->title) }}" placeholder="{{ __('Mon nom / marque') }}"></div>
            <div><label class="lbl">{{ __('Thème') }}</label><select class="sel" name="theme">@foreach(['dark'=>'Noir','light'=>'Blanc','blue'=>'Bleu'] as $k=>$v)<option value="{{ $k }}" @selected(old('theme',$page->theme ?: 'dark')===$k)>{{ $v }}</option>@endforeach</select></div>
        </div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/links/</span><input class="inp" name="alias" value="{{ old('alias',$page->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Bio') }}</label><textarea class="inp" name="bio" rows="2" maxlength="500">{{ old('bio',$page->bio) }}</textarea>
        <label class="lbl">{{ __('Avatar') }}</label><input class="inp" type="file" name="avatar" accept="image/*">
        @if($editing && $page->avatar_url)<img src="{{ $page->avatar_url }}" style="height:46px;border-radius:50%;margin-top:8px">@endif
        <div class="row">
            <div><label class="lbl">{{ __('Page de don (TAGTOA Pay)') }}</label><select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$page->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Texte du don') }}</label><input class="inp" name="donation_label" value="{{ old('donation_label',$page->donation_label) }}" placeholder="{{ __('Faire un don') }}"></div>
        </div>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$page->is_active ?? true))> {{ __('Page active') }}</label>
    </div>

    <div class="card">
        <div class="h-row"><h2>{{ __('Liens') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addL()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">{{ __('Le logo est détecté automatiquement depuis l\'URL.') }}</p>
        <div id="llist"></div>
    </div>
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

<template id="ltpl">
    <div class="lrow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <input name="links[IDX][label]" class="inp" placeholder="{{ __('Titre') }}" style="max-width:180px">
        <input name="links[IDX][url]" class="inp" placeholder="https://...">
        <label class="switch" style="flex:0" title="{{ __('Mettre en avant') }}"><input type="checkbox" name="links[IDX][is_featured]" value="1"></label>
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.lrow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>
@push('scripts')
<script>
var lIdx=0;
function addL(d){var h=document.getElementById('ltpl').innerHTML.replace(/IDX/g,lIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('llist').appendChild(r);
    if(d){r.querySelector('[name$="[label]"]').value=d.label||'';r.querySelector('[name$="[url]"]').value=d.url||'';r.querySelector('[name$="[is_featured]"]').checked=!!d.is_featured;var i=document.createElement('input');i.type='hidden';i.name='links['+lIdx+'][id]';i.value=d.id;r.appendChild(i);}
    lIdx++;}
var ex=@json($page->relationLoaded('links') ? $page->links->map(fn($l)=>['id'=>$l->id,'label'=>$l->label,'url'=>$l->url,'is_featured'=>$l->is_featured]) : []);
if(ex.length){ex.forEach(addL);}else{addL();}
</script>
@endpush
@endsection
