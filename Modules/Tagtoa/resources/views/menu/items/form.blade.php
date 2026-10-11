@extends('tagtoa::layouts.dashboard')
@php $editing = (bool) $item; @endphp
@section('title', $editing ? __('Modifier le produit') : __('Ajouter un produit'))
@section('page', $editing ? __('Modifier le produit') : __('Ajouter un produit'))

@section('content')
<style>
    /* L'attribut `hidden` seul ne suffit pas : un style inline `display:flex`
       sur le MÊME élément l'emporterait sur le display:none de la feuille
       UA (même bug que _form-body.blade.php .itemdet, déjà rencontré dans ce
       module). `:not([hidden])` ne s'applique qu'une fois le volet déplié. */
    .item-advanced:not([hidden]) { display: flex; }
</style>
{{-- Écran à part, EXPRÈS : ni le nom de l'établissement, ni son logo, ni ses
     catégories ou autres produits n'apparaissent ici — seulement ce qui
     concerne CE produit. --}}
<div class="h-row">
    <h2>{{ $editing ? __('Modifier le produit') : __('Ajouter un produit') }} — {{ $menu->name }}</h2>
    <a href="{{ route('tagtoa.menu.dashboard.items.index', $menu->id) }}" class="btn btn-o btn-sm"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour aux produits') }}</a>
</div>

