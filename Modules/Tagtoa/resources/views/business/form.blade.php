@extends('tagtoa::layouts.dashboard')
@php $editing = $business->exists; @endphp
@section('title', $editing ? __('Mon commerce') : __('Votre commerce'))
@section('page', $editing ? __('Mon commerce') : ($premier ? __('Bienvenue sur TAGTOA') : __('Nouveau commerce')))

@section('content')
@if($premier)
    <div class="card" style="background:var(--blk);color:#fff;border:0;margin-bottom:18px">
        <b style="font-family:var(--ft,var(--fh));font-size:21px;font-weight:400">{{ __('Parlez-nous de votre commerce') }}</b>
        <p style="opacity:.82;font-size:14.5px;margin-top:7px;max-width:62ch">
            {{ __('Une minute, une seule fois. Ensuite, tout TAGTOA s\'adapte à votre métier : votre menu, votre caisse, vos liens de paiement.') }}
        </p>
    </div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $editing ? route('tagtoa.business.update',$business->id) : route('tagtoa.business.store') }}">
    @csrf @if($editing) @method('PUT') @endif

    <div class="card">
        <label class="lbl" style="margin-top:0">{{ __('Nom du commerce') }} *</label>
        <input class="inp" name="name" maxlength="160" required
               value="{{ old('name', $business->name) }}" placeholder="{{ __('Ex. Boulangerie Delmas 31') }}">

        <label class="lbl">{{ __('Type de commerce') }} *</label>
        <select class="sel" name="type" id="btype">
            @foreach($types as $cle => $meta)
                <option value="{{ $cle }}" @selected(old('type', $business->type) === $cle)>{{ __($meta['label']) }}</option>
            @endforeach
        </select>
        <p style="color:var(--muted);font-size:13px;margin-top:6px">
            {{ __('Le type décide du vocabulaire et des champs : un hôtel décrit des chambres, un bar des boissons.') }}
        </p>

        <label class="lbl">{{ __('Ce que vous vendez') }}</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">
            <label class="sells">
                <input type="hidden" name="sells_products" value="0">
                <input type="checkbox" name="sells_products" value="1"
                       @checked(old('sells_products', $business->sells_products ?? true))>
                <span><i class="fa-solid fa-box"></i> {{ __('Des produits') }}</span>
            </label>
            <label class="sells">
                <input type="hidden" name="sells_services" value="0">
                <input type="checkbox" name="sells_services" value="1"
                       @checked(old('sells_services', $business->sells_services ?? false))>
                <span><i class="fa-solid fa-hand-holding-heart"></i> {{ __('Des services') }}</span>
            </label>
        </div>
        <p style="color:var(--muted);font-size:13px;margin-top:6px">
            {{ __('Les deux sont possibles : un hôtel vend des nuits et des boissons.') }}
        </p>
    </div>

    <div class="card">
        <div class="h-row"><h2>{{ __('Vos catégories') }}</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-top:-8px">
            {{ __('Proposées d\'après votre métier. Cochez, décochez, ou ajoutez les vôtres.') }}
        </p>
        <div id="cats" style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:10px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <input class="inp" id="newcat" maxlength="60" placeholder="{{ __('Ajouter une catégorie') }}" style="max-width:240px">
            <button type="button" class="btn btn-o btn-sm" onclick="addCat()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
        </div>
    </div>

    <div class="card">
        <div class="h-row"><h2>{{ __('Coordonnées') }}</h2></div>
        <div class="row">
            <div>
                <label class="lbl">{{ __('Adresse') }}</label>
                <input class="inp" name="address" maxlength="255"
                       value="{{ old('address', $business->address) }}" placeholder="{{ __('Rue, ville') }}">
            </div>
            <div>
                <label class="lbl">{{ __('Téléphone') }}</label>
                <input class="inp" name="phone" maxlength="40" inputmode="tel"
                       value="{{ old('phone', $business->phone) }}" placeholder="+509…">
            </div>
        </div>

        <div class="row" style="margin-top:8px">
            <div>
                <label class="lbl">{{ __('Devise') }} *</label>
                <input class="inp" name="currency" list="devises" maxlength="10" required
                       value="{{ old('currency', $business->currency ?: 'HTG') }}"
                       style="text-transform:uppercase">
                <datalist id="devises">
                    @foreach($devises as $d)<option value="{{ $d }}">@endforeach
                </datalist>
                <p style="color:var(--muted);font-size:12.5px;margin-top:5px">
                    {{ __('HTG, USD, ou la vôtre si elle n\'est pas dans la liste.') }}
                </p>
            </div>
            <div>
                <label class="lbl">{{ __('Logo') }} <span style="font-weight:400;color:var(--muted)">({{ __('image, max 2 Mo') }})</span></label>
                <input class="inp" type="file" name="logo" accept="image/*">
                @if($business->logo_url)
                    <img src="{{ $business->logo_url }}" alt="" style="height:44px;border-radius:9px;margin-top:8px">
                @endif
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="card" style="border-left:4px solid var(--red)">
            @foreach($errors->all() as $e)<div style="color:var(--red);font-size:13.5px">{{ $e }}</div>@endforeach
        </div>
    @endif

    <button class="btn btn-p" style="margin-top:4px">
        <i class="fa-solid {{ $editing ? 'fa-floppy-disk' : 'fa-check' }}"></i>
        {{ $editing ? __('Enregistrer') : __('Créer mon commerce') }}
    </button>
