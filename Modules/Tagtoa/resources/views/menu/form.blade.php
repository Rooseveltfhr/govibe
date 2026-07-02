@extends('tagtoa::layouts.dashboard')
@php $editing = $menu->exists; @endphp
@section('title', $editing ? __('Modifier le menu') : __('Nouveau menu'))
@section('page', $editing ? __('Modifier le menu') : __('Nouveau menu'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.menu.dashboard.update',$menu->id) : route('tagtoa.menu.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    {{-- ----- Réglages de l'établissement ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Établissement') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Nom') }}</label><input class="inp" name="name" value="{{ old('name',$menu->name) }}" placeholder="{{ __('Ex. Lounge 509') }}" required></div>
            <div><label class="lbl">{{ __('Type') }}</label><select class="sel" name="type">@foreach(\Modules\Tagtoa\App\Models\Menu\Menu::TYPES as $k=>$v)<option value="{{ $k }}" @selected(old('type',$menu->type ?: 'restaurant')===$k)>{{ __($v['label']) }}</option>@endforeach</select></div>
        </div>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/menu/</span><input class="inp" name="alias" value="{{ old('alias',$menu->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Slogan') }}</label><input class="inp" name="tagline" value="{{ old('tagline',$menu->tagline) }}" placeholder="{{ __('Cuisine créole • Ambiance lounge') }}">
        <label class="lbl">{{ __('Description') }}</label><textarea class="inp" name="description" rows="2" maxlength="600">{{ old('description',$menu->description) }}</textarea>
        <div class="row">
            <div><label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*">@if($editing && $menu->logo_url)<img src="{{ $menu->logo_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
            <div><label class="lbl">{{ __('Couverture') }}</label><input class="inp" type="file" name="cover" accept="image/*">@if($editing && $menu->cover_url)<img src="{{ $menu->cover_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
        </div>
    </div>

    {{-- ----- Contact & commande ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Contact & commande') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('WhatsApp (commande)') }}</label><input class="inp" name="whatsapp" value="{{ old('whatsapp',$menu->whatsapp) }}" placeholder="+509 0000 0000"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone" value="{{ old('phone',$menu->phone) }}" placeholder="+509 0000 0000"></div>
        </div>
        <label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$menu->address) }}" placeholder="{{ __('Rue, ville') }}">
        <div class="row">
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(\Modules\Tagtoa\App\Support\Money::options() as $code=>$label)<option value="{{ $code }}" @selected(old('currency',$menu->currency ?: \Modules\Tagtoa\App\Support\Locale::currencyFor())===$code)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Page de paiement (TAGTOA Pay)') }}</label><select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$menu->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select></div>
        </div>
        <label class="switch"><input type="hidden" name="ordering_enabled" value="0"><input type="checkbox" name="ordering_enabled" value="1" @checked(old('ordering_enabled',$menu->ordering_enabled ?? true))> {{ __('Activer la commande WhatsApp') }}</label>
    </div>

    {{-- ----- Apparence ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Apparence') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Thème') }}</label><select class="sel" name="theme">@foreach(['light'=>'Clair','dark'=>'Sombre'] as $k=>$v)<option value="{{ $k }}" @selected(old('theme',$menu->theme ?: 'light')===$k)>{{ __($v) }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Couleur d\'accent') }}</label><input class="inp" type="color" name="accent_color" value="{{ old('accent_color',$menu->accent_color ?: '#2cb809') }}" style="height:48px;padding:6px"></div>
        </div>
        <label class="switch"><input type="hidden" name="show_prices" value="0"><input type="checkbox" name="show_prices" value="1" @checked(old('show_prices',$menu->show_prices ?? true))> {{ __('Afficher les prix') }}</label>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$menu->is_active ?? true))> {{ __('Menu actif (visible au public)') }}</label>
    </div>

    {{-- ----- Catégories & produits ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Catégories & produits') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addCat()"><i class="fa-solid fa-plus"></i> {{ __('Catégorie') }}</button></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">{{ __('Organisez vos produits & services par catégorie (Entrées, Plats, Boissons, Services…).') }}</p>
        <div id="cats"></div>
    </div>

    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer le menu') }}</button>
</form>

{{-- Template catégorie --}}
<template id="cattpl">
    <div class="catblock" data-ci="CIDX" style="border:1.5px solid var(--bd);border-radius:14px;padding:14px;margin-top:12px;background:#fafafa">
        <div style="display:flex;gap:8px;align-items:center">
            <input name="cats[CIDX][icon]" class="inp" placeholder="🍔" style="max-width:64px;text-align:center">
            <input name="cats[CIDX][name]" class="inp" placeholder="{{ __('Nom de la catégorie') }}" style="font-weight:600">
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.catblock').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="items" style="margin-top:10px"></div>
        <button type="button" class="btn btn-o btn-sm" onclick="addItem(this.closest('.catblock'))" style="margin-top:6px"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</button>
    </div>
</template>

{{-- Template produit/service --}}
<template id="itemtpl">
    <div class="itemrow" style="background:#fff;border:1px solid var(--bd);border-radius:11px;padding:10px;margin-bottom:8px">
        <div style="display:flex;gap:8px;align-items:center">
            <input name="cats[CIDX][items][IIDX][emoji]" class="inp" placeholder="🍔" style="max-width:56px;text-align:center">
            <input name="cats[CIDX][items][IIDX][name]" class="inp" placeholder="{{ __('Nom du produit / service') }}">
            <input name="cats[CIDX][items][IIDX][price]" class="inp" type="number" step="0.01" min="0" placeholder="{{ __('Prix') }}" style="max-width:110px">
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.itemrow').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <input name="cats[CIDX][items][IIDX][description]" class="inp" placeholder="{{ __('Description (optionnel)') }}" style="margin-top:8px">
        <div style="display:flex;gap:16px;align-items:center;margin-top:8px;flex-wrap:wrap">
            <input name="cats[CIDX][items][IIDX][badge]" class="inp" placeholder="{{ __('Badge: Nouveau, Promo…') }}" style="max-width:200px">
            <input name="cats[CIDX][items][IIDX][stock]" class="inp" type="number" min="0" placeholder="{{ __('Stock (vide = illimité)') }}" style="max-width:170px" title="{{ __('Laisser vide pour ne pas suivre le stock') }}">
            <label class="switch" style="flex:0"><input type="hidden" name="cats[CIDX][items][IIDX][is_available]" value="0"><input type="checkbox" name="cats[CIDX][items][IIDX][is_available]" value="1" checked> {{ __('Disponible') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="cats[CIDX][items][IIDX][is_featured]" value="1"> {{ __('Mis en avant') }}</label>
        </div>
    </div>
</template>

@push('scripts')
<script>
var cIdx = 0;

function addItem(catEl, d){
    var ci = catEl.getAttribute('data-ci');
    var ii = parseInt(catEl.getAttribute('data-ii') || '0', 10);
    catEl.setAttribute('data-ii', ii + 1);
    var html = document.getElementById('itemtpl').innerHTML.replace(/CIDX/g, ci).replace(/IIDX/g, ii);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    catEl.querySelector('.items').appendChild(row);
    if (d){
        row.querySelector('[name$="[emoji]"]').value = d.emoji || '';
        row.querySelector('[name$="[name]"]').value = d.name || '';
        row.querySelector('[name$="[price]"]').value = (d.price != null ? d.price : '');
        row.querySelector('[name$="[description]"]').value = d.description || '';
        row.querySelector('[name$="[badge]"]').value = d.badge || '';
        row.querySelector('[name$="[stock]"]').value = (d.stock != null ? d.stock : '');
        row.querySelector('input[type=checkbox][name$="[is_available]"]').checked = d.is_available !== false;
        row.querySelector('[name$="[is_featured]"]').checked = !!d.is_featured;
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][items]['+ii+'][id]'; h.value=d.id; row.appendChild(h);
    }
    return row;
}

function addCat(d){
    var ci = cIdx++;
    var html = document.getElementById('cattpl').innerHTML.replace(/CIDX/g, ci);
    var box = document.createElement('div'); box.innerHTML = html;
    var block = box.firstElementChild;
    document.getElementById('cats').appendChild(block);
    if (d){
        block.querySelector('[name$="[icon]"]').value = d.icon || '';
        block.querySelector('[name$="[name]"]').value = d.name || '';
        var h = document.createElement('input'); h.type='hidden'; h.name='cats['+ci+'][id]'; h.value=d.id; block.appendChild(h);
        (d.items || []).forEach(function(it){ addItem(block, it); });
    }
    return block;
}

var existing = @json($menu->relationLoaded('categories') ? $menu->categories->map(fn($c)=>[
    'id'=>$c->id,'name'=>$c->name,'icon'=>$c->icon,
    'items'=>$c->items->map(fn($i)=>['id'=>$i->id,'name'=>$i->name,'emoji'=>$i->emoji,'price'=>$i->price,'description'=>$i->description,'badge'=>$i->badge,'is_available'=>$i->is_available,'is_featured'=>$i->is_featured,'stock'=>$i->stock]),
]) : []);

if (existing.length){ existing.forEach(addCat); }
else { var c = addCat(); addItem(c); }
</script>
@endpush
@endsection
