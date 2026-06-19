{{-- TAGTOA EVENT — Dashboard créer/modifier + types de billets. ADAPTER @extends. --}}
@extends('layouts.app')
@section('content')
@php $editing = $event->exists; @endphp
<div class="container py-4" style="max-width:780px">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" class="text-decoration-none small"><i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}</a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">{{ $editing ? __('Modifier l\'événement') : __('Nouvel événement') }}</h4>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('tagtoa.event.dashboard.update',$event->id) : route('tagtoa.event.dashboard.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="card border-0 shadow-sm mb-3" style="border-radius:16px"><div class="card-body">
            <div class="mb-3"><label class="form-label small fw-bold">{{ __('Titre') }} *</label>
                <input name="title" class="form-control" required value="{{ old('title',$event->title) }}"></div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label small fw-bold">{{ __('Type') }}</label>
                    <select name="type" class="form-select">
                        @foreach(['concert','expo','mariage','sport','conference','autre'] as $t)
                            <option value="{{ $t }}" @selected(old('type',$event->type)===$t)>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select></div>
                <div class="col-md-6 mb-3"><label class="form-label small fw-bold">{{ __('Devise') }}</label>
                    <select name="currency" class="form-select">@foreach(['HTG','USD'] as $c)<option @selected(old('currency',$event->currency ?: 'HTG')===$c)>{{ $c }}</option>@endforeach</select></div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label small fw-bold">{{ __('Début') }}</label>
                    <input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', optional($event->starts_at)->format('Y-m-d\TH:i')) }}"></div>
                <div class="col-md-6 mb-3"><label class="form-label small fw-bold">{{ __('Fin') }}</label>
                    <input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', optional($event->ends_at)->format('Y-m-d\TH:i')) }}"></div>
            </div>
            <div class="mb-3"><label class="form-label small fw-bold">{{ __('Lieu') }}</label>
                <input name="venue" class="form-control" value="{{ old('venue',$event->venue) }}"></div>
            <div class="mb-3"><label class="form-label small fw-bold">{{ __('Adresse') }}</label>
                <input name="address" class="form-control" value="{{ old('address',$event->address) }}"></div>
            <div class="mb-3"><label class="form-label small fw-bold">{{ __('Description') }}</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description',$event->description) }}</textarea></div>
            <div class="mb-3"><label class="form-label small fw-bold">{{ __('Cover') }}</label>
                <input type="file" name="cover" accept="image/*" class="form-control">
                @if($editing && $event->cover_url)<img src="{{ $event->cover_url }}" class="mt-2 rounded" style="height:60px">@endif</div>
            <div class="mb-2"><label class="form-label small fw-bold">{{ __('Page de paiement (TAGTOA PAY)') }}</label>
                <input type="number" name="pay_page_id" class="form-control" value="{{ old('pay_page_id',$event->pay_page_id) }}" placeholder="ID page PAY (optionnel)"></div>
            <div class="form-check form-switch"><input type="hidden" name="is_free" value="0"><input class="form-check-input" type="checkbox" name="is_free" value="1" id="free" @checked(old('is_free',$event->is_free))><label class="form-check-label" for="free">{{ __('Événement gratuit') }}</label></div>
            <div class="form-check form-switch"><input type="hidden" name="is_published" value="0"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" @checked(old('is_published',$event->is_published))><label class="form-check-label" for="pub">{{ __('Publié') }}</label></div>
        </div></div>

        @if($editing)
        <h6 class="fw-bold mb-2">{{ __('Types de billets') }} <button type="button" class="btn btn-sm btn-dark ms-2" onclick="addTT()"><i class="fa-solid fa-plus"></i></button></h6>
        <div id="tt-list"></div>
        @else
        <p class="text-muted small">{{ __('Enregistrez d\'abord, puis ajoutez les types de billets.') }}</p>
        @endif

        <button class="btn btn-primary w-100 mt-3" style="background:#0055FF;border:0;padding:12px"><i class="fa-solid fa-floppy-disk me-1"></i> {{ __('Enregistrer') }}</button>
    </form>
</div>

@if($editing)
<template id="tt-tpl">
    <div class="card border-0 shadow-sm mb-2 tt-row" style="border-radius:12px"><div class="card-body py-2 d-flex gap-2 align-items-center">
        <input name="ticket_types[IDX][name]" class="form-control form-control-sm" placeholder="{{ __('Nom (VIP…)') }}" style="max-width:160px">
        <input name="ticket_types[IDX][price]" type="number" step="0.01" class="form-control form-control-sm" placeholder="{{ __('Prix') }}" style="max-width:110px">
        <input name="ticket_types[IDX][quantity]" type="number" class="form-control form-control-sm" placeholder="{{ __('Qté (vide=∞)') }}" style="max-width:120px">
        <div class="form-check"><input class="form-check-input" type="checkbox" name="ticket_types[IDX][is_active]" value="1" checked><label class="form-check-label small">{{ __('Actif') }}</label></div>
        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="this.closest('.tt-row').remove()"><i class="fa-solid fa-trash"></i></button>
    </div></div>
</template>
<script>
var ttIdx=0;
function addTT(d){
    var h=document.getElementById('tt-tpl').innerHTML.replace(/IDX/g,ttIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;
    document.getElementById('tt-list').appendChild(r);
    if(d){r.querySelector('[name$="[name]"]').value=d.name||'';r.querySelector('[name$="[price]"]').value=d.price||'';r.querySelector('[name$="[quantity]"]').value=d.quantity==null?'':d.quantity;r.querySelector('[name$="[is_active]"]').checked=!!d.is_active;
        var i=document.createElement('input');i.type='hidden';i.name='ticket_types['+ttIdx+'][id]';i.value=d.id;r.appendChild(i);}
    ttIdx++;
}
var ex=@json($event->relationLoaded('ticketTypes') ? $event->ticketTypes->map(fn($t)=>['id'=>$t->id,'name'=>$t->name,'price'=>$t->price,'quantity'=>$t->quantity,'is_active'=>$t->is_active]) : []);
if(ex.length){ex.forEach(addTT);}else{addTT();}
</script>
@endif
@endsection