<div class="card">
    <form method="POST" action="{{ $editing ? route('tagtoa.menu.dashboard.items.update', [$menu->id, $item->id]) : route('tagtoa.menu.dashboard.items.store', $menu->id) }}" enctype="multipart/form-data">
        @csrf
        @if($editing) @method('PUT') @endif

        <label class="lbl">{{ __('Catégorie') }} *</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <select name="category_id" id="catSelect" class="sel" style="flex:1;min-width:180px" onchange="document.getElementById('newCatWrap').style.display = this.value ? 'none' : 'flex'">
                <option value="">{{ __('— Nouvelle catégorie —') }}</option>
                @foreach($menu->categories as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id', $item?->category_id) == $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div id="newCatWrap" style="display:{{ old('category_id', $item?->category_id) ? 'none' : 'flex' }};flex-direction:column;gap:8px;margin-top:8px">
            <input name="new_category_name" id="newCatName" class="inp" placeholder="{{ __('Nom de la nouvelle catégorie') }}" value="{{ old('new_category_name') }}" style="flex:1">
            {{-- Suggestions propres au métier de ce commerce (« Chambres » pour
                 un hôtel, « Bières » pour un bar…) — jamais imposées : un clic
                 remplit juste le champ texte au-dessus, qu'on peut toujours
                 modifier. Celles déjà créées pour ce menu ne sont pas reproposées. --}}
            @if(!empty($categoryPresets))
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                    @foreach($categoryPresets as $preset)
                        <button type="button" class="btn btn-o btn-sm catpreset" data-nom="{{ $preset }}" style="font-size:12.5px;padding:4px 10px">{{ $preset }}</button>
                    @endforeach
                </div>
            @endif
        </div>
        @error('new_category_name')<div class="err">{{ $message }}</div>@enderror
        <script>
            document.querySelectorAll('.catpreset').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('newCatName').value = btn.dataset.nom;
                });
            });
        </script>

        <label class="lbl" style="margin-top:14px">{{ __('Nom du produit') }} *</label>
        <input name="item_name" class="inp" required maxlength="160" value="{{ old('item_name', $item?->name) }}" placeholder="{{ \Modules\Tagtoa\App\Support\Menu\BusinessProfile::for($menu->type)['noun'] ?? __('Produit') }}">
        @error('item_name')<div class="err">{{ $message }}</div>@enderror

        <label class="lbl" style="margin-top:10px">{{ __('Description (optionnel)') }}</label>
        <input name="item_description" class="inp" maxlength="600" value="{{ old('item_description', $item?->description) }}">

        <div style="display:flex;gap:16px;align-items:center;margin-top:10px;flex-wrap:wrap">
            <div>
                <label class="lbl">{{ __('Prix') }} *</label>
                <input name="item_price" type="number" step="0.01" min="0" required class="inp" style="max-width:150px" value="{{ old('item_price', $item?->price) }}">
                @error('item_price')<div class="err">{{ $message }}</div>@enderror
            </div>
            <div>
                <label class="lbl">{{ __('Photo') }}</label>
                {{-- SANS `capture` : sur téléphone, cet attribut ne propose pas
                     « appareil photo OU galerie » comme on pourrait le croire —
                     il saute la boîte de choix et ouvre l'appareil photo
                     DIRECTEMENT, rendant la galerie inaccessible depuis ce
                     champ. Sans lui, le sélecteur natif du téléphone propose
                     les deux. --}}
                <input name="item_image" type="file" accept="image/*" class="inp" style="max-width:220px">
            </div>
            @if($editing && $item->image_url)
                <label class="switch" style="flex:0"><input type="checkbox" name="item_remove_image" value="1"> {{ __('Retirer la photo actuelle') }}</label>
            @endif
        </div>

        <div style="display:flex;gap:16px;align-items:center;margin-top:14px">
            <label class="switch" style="flex:0">
                <input type="hidden" name="item_is_available" value="0">
                <input type="checkbox" name="item_is_available" value="1" @checked(old('item_is_available', $item->is_available ?? true))> {{ __('Disponible') }}
            </label>
            <label class="switch" style="flex:0">
                <input type="checkbox" name="item_is_featured" value="1" @checked(old('item_is_featured', $item->is_featured ?? false))> {{ __('Mis en avant') }}
            </label>
        </div>

        {{-- Champs propres au métier de CET établissement — le type ne change
             pas depuis cet écran, donc rendu directement, sans JavaScript. --}}
        @if(!empty($fields))
            <div class="specgrid">
                @foreach($fields as $key => $f)
                    @php $v = old('specs.'.$key, $item?->specs[$key] ?? null); @endphp
                    <div class="specfld">
                        <label class="lbl">{{ __($f['label']) }}@if(!empty($f['unit'])) <span style="font-weight:400;color:var(--muted)">({{ $f['unit'] }})</span>@endif</label>
                        @if($f['type'] === 'number')
                            <input class="inp" type="number" step="any" name="specs[{{ $key }}]" @if(isset($f['min'])) min="{{ $f['min'] }}" @endif @if(isset($f['max'])) max="{{ $f['max'] }}" @endif value="{{ $v }}">
                        @elseif($f['type'] === 'select')
                            <select class="sel" name="specs[{{ $key }}]">
                                <option value="">—</option>
                                @foreach($f['options'] as $o)
                                    <option value="{{ $o }}" @selected($v === $o)>{{ $o }}</option>
                                @endforeach
                            </select>
                        @elseif($f['type'] === 'tags')
                            @php $chosen = is_array($v) ? $v : []; @endphp
                            <div class="tagwrap">
                                @foreach($f['options'] as $o)
                                    <label class="tag"><input type="checkbox" name="specs[{{ $key }}][]" value="{{ $o }}" @checked(in_array($o, $chosen, true))><span>{{ $o }}</span></label>
                                @endforeach
                            </div>
                        @elseif($f['type'] === 'bool')
                            <label class="switch"><input type="checkbox" name="specs[{{ $key }}]" value="1" @checked((bool) $v)> {{ __($f['label']) }}</label>
                        @else
                            <input class="inp" name="specs[{{ $key }}]" maxlength="{{ $f['max'] ?? 120 }}" value="{{ $v }}">
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Options & variantes : « Taille », « Suppléments »… Le moteur
             (ItemOption/ItemOptionChoice + DashboardController::syncItemOptions)
             existe déjà et fonctionne dans le grand formulaire — jamais
             affiché sur CET écran jusqu'ici. Replié par défaut, comme
             « Coût & gestion » juste en dessous : la plupart des produits
             n'en ont pas, et ne doivent pas le subir. SANS style inline sur
             l'élément qui porte `hidden` — le bloc reprend son display:block
             normal une fois déplié, pas de conflit de spécificité à gérer. --}}
        <button type="button" class="btn btn-o btn-sm" style="margin-top:14px" onclick="var d=document.getElementById('itemOptions');d.hidden=!d.hidden">
            <i class="fa-solid fa-layer-group"></i> {{ __('Options & variantes (taille, suppléments…)') }}
        </button>
        @php
            // `$item?->options->isEmpty()` serait FAUX ici : l'opérateur
            // nullsafe ne protège que l'accès juste après lui (`->options`),
            // pas toute la chaîne — `->isEmpty()` planterait quand même sur
            // null en création. D'où ce booléen calculé à part.
            $panneauOptionsVide = ($item === null || $item->options->isEmpty()) && ! old('item_options');
        @endphp
        <div id="itemOptions" @if($panneauOptionsVide) hidden @endif style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--bd)">
            <div id="optionsList"></div>
            <button type="button" class="btn btn-o btn-sm" onclick="addOpt()" style="margin-top:6px">
                <i class="fa-solid fa-plus"></i> {{ __('Groupe d\'options (ex. Taille)') }}
            </button>
        </div>

        {{-- Gestion : coût, stock, unité, référence, fournisseur — replié par
             défaut, comme dans l'assistant complet (voir _form-body.blade.php) :
             un commerce qui veut seulement afficher sa carte ne doit pas le subir. --}}
        <button type="button" class="btn btn-o btn-sm" style="margin-top:14px" onclick="var d=document.getElementById('itemAdvanced');d.hidden=!d.hidden">
            <i class="fa-solid fa-sliders"></i> {{ __('Coût & gestion') }}
        </button>
        <div id="itemAdvanced" class="item-advanced" {{ old('item_cost_price') || old('item_sku') || old('item_stock') ? '' : 'hidden' }} style="gap:10px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px dashed var(--bd)">
            <label style="font-size:12px;color:var(--muted)">{{ __('Coût matière') }}
                <input name="item_cost_price" class="inp" type="number" step="0.01" min="0" value="{{ old('item_cost_price', $item?->cost_price) }}" style="max-width:130px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Unité') }}
                <select name="item_unit" class="inp" style="max-width:130px">
                    @foreach($units as $cle => $u)
                        <option value="{{ $cle }}" @selected(old('item_unit', $item?->unit) === $cle)>{{ __($u['label']) }}</option>
                    @endforeach
                </select>
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Stock (vide = illimité)') }}
                <input name="item_stock" class="inp" type="number" step="0.001" min="0" value="{{ old('item_stock', $item?->stock) }}" style="max-width:140px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Alerte sous') }}
                <input name="item_low_stock_threshold" class="inp" type="number" step="0.001" min="0" value="{{ old('item_low_stock_threshold', $item?->low_stock_threshold) }}" style="max-width:110px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Référence (SKU)') }}
                <input name="item_sku" class="inp" maxlength="60" value="{{ old('item_sku', $item?->sku) }}" style="max-width:140px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Fournisseur') }}
                <select name="item_supplier_id" class="inp" style="max-width:170px">
                    <option value="">—</option>
                    @foreach($suppliers as $f)
                        <option value="{{ $f->id }}" @selected(old('item_supplier_id', $item?->supplier_id) == $f->id)>{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div style="margin-top:18px">
            <button type="submit" class="btn btn-p"><i class="fa-solid fa-check"></i> {{ $editing ? __('Enregistrer les modifications') : __('Ajouter le produit') }}</button>
        </div>
    </form>
</div>

{{-- Template groupe d'options — mêmes noms de champs et même structure que
     le grand formulaire (_form-body.blade.php), sans les indices CIDX/IIDX
     puisque cet écran ne porte toujours qu'UN seul produit. --}}
<template id="opttpl">
    <div class="optblock" data-chi="0" style="border:1px dashed var(--bd);border-radius:10px;padding:8px;margin-top:8px;background:#fafafa">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input name="item_options[OIDX][name]" class="inp" placeholder="{{ __('Nom du groupe (ex. Taille)') }}" style="flex:1;min-width:140px">
            <label class="switch" style="flex:0"><input type="checkbox" name="item_options[OIDX][required]" value="1"> {{ __('Obligatoire') }}</label>
            <label class="switch" style="flex:0"><input type="checkbox" name="item_options[OIDX][multiple]" value="1"> {{ __('Choix multiples') }}</label>
            <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.optblock').remove()"><i class="fa-solid fa-trash"></i></button>
        </div>
        <div class="choices" style="margin-top:6px"></div>
        <button type="button" class="btn btn-o btn-sm" onclick="addChoiceRow(this.closest('.optblock'))" style="margin-top:4px"><i class="fa-solid fa-plus"></i> {{ __('Choix') }}</button>
    </div>
</template>

<template id="choicetpl">
    <div class="choicerow" style="display:flex;gap:8px;align-items:center;margin-top:4px">
        <input name="item_options[OIDX][choices][CHIDX][label]" class="inp" placeholder="{{ __('Libellé (ex. Grand)') }}">
        <input name="item_options[OIDX][choices][CHIDX][price_delta]" class="inp" type="number" step="0.01" placeholder="{{ __('+/- prix') }}" style="max-width:110px">
        <button type="button" class="btn btn-o btn-sm" style="flex:0;color:var(--red)" onclick="this.closest('.choicerow').remove()"><i class="fa-solid fa-trash"></i></button>
    </div>
</template>

<script>
(function () {
    var oiCounter = 0;

    window.addOpt = function (d) {
        var oi = oiCounter++;
        var html = document.getElementById('opttpl').innerHTML.replace(/OIDX/g, oi);
        var box = document.createElement('div'); box.innerHTML = html;
        var row = box.firstElementChild;
        document.getElementById('optionsList').appendChild(row);
        if (d) {
            row.querySelector('[name$="[name]"]').value = d.name || '';
            row.querySelector('[name$="[required]"]').checked = !!d.required;
            row.querySelector('[name$="[multiple]"]').checked = !!d.multiple;
            if (d.id) {
                var h = document.createElement('input'); h.type = 'hidden'; h.name = 'item_options[' + oi + '][id]'; h.value = d.id; row.appendChild(h);
            }
            (d.choices || []).forEach(function (ch) { addChoiceRow(row, ch); });
        }
        return row;
    };

    window.addChoiceRow = function (optRow, d) {
        var oiMatch = optRow.querySelector('[name$="[name]"]').name.match(/item_options\[(\d+)\]/);
        var oi = oiMatch[1];
        var chi = parseInt(optRow.getAttribute('data-chi') || '0', 10);
        optRow.setAttribute('data-chi', chi + 1);
        var html = document.getElementById('choicetpl').innerHTML.replace(/OIDX/g, oi).replace(/CHIDX/g, chi);
        var box = document.createElement('div'); box.innerHTML = html;
        var row = box.firstElementChild;
        optRow.querySelector('.choices').appendChild(row);
        if (d) {
            row.querySelector('[name$="[label]"]').value = d.label || '';
            row.querySelector('[name$="[price_delta]"]').value = (d.price_delta != null ? d.price_delta : '');
            if (d.id) {
                var h = document.createElement('input'); h.type = 'hidden'; h.name = 'item_options[' + oi + '][choices][' + chi + '][id]'; h.value = d.id; row.appendChild(h);
            }
        }
        return row;
    };

    @php
        // Même piège que $panneauOptionsVide plus haut : `$item?->options->map`
        // plante sur null dès que `$item` est null (création), le nullsafe ne
        // protégeant que `->options`. D'où le `$item === null ? [] : …` ici.
        $optionsExistantes = $item === null ? [] : $item->options->map(fn ($o) => [
            'id' => $o->id, 'name' => $o->name, 'required' => $o->required, 'multiple' => $o->multiple,
            'choices' => $o->choices->map(fn ($ch) => ['id' => $ch->id, 'label' => $ch->label, 'price_delta' => $ch->price_delta])->values(),
        ])->values();
    @endphp
    var existingOptions = @json(old('item_options') ?: $optionsExistantes);
    existingOptions.forEach(function (o) { addOpt(o); });
})();
</script>
@endsection
