@extends('tagtoa::layouts.dashboard')
@php $editing = $event->exists; @endphp
@section('title', $editing ? __('Modifier l\'événement') : __('Nouvel événement'))
@section('page', $editing ? __('Modifier l\'événement') : __('Nouvel événement'))

@section('content')
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.event.dashboard.update',$event->id) : route('tagtoa.event.dashboard.store') }}">
    @csrf @if($editing) @method('PUT') @endif
    <div class="card">
        <label class="lbl">{{ __('Titre') }} *</label><input class="inp" name="title" required value="{{ old('title',$event->title) }}">
        <div class="row">
            <div><label class="lbl">{{ __('Type') }}</label><select class="sel" name="type">@foreach(['concert','expo','mariage','sport','conference','autre'] as $t)<option @selected(old('type',$event->type)===$t)>{{ ucfirst($t) }}</option>@endforeach</select></div>
            <div><label class="lbl">{{ __('Devise') }}</label><select class="sel" name="currency">@foreach(['HTG','USD'] as $c)<option @selected(old('currency',$event->currency ?: 'HTG')===$c)>{{ $c }}</option>@endforeach</select></div>
        </div>
        <div class="row">
            <div><label class="lbl">{{ __('Début') }}</label><input class="inp" type="datetime-local" name="starts_at" value="{{ old('starts_at', optional($event->starts_at)->format('Y-m-d\TH:i')) }}"></div>
            <div><label class="lbl">{{ __('Fin') }}</label><input class="inp" type="datetime-local" name="ends_at" value="{{ old('ends_at', optional($event->ends_at)->format('Y-m-d\TH:i')) }}"></div>
        </div>
        <label class="lbl">{{ __('Lieu') }}</label><input class="inp" name="venue" value="{{ old('venue',$event->venue) }}">
        <label class="lbl">{{ __('Adresse') }}</label><input class="inp" name="address" value="{{ old('address',$event->address) }}">
        <label class="lbl">{{ __('Description') }}</label><textarea class="inp" name="description" rows="3">{{ old('description',$event->description) }}</textarea>
        <label class="lbl">{{ __('Cover') }}</label><input class="inp" type="file" name="cover" accept="image/*">
        @if($editing && $event->cover_url)<img src="{{ $event->cover_url }}" style="height:56px;border-radius:8px;margin-top:8px">@endif
        <label class="lbl">{{ __('Page de paiement (pour billets payants)') }}</label>
        <select class="sel" name="pay_page_id"><option value="">{{ __('— Aucune —') }}</option>@foreach($payPages as $pp)<option value="{{ $pp->id }}" @selected(old('pay_page_id',$event->pay_page_id)==$pp->id)>{{ $pp->title ?: $pp->alias }}</option>@endforeach</select>
        <div style="display:flex;gap:20px;margin-top:8px">
            <label class="switch"><input type="hidden" name="is_free" value="0"><input type="checkbox" name="is_free" value="1" @checked(old('is_free',$event->is_free))> {{ __('Gratuit') }}</label>
            <label class="switch"><input type="hidden" name="is_published" value="0"><input type="checkbox" name="is_published" value="1" @checked(old('is_published',$event->is_published))> {{ __('Publié') }}</label>
        </div>
    </div>

    @if($editing)
    <div class="card">
        <div class="h-row"><h2>{{ __('Types de billets') }}</h2><button type="button" class="btn btn-d btn-sm" onclick="addTT()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button></div>
        <div id="ttlist"></div>
    </div>
    @else
        <p style="color:var(--muted);font-size:14px;margin:10px 0">{{ __('Enregistrez, puis ajoutez les types de billets.') }}</p>
    @endif
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

@if($editing)
<template id="tttpl">
    <div class="ttrow" style="display:flex;gap:8px;align-items:center;margin-bottom:8px">
        <input name="ticket_types[IDX][name]" class="inp" placeholder="{{ __('Nom (VIP…)') }}" style="max-width:160px">
        <input name="ticket_types[IDX][price]" type="number" step="0.01" class="inp" placeholder="{{ __('Prix') }}" style="max-width:110px">
        <input name="ticket_types[IDX][quantity]" type="number" class="inp" placeholder="{{ __('Qté (∞)') }}" style="max-width:110px">
        <label class="switch" style="flex:0"><input type="checkbox" name="ticket_types[IDX][is_active]" value="1" checked></label>
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.ttrow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>
@push('scripts')
<script>
var ttIdx=0;
function addTT(d){var h=document.getElementById('tttpl').innerHTML.replace(/IDX/g,ttIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('ttlist').appendChild(r);
    if(d){r.querySelector('[name$="[name]"]').value=d.name||'';r.querySelector('[name$="[price]"]').value=d.price||'';r.querySelector('[name$="[quantity]"]').value=d.quantity==null?'':d.quantity;r.querySelector('[name$="[is_active]"]').checked=!!d.is_active;var i=document.createElement('input');i.type='hidden';i.name='ticket_types['+ttIdx+'][id]';i.value=d.id;r.appendChild(i);}
    ttIdx++;}
var ex=@json($event->relationLoaded('ticketTypes') ? $event->ticketTypes->map(fn($t)=>['id'=>$t->id,'name'=>$t->name,'price'=>$t->price,'quantity'=>$t->quantity,'is_active'=>$t->is_active]) : []);
if(ex.length){ex.forEach(addTT);}else{addTT();}
</script>
@endpush
@endif
@endsection
