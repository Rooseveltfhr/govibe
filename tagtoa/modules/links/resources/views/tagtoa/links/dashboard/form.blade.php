{{-- TAGTOA LINKS — Dashboard : créer / modifier une page + ses liens.
     ADAPTER @extends au layout admin du projet (Bootstrap). --}}
@extends('layouts.app')

@section('content')
@php $editing = $page->exists; @endphp
<div class="container py-4" style="max-width:760px">
    <a href="{{ route('tagtoa.links.dashboard.index') }}" class="text-decoration-none small">
        <i class="fa-solid fa-arrow-left me-1"></i>{{ __('Retour') }}
    </a>
    <h4 class="my-3 fw-bold" style="font-family:'Space Grotesk',sans-serif">
        {{ $editing ? __('Modifier la page LINKS') : __('Nouvelle page LINKS') }}
    </h4>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('tagtoa.links.dashboard.update', $page->id) : route('tagtoa.links.dashboard.store') }}">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card border-0 shadow-sm mb-3" style="border-radius:16px">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label small fw-bold">{{ __('Titre') }}</label>
                        <input name="title" class="form-control" value="{{ old('title', $page->title) }}" placeholder="{{ __('Mon nom / marque') }}">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label small fw-bold">{{ __('Thème') }}</label>
                        <select name="theme" class="form-select">
                            @foreach(['dark'=>'Noir','light'=>'Blanc','blue'=>'Bleu'] as $k=>$v)
                                <option value="{{ $k }}" @selected(old('theme', $page->theme ?: 'dark') == $k)>{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label small fw-bold">{{ __('Alias (URL)') }}</label>
                        <div class="input-group">
                            <span class="input-group-text">tagtoa.com/links/</span>
                            <input name="alias" class="form-control" value="{{ old('alias', $page->alias) }}" placeholder="{{ __('auto si vide') }}">
                        </div>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label small fw-bold">{{ __('Vcard (optionnel)') }}</label>
                        <select name="vcard_id" class="form-select">
                            <option value="">{{ __('— Aucun —') }}</option>
                            @foreach($vcards as $v)
                                <option value="{{ $v->id }}" @selected(old('vcard_id', $page->vcard_id) == $v->id)>{{ $v->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Bio') }}</label>
                    <textarea name="bio" class="form-control" rows="2" maxlength="500">{{ old('bio', $page->bio) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold">{{ __('Avatar') }}</label>
                    <input type="file" name="avatar" accept="image/*" class="form-control">
                    @if($editing && $page->avatar_url)<img src="{{ $page->avatar_url }}" class="mt-2 rounded-circle" style="height:48px">@endif
                </div>
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label small fw-bold">{{ __('Page de don (TAGTOA PAY)') }}</label>
                        <select name="pay_page_id" class="form-select">
                            <option value="">{{ __('— Aucune —') }}</option>
                            @foreach($payPages as $pp)
                                <option value="{{ $pp->id }}" @selected(old('pay_page_id', $page->pay_page_id) == $pp->id)>{{ $pp->title ?: $pp->alias }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label small fw-bold">{{ __('Texte du don') }}</label>
                        <input name="donation_label" class="form-control" value="{{ old('donation_label', $page->donation_label) }}" placeholder="{{ __('Faire un don') }}">
                    </div>
                </div>
                <div class="form-check form-switch">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $page->is_active ?? true))>
                    <label class="form-check-label" for="act">{{ __('Page active') }}</label>
                </div>
            </div>
        </div>

        {{-- Liens --}}
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h6 class="fw-bold mb-0">{{ __('Liens') }}</h6>
            <button type="button" class="btn btn-sm btn-dark" onclick="tlAdd()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
        </div>
        <p class="text-muted small">{{ __('Le logo est détecté automatiquement depuis l\'URL (Facebook, Instagram, TikTok…).') }}</p>
        <div id="links-list"></div>

        <button type="submit" class="btn btn-primary w-100 mt-3" style="background:#0055FF;border:0;padding:12px">
            <i class="fa-solid fa-floppy-disk me-1"></i> {{ __('Enregistrer') }}
        </button>
    </form>
</div>

<template id="link-tpl">
    <div class="card border-0 shadow-sm mb-2 link-row" style="border-radius:12px">
        <div class="card-body py-2">
            <div class="d-flex gap-2 align-items-center">
                <i class="fa-solid fa-grip-vertical text-muted"></i>
                <input name="links[IDX][label]" class="form-control form-control-sm" placeholder="{{ __('Titre du lien') }}" style="max-width:200px">
                <input name="links[IDX][url]" class="form-control form-control-sm" placeholder="https://...">
                <div class="form-check ms-1" title="{{ __('Mettre en avant') }}">
                    <input class="form-check-input" type="checkbox" name="links[IDX][is_featured]" value="1">
                    <label class="form-check-label small">{{ __('Top') }}</label>
                </div>
                <input type="hidden" name="links[IDX][is_active]" value="1">
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.link-row').remove()"><i class="fa-solid fa-trash"></i></button>
            </div>
        </div>
    </div>
</template>

<script>
var tlIdx = 0;
function tlAdd(data){
    var html = document.getElementById('link-tpl').innerHTML.replace(/IDX/g, tlIdx);
    var div = document.createElement('div'); div.innerHTML = html;
    var row = div.firstElementChild;
    document.getElementById('links-list').appendChild(row);
    if(data){
        row.querySelector('[name$="[label]"]').value = data.label || '';
        row.querySelector('[name$="[url]"]').value = data.url || '';
        row.querySelector('[name$="[is_featured]"]').checked = !!data.is_featured;
        var hid=document.createElement('input'); hid.type='hidden';
        hid.name='links['+tlIdx+'][id]'; hid.value=data.id; row.appendChild(hid);
    }
    tlIdx++;
}
var existing = @json($page->relationLoaded('links') ? $page->links->map(fn($l)=>[
    'id'=>$l->id,'label'=>$l->label,'url'=>$l->url,'is_featured'=>$l->is_featured,
]) : []);
if(existing.length){ existing.forEach(tlAdd); } else { tlAdd(); }
</script>
@endsection
