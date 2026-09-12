@extends('tagtoa::layouts.dashboard')
@section('title', __('Produits'))
@section('page', $terminal->name.' — '.__('Produits'))

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.pos.register',$terminal->id) }}" class="btn btn-d btn-sm"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir caisse') }}</a>
</div>
<form method="POST" action="{{ route('tagtoa.pos.products.save',$terminal->id) }}">
    @csrf
    <div class="card">
        <p style="color:var(--muted);font-size:13px;margin:-4px 0 10px">
            <i class="fa-solid fa-circle-info"></i>
            {{ __('Ce catalogue est celui du commerce : toutes vos caisses y vendent les mêmes articles.') }}
            {{ __('Enregistrer ne supprime jamais un article — décochez pour le retirer de la vente, ou utilisez la corbeille.') }}
        </p>
        <button type="button" class="btn btn-d btn-sm" onclick="addP()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter un produit') }}</button>
        <div id="plist" style="margin-top:12px"></div>
    </div>
    <button class="btn btn-p"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
</form>

<template id="ptpl">
    <div class="prow" style="border:1px solid var(--bd);border-radius:10px;padding:8px;margin-bottom:8px">
        {{-- Ligne rapide : ce qu'il faut pour vendre. --}}
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <input name="products[IDX][emoji]" class="inp" placeholder="🍔" style="max-width:64px">
            <input name="products[IDX][name]" class="inp" placeholder="{{ __('Nom') }}" style="max-width:170px">
            <input name="products[IDX][price]" type="number" step="0.01" min="0" class="inp" placeholder="{{ __('Prix vente') }}" style="max-width:110px">
            {{-- Stock décimal : 2,5 livres de riz est une quantité réelle. --}}
            <input name="products[IDX][stock]" type="number" step="0.001" class="inp" placeholder="{{ __('Stock') }}" style="max-width:90px">
            <input name="products[IDX][color]" type="color" value="#2cb809" style="width:42px;height:42px;border:1px solid var(--bd);border-radius:8px">
            <label class="switch" style="flex:0"><input type="checkbox" name="products[IDX][is_active]" value="1" checked></label>
            <button type="button" class="btn btn-o btn-sm togdet" style="flex:0"
                    title="{{ __('Prix d\'achat, unité, seuil') }}"><i class="fa-solid fa-sliders"></i></button>
            <button type="button" class="btn btn-o btn-sm delrow" style="flex:0;color:var(--red)"
                    title="{{ __('Supprimer du catalogue') }}"><i class="fa-solid fa-trash"></i></button>
        </div>

        {{-- Volet gestion : replié par défaut. Un marchand qui veut seulement
             une grille de boutons ne doit pas le subir ; celui qui veut savoir
             ce qu'il gagne le déplie une fois et n'y revient plus. --}}
        <div class="pdet" hidden style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;padding-top:8px;border-top:1px dashed var(--bd)">
            <label style="font-size:12px;color:var(--muted)">{{ __('Prix d\'achat') }}
                <input name="products[IDX][cost_price]" type="number" step="0.01" min="0" class="inp" placeholder="{{ __('non renseigné') }}" style="max-width:120px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Unité') }}
                <select name="products[IDX][unit]" class="inp" style="max-width:130px">
                    @foreach (\Modules\Tagtoa\App\Support\Catalog\Pricing::UNITS as $cle => $u)
                        <option value="{{ $cle }}">{{ __($u['label']) }}</option>
                    @endforeach
                </select>
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Alerte sous') }}
                <input name="products[IDX][low_stock_threshold]" type="number" step="0.001" min="0" class="inp" placeholder="5" style="max-width:100px">
            </label>
            <label style="font-size:12px;color:var(--muted)">{{ __('Référence (SKU)') }}
                <input name="products[IDX][sku]" class="inp" maxlength="60" style="max-width:140px">
            </label>
            <span class="marge" style="font-size:12px;color:var(--muted);align-self:flex-end;padding-bottom:8px"></span>
        </div>
    </div>
</template>
{{-- Supprimer est un acte à part, jamais un effet de bord de l'enregistrement. --}}
<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>

@push('scripts')
<script>
var DEL_URL = "{{ url('/tagtoa/pos/'.$terminal->id.'/products') }}";
var pIdx=0;

/* Ligne jamais enregistrée → on l'enlève de l'écran.
   Article déjà au catalogue → suppression serveur, confirmée. */
function delRow(btn){
    var row = btn.closest('.prow');
    var id  = row.querySelector('input[name$="[id]"]');
    var nom = (row.querySelector('[name$="[name]"]').value || '').trim();

    if (!id || !id.value){ row.remove(); return; }

    if (!confirm("{{ __('Supprimer définitivement cet article du catalogue de toutes vos caisses ?') }}\n\n" + nom)) return;

    var f = document.getElementById('delform');
    f.action = DEL_URL + '/' + id.value;
    f.submit();
}
function champ(row, nom){ return row.querySelector('[name$="[' + nom + ']"]'); }

/* Ce qui reste sur une unité vendue, affiché pendant la saisie.
   C'est la seule raison pour laquelle un marchand prend le temps de remplir
   son prix d'achat : il voit tout de suite ce que l'article lui rapporte. */
function majMarge(row){
    var vente  = parseFloat(champ(row,'price').value);
    var achat  = parseFloat(champ(row,'cost_price').value);
    var cible  = row.querySelector('.marge');
    if (!cible) return;

    if (isNaN(achat) || isNaN(vente) || vente <= 0){ cible.textContent=''; return; }

    var marge = vente - achat;
    var pct   = Math.round(marge / vente * 1000) / 10;
    cible.textContent = (marge < 0 ? "{{ __('À PERTE') }} " : "{{ __('Marge') }} ") + marge.toFixed(2) + ' (' + pct + '%)';
    cible.style.color = marge < 0 ? 'var(--red)' : 'var(--muted)';
}

function addP(d){var h=document.getElementById('ptpl').innerHTML.replace(/IDX/g,pIdx),x=document.createElement('div');x.innerHTML=h;var r=x.firstElementChild;document.getElementById('plist').appendChild(r);
    if(d){champ(r,'emoji').value=d.emoji||'';champ(r,'name').value=d.name||'';champ(r,'price').value=d.price||'';champ(r,'stock').value=d.stock==null?'':d.stock;champ(r,'color').value=d.color||'#2cb809';champ(r,'is_active').checked=!!d.is_active;
        champ(r,'cost_price').value=d.cost_price==null?'':d.cost_price;
        champ(r,'unit').value=d.unit||'piece';
        champ(r,'low_stock_threshold').value=d.low_stock_threshold==null?'':d.low_stock_threshold;
        champ(r,'sku').value=d.sku||'';
        var i=document.createElement('input');i.type='hidden';i.name='products['+pIdx+'][id]';i.value=d.id;r.appendChild(i);}
    r.querySelector('.delrow').addEventListener('click', function(){ delRow(this); });
    r.querySelector('.togdet').addEventListener('click', function(){
        var det = r.querySelector('.pdet'); det.hidden = !det.hidden;
    });
    champ(r,'price').addEventListener('input', function(){ majMarge(r); });
    champ(r,'cost_price').addEventListener('input', function(){ majMarge(r); });
    majMarge(r);
    pIdx++;}
@php
    $productData = $terminal->products->map(fn ($p) => [
        'id' => $p->id, 'emoji' => $p->emoji, 'name' => $p->name,
        'price' => $p->price, 'stock' => $p->stock, 'color' => $p->color,
        'is_active' => $p->is_active,
        'cost_price' => $p->cost_price, 'unit' => $p->unit_key,
        'low_stock_threshold' => $p->low_stock_threshold, 'sku' => $p->sku,
    ])->values();
@endphp
var ex=@json($productData);
if(ex.length){ex.forEach(addP);}else{addP();}
</script>
@endpush
@endsection