</form>

<style>
    .sells input{position:absolute;opacity:0;width:0;height:0}
    .sells span{display:inline-flex;align-items:center;gap:8px;border:1.5px solid var(--bd);
                border-radius:11px;padding:11px 16px;cursor:pointer;transition:.15s;font-size:14.5px}
    .sells input:checked + span{border-color:#2cb809;background:rgba(44,184,9,.08);font-weight:600}
    .sells input:focus-visible + span{outline:2px solid #2cb809;outline-offset:2px}
    .catchip{display:inline-flex;align-items:center;gap:7px;border:1.5px solid var(--bd);
             border-radius:999px;padding:6px 12px;font-size:13px;cursor:pointer;user-select:none}
    .catchip.on{border-color:#2cb809;background:rgba(44,184,9,.08);font-weight:600}
    .catchip b{color:var(--red);font-weight:700}
</style>

@push('scripts')
<script>
/* Catégories : proposées d'après le métier, choisies par le marchand.
   Les profils viennent du serveur — le navigateur ne fait que les rendre. */
var PROFILS = @json(collect($suggestions)->map(fn ($p) => $p['categories'] ?? [])),
    CHOISIES = @json(old('categories', $business->categories ?? [])) || [];

function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

function renderCats(){
    var type = document.getElementById('btype').value,
        proposees = PROFILS[type] || [],
        toutes = proposees.slice();

    CHOISIES.forEach(function(c){ if(toutes.indexOf(c) === -1) toutes.push(c); });

    var box = document.getElementById('cats');
    box.innerHTML = '';
    toutes.forEach(function(nom){
        var on = CHOISIES.indexOf(nom) !== -1,
            el = document.createElement('label');
        el.className = 'catchip' + (on ? ' on' : '');
        el.innerHTML = '<input type="checkbox" name="categories[]" value="' + esc(nom) + '"'
                     + (on ? ' checked' : '') + ' style="position:absolute;opacity:0;width:0">'
                     + esc(nom);
        el.querySelector('input').addEventListener('change', function(){
            el.classList.toggle('on', this.checked);
            var i = CHOISIES.indexOf(nom);
            this.checked ? (i === -1 && CHOISIES.push(nom)) : (i !== -1 && CHOISIES.splice(i, 1));
        });
        box.appendChild(el);
    });
}

function addCat(){
    var champ = document.getElementById('newcat'), nom = champ.value.trim();
    if(!nom || CHOISIES.indexOf(nom) !== -1){ champ.value = ''; return; }
    CHOISIES.push(nom);
    champ.value = '';
    renderCats();
}

document.getElementById('newcat').addEventListener('keydown', function(e){
    if(e.key === 'Enter'){ e.preventDefault(); addCat(); }
});
document.getElementById('btype').addEventListener('change', renderCats);
renderCats();
</script>
@endpush
@endsection
