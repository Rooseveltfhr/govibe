@extends('tagtoa::layouts.dashboard')
@php $editing = $site->exists; use Illuminate\Support\Facades\Storage; @endphp
@section('title', $editing ? __('Modifier le site') : __('Nouveau site'))
@section('page', $editing ? __('Modifier le site') : __('Nouveau site'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.site.dashboard.update',$site->id) : route('tagtoa.site.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    {{-- Identité --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Identité du site') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Nom') }}</label><input class="inp" name="name" value="{{ old('name',$site->name) }}" placeholder="{{ __('Nom de votre business') }}" required></div>
            <div><label class="lbl">{{ __('Slogan') }}</label><input class="inp" name="tagline" value="{{ old('tagline',$site->tagline) }}" placeholder="{{ __('Votre phrase d\'accroche') }}"></div>
        </div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/site/</span><input class="inp" name="alias" value="{{ old('alias',$site->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('À propos') }}</label><textarea class="inp" name="about" rows="4" maxlength="2000">{{ old('about',$site->about) }}</textarea>
        <div class="row">
            <div><label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*">@if($editing && $site->logo_url)<img src="{{ $site->logo_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
            <div><label class="lbl">{{ __('Couverture') }}</label><input class="inp" type="file" name="cover" accept="image/*">@if($editing && $site->cover_url)<img src="{{ $site->cover_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
        </div>
        <div class="row">
            <div><label class="lbl">{{ __('Thème') }}</label><select class="sel" name="theme">@foreach(['light'=>'Clair','dark'=>'Sombre'] as $k=>$v)<option value="{{ $k }}" @selected(old('theme',$site->theme ?: 'light')===$k)>{{ __($v) }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Couleur d\'accent') }}</label><input class="inp" type="color" name="accent_color" value="{{ old('accent_color',$site->accent_color ?: '#2cb809') }}" style="height:48px;padding:6px"></div>
        </div>
    </div>

    {{-- Contact --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Contact') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone" value="{{ old('phone',$site->phone) }}"></div>
            <div><label class="lbl">WhatsApp</label><input class="inp" name="whatsapp" value="{{ old('whatsapp',$site->whatsapp) }}" placeholder="+509 ..."></div>
        </div>
        <div class="row">
            <div><label class="lbl">Email</label><input class="inp" type="email" name="email" value="{{ old('email',$site->email) }}"></div>
            <div><label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$site->address) }}"></div>
        </div>
        <label class="lbl">{{ __('Carte (lien d\'intégration Google Maps)') }}</label><input class="inp" name="map_url" value="{{ old('map_url',$site->map_url) }}" placeholder="https://www.google.com/maps/embed?...">
    </div>

    {{-- Intégrations TAGTOA --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Boutons TAGTOA') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Menu lié') }}</label><select class="sel" name="menu_id"><option value="">{{ __('— Aucun —') }}</option>@foreach($menus as $m)<option value="{{ $m->id }}" @selected(old('menu_id',$site->menu_id)==$m->id)>{{ $m->name }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Page de paiement liée') }}</label><select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $p)<option value="{{ $p->id }}" @selected(old('pay_page_id',$site->pay_page_id)==$p->id)>{{ $p->title ?: $p->alias }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Page de liens liée') }}</label><select class="sel" name="link_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($linkPages as $l)<option value="{{ $l->id }}" @selected(old('link_page_id',$site->link_page_id)==$l->id)>{{ $l->title ?: $l->alias }}</option>@endforeach</select></div>
        </div>
    </div>

    {{-- Services --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Services') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addSvc()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <div id="svclist"></div>
    </div>

    {{-- Heures --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Heures d\'ouverture') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addHr()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <div id="hrlist"></div>
    </div>

    {{-- Réseaux sociaux --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Réseaux sociaux') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addSoc()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <div id="soclist"></div>
    </div>

    {{-- Galerie --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Galerie') }}</h2></div>
        <label class="lbl">{{ __('Ajouter des images') }}</label>
        <input class="inp" type="file" name="gallery_files[]" accept="image/*" multiple>
        @if($editing && !empty($site->gallery))
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px">
                @foreach($site->gallery as $g)<img src="{{ Storage::url($g) }}" style="height:64px;width:64px;object-fit:cover;border-radius:10px;border:1px solid var(--bd)">@endforeach
            </div>
        @endif
    </div>

    {{-- Affichage --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Sections affichées') }}</h2></div>
        @foreach(['show_services'=>'Services','show_gallery'=>'Galerie','show_hours'=>'Heures d\'ouverture','show_contact'=>'Contact'] as $k=>$lbl)
            <label class="switch"><input type="hidden" name="{{ $k }}" value="0"><input type="checkbox" name="{{ $k }}" value="1" @checked(old($k,$site->$k ?? true))> {{ __($lbl) }}</label>
        @endforeach
        <label class="switch"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published',$site->is_published ?? true))> {{ __('Site publié (visible au public)') }}</label>
    </div>

    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer le site') }}</button>
</form>

<template id="svctpl">
    <div class="rrow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <input name="services[IDX][icon]" class="inp" placeholder="fa-solid fa-star" style="max-width:170px">
        <input name="services[IDX][title]" class="inp" placeholder="{{ __('Titre du service') }}" style="max-width:200px">
        <input name="services[IDX][desc]" class="inp" placeholder="{{ __('Description') }}">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.rrow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>
<template id="hrtpl">
    <div class="rrow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <input name="hours[IDX][day]" class="inp" placeholder="{{ __('Jour (ex. Lun-Ven)') }}" style="max-width:220px">
        <input name="hours[IDX][value]" class="inp" placeholder="{{ __('Ex. 8h - 17h') }}">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.rrow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>
<template id="soctpl">
    <div class="rrow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <select name="socials[IDX][platform]" class="sel" style="max-width:170px">@foreach($platforms as $p)<option value="{{ $p }}">{{ ucfirst($p) }}</option>@endforeach</select>
        <input name="socials[IDX][url]" class="inp" placeholder="https://...">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.rrow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>

@push('scripts')
<script>
function mk(tplId, listId, idx, fill){
    var h=document.getElementById(tplId).innerHTML.replace(/IDX/g,idx);
    var x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;
    document.getElementById(listId).appendChild(r);if(fill)fill(r);return r;
}
var si=0,hi=0,ci=0;
function addSvc(d){mk('svctpl','svclist',si++,function(r){if(d){r.querySelector('[name$="[icon]"]').value=d.icon||'';r.querySelector('[name$="[title]"]').value=d.title||'';r.querySelector('[name$="[desc]"]').value=d.desc||'';}});}
function addHr(d){mk('hrtpl','hrlist',hi++,function(r){if(d){r.querySelector('[name$="[day]"]').value=d.day||'';r.querySelector('[name$="[value]"]').value=d.value||'';}});}
function addSoc(d){mk('soctpl','soclist',ci++,function(r){if(d){if(d.platform)r.querySelector('[name$="[platform]"]').value=d.platform;r.querySelector('[name$="[url]"]').value=d.url||'';}});}

var exSvc=@json(old('services', $site->services ?? []));
var exHr=@json(old('hours', $site->hours ?? []));
var exSoc=@json(old('socials', $site->socials ?? []));
(exSvc&&exSvc.length?exSvc:[0]).forEach(function(d){addSvc(d&&d.title?d:null);});
(exHr&&exHr.length?exHr:[0]).forEach(function(d){addHr(d&&d.day?d:null);});
(exSoc&&exSoc.length?exSoc:[0]).forEach(function(d){addSoc(d&&d.url?d:null);});
</script>
@endpush
@endsection
