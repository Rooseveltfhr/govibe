@extends('tagtoa::layouts.dashboard')
@php $editing = (bool) $item; @endphp
@section('title', $editing ? __('Modifier le produit') : __('Ajouter un produit'))
@section('page', $editing ? __('Modifier le produit') : __('Ajouter un produit'))

@section('content')
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
        <div id="newCatWrap" style="display:{{ old('category_id', $item?->category_id) ? 'none' : 'flex' }};gap:8px;margin-top:8px">
            <input name="new_category_name" class="inp" placeholder="{{ __('Nom de la nouvelle catégorie') }}" value="{{ old('new_category_name') }}" style="flex:1">
        </div>
        @error('new_category_name')<div class="err">{{ $message }}</div>@enderror

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
                <input name="item_image" type="file" accept="image/*" capture="environment" class="inp" style="max-width:220px">
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

        {{-- Gestion : coût, stock, unité, référence, fournisseur — replié par
             défaut, comme dans l'assistant complet (voir _form-body.blade.php) :
             un commerce qui veut seulement afficher sa carte ne doit pas le subir. --}}
        <button type="button" class="btn btn-o btn-sm" style="margin-top:14px" onclick="var d=document.getElementById('itemAdvanced');d.hidden=!d.hidden">
            <i class="fa-solid fa-sliders"></i> {{ __('Coût & gestion') }}
        </button>
        <div id="itemAdvanced" {{ old('item_cost_price') || old('item_sku') || old('item_stock') ? '' : 'hidden' }} style="display:flex;gap:10px;flex-wrap:wrap;margin-top:10px;padding-top:10px;border-top:1px dashed var(--bd)">
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
@endsection
