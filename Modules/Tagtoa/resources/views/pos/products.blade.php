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
        {{-- Scanner pour créer : le code inconnu devient un article, et
             l'article se revend ensuite en le scannant. La boucle est fermée
             sans jamais taper un chiffre. --}}
        <button type="button" class="btn btn-o btn-sm" id="scanBtn">
            <i class="fa-solid fa-barcode"></i> {{ __('Scanner un produit') }}
        </button>
        <p id="scanmsg" style="font-size:13px;margin-top:8px"></p>
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
            {{-- Chez qui on rachète cet article : pré-rempli au moment de
                 saisir une réception dans l'écran Stock. --}}
            <label style="font-size:12px;color:var(--muted)">{{ __('Fournisseur') }}
                <select name="products[IDX][supplier_id]" class="inp" style="max-width:170px">
                    <option value="">—</option>
                    @foreach($suppliers as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>
            {{-- Les codes-barres de l'article : n'apparaît qu'une fois
                 l'article enregistré, puisqu'un code se rattache à quelque
                 chose qui existe. --}}
            <a class="btn btn-o btn-sm lienCodes" hidden style="align-self:flex-end">
                <i class="fa-solid fa-barcode"></i> {{ __('Codes-barres') }}
            </a>
            <span class="nouveauCode" style="font-size:12px;color:#1a7a05;font-family:monospace;align-self:flex-end;padding-bottom:8px"></span>
            <span class="marge" style="font-size:12px;color:var(--muted);align-self:flex-end;padding-bottom:8px"></span>
        </div>
    </div>
</template>
{{-- Supprimer est un acte à part, jamais un effet de bord de l'enregistrement. --}}
<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script>
var DEL_URL = "{{ url('/tagtoa/pos/'.$terminal->id.'/products') }}";
var CODES_URL = "{{ route('tagtoa.catalog.codes.index') }}";
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
        champ(r,'supplier_id').value=d.supplier_id||'';
        var i=document.createElement('input');i.type='hidden';i.name='products['+pIdx+'][id]';i.value=d.id;r.appendChild(i);
        var lien=r.querySelector('.lienCodes');
        lien.href=CODES_URL+'?ref=pos:'+d.id;
        lien.hidden=false;}
    r.querySelector('.delrow').addEventListener('click', function(){ delRow(this); });
    r.querySelector('.togdet').addEventListener('click', function(){
        var det = r.querySelector('.pdet'); det.hidden = !det.hidden;
    });
    champ(r,'price').addEventListener('input', function(){ majMarge(r); });
    champ(r,'cost_price').addEventListener('input', function(){ majMarge(r); });
    majMarge(r);
    pIdx++;
    return r;}
/* ------------------------------------------------------------------
   Scanner pour créer un article.

   Seul le patron passe par cet écran : un caissier vend, il ne crée pas
   d'article. C'est pourquoi la création vit ici et pas à la caisse.
   ------------------------------------------------------------------ */
var SCAN_URL = "{{ route('tagtoa.catalog.scan') }}";
var CSRF = "{{ csrf_token() }}";

function direScan(texte, erreur){
    var el = document.getElementById('scanmsg');
    el.textContent = texte;
    el.style.color = erreur ? 'var(--red)' : '#1a7a05';
    clearTimeout(el._t);
    el._t = setTimeout(function(){ el.textContent = ''; }, 6000);
}

/* Article déjà connu : on le montre plutôt que d'en créer un deuxième. Deux
   articles pour le même produit, c'est un stock coupé en deux. */
function surlignerExistant(ref){
    var lien = document.querySelector('.lienCodes[href$="' + ref + '"]');
    var ligne = lien ? lien.closest('.prow') : null;
    if(!ligne) return false;

    ligne.scrollIntoView({behavior:'smooth', block:'center'});
    ligne.style.transition = 'background .4s';
    ligne.style.background = 'rgba(44,184,9,.14)';
    setTimeout(function(){ ligne.style.background = ''; }, 1600);
    return true;
}

function creerDepuisCode(code){
    fetch(SCAN_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:JSON.stringify({code:code})})
      .then(function(r){return r.json();})
      .then(function(d){
          if(d && d.found){
              if(window.TagtoaScanner) TagtoaScanner.close();
              direScan("{{ __('Ce code est déjà celui de : ') }}" + d.article.name);
              surlignerExistant(d.article.ref);
              return;
          }

          // Inconnu : une ligne neuve, le code déjà accroché. Il ne reste
          // qu'à écrire le nom et le prix.
          if(window.TagtoaScanner) TagtoaScanner.close();
          var r = addP();
          var champ = document.createElement('input');
          champ.type = 'hidden';
          champ.name = 'products[' + (pIdx - 1) + '][new_code]';
          champ.value = code;
          r.appendChild(champ);
          r.querySelector('.nouveauCode').textContent = code;
          champ = r.querySelector('[name$="[name]"]');
          r.scrollIntoView({behavior:'smooth', block:'center'});
          champ.focus();
          direScan("{{ __('Nouveau code ') }}" + code + " — " + "{{ __('donnez-lui un nom et un prix, puis enregistrez.') }}");
      })
      .catch(function(){ direScan("{{ __('Vérification impossible. Réessayez.') }}", true); });
}

window.addEventListener('load', function(){
    if(!window.TagtoaScanner){ document.getElementById('scanBtn').disabled = true; return; }

    TagtoaScanner.listenWedge(creerDepuisCode);

    document.getElementById('scanBtn').addEventListener('click', function(){
        TagtoaScanner.open({
            onCode: creerDepuisCode,
            title:  "{{ __('Scanner un produit') }}",
            hint:   "{{ __('Un code inconnu crée une nouvelle ligne. Un code déjà connu vous montre son article.') }}",
            submit: "{{ __('Chercher') }}"
        });
    });
});

@php
    $productData = $terminal->products->map(fn ($p) => [
        'id' => $p->id, 'emoji' => $p->emoji, 'name' => $p->name,
        'price' => $p->price, 'stock' => $p->stock, 'color' => $p->color,
        'is_active' => $p->is_active,
        'cost_price' => $p->cost_price, 'unit' => $p->unit_key,
        'low_stock_threshold' => $p->low_stock_threshold, 'sku' => $p->sku,
        'supplier_id' => $p->supplier_id,
    ])->values();
@endphp
var ex=@json($productData);
if(ex.length){ex.forEach(addP);}else{addP();}
</script>
@endpush
@endsection
