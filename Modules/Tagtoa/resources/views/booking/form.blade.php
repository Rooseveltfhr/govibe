@extends('tagtoa::layouts.dashboard')
@php $editing = $page->exists; @endphp
@section('title', $editing ? __('Modifier la page') : __('Nouvelle page de réservation'))
@section('page', $editing ? __('Modifier la page') : __('Nouvelle page de réservation'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.booking.dashboard.update',$page->id) : route('tagtoa.booking.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    {{-- ----- Établissement ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Établissement') }}</h2></div>
        <label class="lbl">{{ __('Nom') }}</label>
        <input class="inp" name="name" value="{{ old('name',$page->name) }}" placeholder="{{ __('Ex. Studio Beauté 509') }}" required>
        <label class="lbl">{{ __('Alias (URL)') }}</label>
        <div style="display:flex;align-items:center;gap:8px"><span style="color:var(--muted);font-size:14px">tagtoa.com/book/</span><input class="inp" name="alias" value="{{ old('alias',$page->alias) }}" placeholder="{{ __('auto si vide') }}"></div>
        <label class="lbl">{{ __('Slogan') }}</label><input class="inp" name="tagline" value="{{ old('tagline',$page->tagline) }}" placeholder="{{ __('Coiffure • Soins • Sur rendez-vous') }}">
        <label class="lbl">{{ __('À propos') }}</label><textarea class="inp" name="about" rows="2" maxlength="1000">{{ old('about',$page->about) }}</textarea>
        <div class="row">
            <div><label class="lbl">{{ __('Logo') }}</label><input class="inp" type="file" name="logo" accept="image/*">@if($editing && $page->logo_url)<img src="{{ $page->logo_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
            <div><label class="lbl">{{ __('Couverture') }}</label><input class="inp" type="file" name="cover" accept="image/*">@if($editing && $page->cover_url)<img src="{{ $page->cover_url }}" style="height:42px;border-radius:10px;margin-top:8px">@endif</div>
        </div>
    </div>

    {{-- ----- Contact & paiement ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Contact & paiement') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('WhatsApp') }}</label><input class="inp" name="whatsapp" value="{{ old('whatsapp',$page->whatsapp) }}" placeholder="+509 0000 0000"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="phone" value="{{ old('phone',$page->phone) }}" placeholder="+509 0000 0000"></div>
        </div>
        <div class="row">
            <div><label class="lbl">{{ __('E-mail') }}</label><input class="inp" type="email" name="email" value="{{ old('email',$page->email) }}" placeholder="contact@exemple.com"></div>
            <div><label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$page->address) }}" placeholder="{{ __('Rue, ville') }}"></div>
        </div>
        <div class="row">
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(\Modules\Tagtoa\App\Support\Money::options() as $code=>$label)<option value="{{ $code }}" @selected(old('currency',$page->currency ?: \Modules\Tagtoa\App\Support\Locale::currencyFor())===$code)>{{ $label }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Page de paiement (TAGTOA Pay)') }}</label><select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$page->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select></div>
        </div>
    </div>

    {{-- ----- Apparence ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Apparence') }}</h2></div>
        <div class="row">
            <div><label class="lbl">{{ __('Thème') }}</label><select class="sel" name="theme">@foreach(['light'=>'Clair','dark'=>'Sombre'] as $k=>$v)<option value="{{ $k }}" @selected(old('theme',$page->theme ?: 'light')===$k)>{{ __($v) }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Couleur d\'accent') }}</label><input class="inp" type="color" name="accent_color" value="{{ old('accent_color',$page->accent_color ?: '#2cb809') }}" style="height:48px;padding:6px"></div>
        </div>
        <label class="switch"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active',$page->is_active ?? true))> {{ __('Page active (visible au public)') }}</label>
    </div>

    {{-- ----- Prestations ----- --}}
    <div class="card">
        <div class="h-row"><h2>{{ __('Prestations') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addSvc()"><i class="fa-solid fa-plus"></i> {{ __('Prestation') }}</button></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">{{ __('Définissez vos prestations réservables : nom, durée et prix.') }}</p>
        <div id="svcs"></div>
    </div>

    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

{{-- Template prestation --}}
<template id="svctpl">
    <div class="svcrow" style="background:#fff;border:1px solid var(--bd);border-radius:11px;padding:10px;margin-bottom:8px">
        <div style="display:flex;gap:8px;align-items:center">
            <input name="services[SIDX][name]" class="inp" placeholder="{{ __('Nom de la prestation') }}">
            <input name="services[SIDX][duration_min]" class="inp" type="number" min="5" step="5" placeholder="{{ __('Min') }}" style="max-width:90px" value="30">
            <input name="services[SIDX][price]" class="inp" type="number" step="0.01" min="0" placeholder="{{ __('Prix') }}" style="max-width:110px">
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.svcrow').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <input name="services[SIDX][description]" class="inp" placeholder="{{ __('Description (optionnel)') }}" style="margin-top:8px">
        <label class="switch" style="flex:0;margin-top:8px"><input type="hidden" name="services[SIDX][is_active]" value="0"><input type="checkbox" name="services[SIDX][is_active]" value="1" checked> {{ __('Active') }}</label>
    </div>
</template>

@push('scripts')
<script>
var sIdx = 0;

function addSvc(d){
    var si = sIdx++;
    var html = document.getElementById('svctpl').innerHTML.replace(/SIDX/g, si);
    var box = document.createElement('div'); box.innerHTML = html;
    var row = box.firstElementChild;
    document.getElementById('svcs').appendChild(row);
    if (d){
        row.querySelector('[name$="[name]"]').value = d.name || '';
        row.querySelector('[name$="[duration_min]"]').value = (d.duration_min != null ? d.duration_min : 30);
        row.querySelector('[name$="[price]"]').value = (d.price != null ? d.price : '');
        row.querySelector('[name$="[description]"]').value = d.description || '';
        row.querySelector('input[type=checkbox][name$="[is_active]"]').checked = d.is_active !== false;
        var h = document.createElement('input'); h.type='hidden'; h.name='services['+si+'][id]'; h.value=d.id; row.appendChild(h);
    }
    return row;
}

var existing = @json($page->relationLoaded('services') ? $page->services->map(fn($s)=>[
    'id'=>$s->id,'name'=>$s->name,'duration_min'=>$s->duration_min,'price'=>$s->price,'description'=>$s->description,'is_active'=>$s->is_active,
]) : []);

if (existing.length){ existing.forEach(addSvc); }
else { addSvc(); }
</script>
@endpush
@endsection
