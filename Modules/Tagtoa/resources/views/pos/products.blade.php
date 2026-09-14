@extends('tagtoa::layouts.dashboard')
@section('title', __('Produits'))
@section('page', $terminal->name.' — '.__('Produits'))

@push('head')
<style>
/* Saisie DENSE. Le formulaire d'origine posait un cadre autour de chaque champ
   et une étiquette au-dessus : sur un téléphone, « Prix d'achat », « Unité » et
   « Alerte sous » prenaient chacun une ligne entière pour trois caractères.
   Ici les champs se rangent en grille et remplissent la largeur disponible. */
/* [hidden] AVANT tout le reste : une règle d'affichage explicite (display:grid
   ici) l'emporte sur le display:none que le navigateur applique à l'attribut.
   Sans cette ligne, les volets « plus de détails » s'affichent tous, dépliés,
   dès l'ouverture — exactement ce que ce volet devait éviter. */
[hidden]{display:none!important}
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px 7px;align-items:end}
.pf .w2{grid-column:span 2}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;
          text-transform:uppercase;letter-spacing:.04em}
/* Champ compact : moins de rembourrage, moins de rayon, une seule bordure. */
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;
    font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
select.ic{padding:8px 8px}
/* La vignette : photo si elle existe, sinon emoji sur la couleur du bouton.
   Cliquer dessus ouvre la galerie — pas de champ « choisir un fichier » qui
   prendrait une ligne pour lui seul. */
.vig{position:relative;width:56px;height:56px;border-radius:11px;overflow:hidden;flex:0 0 auto;
     border:1.5px solid var(--bd);display:flex;align-items:center;justify-content:center;
     font-size:24px;cursor:pointer;background:#fafafa}
.vig img{width:100%;height:100%;object-fit:cover}
.vig input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.vig .cam{position:absolute;right:2px;bottom:2px;background:rgba(0,0,0,.6);color:#fff;
          border-radius:6px;font-size:9px;padding:1px 4px;pointer-events:none}
/* Une ligne d'article : un filet de séparation, pas un cadre. */
.art{display:flex;gap:10px;align-items:flex-start;padding:12px 0;border-top:1px solid var(--bd)}
.art:first-child{border-top:0}
.art .corps{flex:1;min-width:0}
.art .act{display:flex;gap:4px;flex:0 0 auto}
.ib{background:none;border:0;cursor:pointer;color:var(--muted);padding:7px 8px;border-radius:8px;font-size:14px}
.ib:hover{background:rgba(0,0,0,.05);color:var(--blk)}
.ib.rouge:hover{background:#fdecea;color:var(--red)}
.chk{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);color:var(--muted);white-space:nowrap}
/* Deux champs sur la même ligne, au même endroit, plutôt qu'un seul :
   « Emoji » et « Couleur » ne méritent pas une ligne chacun. */
.duo{display:flex;gap:6px}
.duo>*{flex:1;min-width:0}
.duo input[type=color]{flex:0 0 46px}
@media(max-width:560px){
    /* Les trois boutons d'action prenaient une centaine de pixels EN LARGEUR
       sur la ligne de l'article — c'est-à-dire la moitié de la place des
       champs, qui retombaient alors à une colonne. Empilés, ils en prennent
       trente-quatre et la grille reprend ses deux colonnes. */
    .art .act{flex-direction:column;gap:2px}
    .vig{width:44px;height:44px;font-size:18px}
    .art{gap:8px}
}
</style>
@endpush

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.pos.register',$terminal->id) }}" class="btn btn-d btn-sm"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir caisse') }}</a>
</div>

{{-- ══ AJOUTER ═══════════════════════════════════════════════════════════
     Un article, enregistré tout de suite. L'écran empilait auparavant des
     lignes vides qu'il fallait penser à enregistrer à la fin : on en scannait
     cinq, le téléphone se verrouillait, tout était perdu. Ici chaque article
     est acquis au moment où il apparaît dans la liste en dessous. --}}
<form method="POST" action="{{ route('tagtoa.pos.products.add',$terminal->id) }}"
      enctype="multipart/form-data" class="card" id="fadd">
    @csrf
    <div class="h-row" style="margin-bottom:12px">
        <h2>{{ __('Ajouter un article') }}</h2>
        <button type="button" class="btn btn-o btn-sm" id="scanBtn">
            <i class="fa-solid fa-barcode"></i> {{ __('Scanner') }}
        </button>
    </div>

    <div style="display:flex;gap:10px;align-items:flex-start">
        <label class="vig" id="vigAdd" title="{{ __('Photo de l\'article') }}">
            <span id="vigEmoji">🍔</span>
            <img id="vigImg" hidden alt="">
            <span class="cam"><i class="fa-solid fa-camera"></i></span>
            <input type="file" name="image" accept="image/*" id="imgAdd">
        </label>

        <div class="corps" style="flex:1;min-width:0">
            {{-- Pas d'étiquette au-dessus de ces quatre-là : le texte d'invite
                 dit déjà ce qu'on attend, et une étiquette par champ coûtait
                 dix-sept pixels de hauteur pour ne rien apprendre. --}}
            <div class="pf">
                <input class="ic w2" id="aName" name="name" required maxlength="120" autofocus
                       placeholder="{{ __('Nom de l\'article') }}" aria-label="{{ __('Nom') }}">
                <input class="ic" id="aPrice" name="price" type="number" step="0.01" min="0"
                       placeholder="{{ __('Prix') }}" aria-label="{{ __('Prix de vente') }}">
                <input class="ic" id="aStock" name="stock" type="number" step="0.001"
                       placeholder="{{ __('Stock') }}" aria-label="{{ __('Stock') }}">
            </div>

            {{-- Volet gestion : replié. Un marchand qui veut seulement une
                 grille de boutons ne doit pas le subir ; celui qui veut savoir
                 ce qu'il gagne le déplie une fois. --}}
            <div class="pf" id="addPlus" hidden style="margin-top:10px">
                <div>
                    <label for="aUnit">{{ __('Unité') }}</label>
                    {{-- Repliée avec le reste : la très grande majorité des
                         articles se vend à la pièce, qui est la valeur par
                         défaut. Celui qui vend au poids l'ouvre une fois. --}}
                    <select class="ic" id="aUnit" name="unit">
                        @foreach (\Modules\Tagtoa\App\Support\Catalog\Pricing::UNITS as $cle => $u)
                            <option value="{{ $cle }}">{{ __($u['label']) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="aCost">{{ __('Prix d\'achat') }}</label>
                    <input class="ic" id="aCost" name="cost_price" type="number" step="0.01" min="0" placeholder="—">
                </div>
                <div>
                    <label for="aSeuil">{{ __('Alerte sous') }}</label>
                    <input class="ic" id="aSeuil" name="low_stock_threshold" type="number" step="0.001" min="0" placeholder="5">
                </div>
                <div>
                    <label for="aSku">{{ __('Référence') }}</label>
                    <input class="ic" id="aSku" name="sku" maxlength="60" placeholder="SKU">
                </div>
                <div>
                    <label for="aFour">{{ __('Fournisseur') }}</label>
                    <select class="ic" id="aFour" name="supplier_id">
                        <option value="">—</option>
                        @foreach($suppliers as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="aEmoji">{{ __('Bouton') }}</label>
                    <div class="duo">
                        <input class="ic" id="aEmoji" name="emoji" maxlength="16" placeholder="🍔">
                        <input class="ic" name="color" type="color" value="#2cb809"
                               style="height:38px;padding:3px" aria-label="{{ __('Couleur') }}">
                    </div>
                </div>
            </div>

            <div style="display:flex;gap:8px;align-items:center;margin-top:12px;flex-wrap:wrap">
                <button class="btn btn-p"><i class="fa-solid fa-plus"></i> {{ __('Ajouter') }}</button>
                <button type="button" class="btn btn-o btn-sm" id="plusBtn">
                    <i class="fa-solid fa-sliders"></i> {{ __('Plus de détails') }}
                </button>
                <span id="scanmsg" style="font-size:13px"></span>
                <span id="codePose" style="font-size:12.5px;color:#1a7a05;font-family:monospace"></span>
            </div>
            <input type="hidden" name="new_code" id="aCode">
        </div>
    </div>
</form>

{{-- ══ LES ARTICLES DÉJÀ AU CATALOGUE ════════════════════════════════════ --}}
<form method="POST" action="{{ route('tagtoa.pos.products.save',$terminal->id) }}"
      enctype="multipart/form-data" class="card" id="fedit">
    @csrf
    <div class="h-row">
        <h2>{{ __('Vos articles') }} <span style="color:var(--muted);font-weight:400">({{ $terminal->products->count() }})</span></h2>
        @if($terminal->products->isNotEmpty())
            <button class="btn btn-p btn-sm"><i class="fa-solid fa-floppy-disk"></i> {{ __('Enregistrer') }}</button>
        @endif
    </div>

    <p style="color:var(--muted);font-size:12.5px;margin:-6px 0 4px">
        <i class="fa-solid fa-circle-info"></i>
        {{ __('Catalogue du commerce : toutes vos caisses y vendent les mêmes articles.') }}
        {{ __('Enregistrer ne supprime jamais — décochez pour retirer de la vente.') }}
    </p>

    @forelse($terminal->products as $i => $p)
    <div class="art" data-ref="pos:{{ $p->id }}">
        <input type="hidden" name="products[{{ $i }}][id]" value="{{ $p->id }}">
        <input type="hidden" name="products[{{ $i }}][sort]" value="{{ $p->sort }}">

        <label class="vig" title="{{ __('Changer la photo') }}"
               style="{{ $p->image_url ? '' : 'background:'.$p->color }}">
            @if($p->image_url)
                <img src="{{ $p->image_url }}" alt="{{ $p->name }}">
            @else
                <span>{{ $p->emoji ?: '🛒' }}</span>
            @endif
            <span class="cam"><i class="fa-solid fa-camera"></i></span>
            <input type="file" name="products[{{ $i }}][image]" accept="image/*" class="fimg">
        </label>

        <div class="corps">
            <div class="pf">
                <input class="ic w2" name="products[{{ $i }}][name]" value="{{ $p->name }}" maxlength="120"
                       aria-label="{{ __('Nom') }}">
                <input class="ic pv" name="products[{{ $i }}][price]" type="number" step="0.01" min="0"
                       value="{{ $p->price }}" placeholder="{{ __('Prix') }}" aria-label="{{ __('Prix de vente') }}">
                <input class="ic" name="products[{{ $i }}][stock]" type="number" step="0.001"
                       value="{{ $p->stock }}" placeholder="{{ __('Stock') }}" aria-label="{{ __('Stock') }}">
            </div>

            <div class="pf plus" hidden style="margin-top:8px">
                <div>
                    <label>{{ __('Prix d\'achat') }}</label>
                    <input class="ic pa" name="products[{{ $i }}][cost_price]" type="number" step="0.01" min="0" value="{{ $p->cost_price }}" placeholder="—">
                </div>
                <div>
                    <label>{{ __('Unité') }}</label>
                    <select class="ic" name="products[{{ $i }}][unit]">
                        @foreach (\Modules\Tagtoa\App\Support\Catalog\Pricing::UNITS as $cle => $u)
                            <option value="{{ $cle }}" @selected($p->unit_key === $cle)>{{ __($u['label']) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ __('Alerte sous') }}</label>
                    <input class="ic" name="products[{{ $i }}][low_stock_threshold]" type="number" step="0.001" min="0" value="{{ $p->low_stock_threshold }}" placeholder="5">
                </div>
                <div>
                    <label>{{ __('Référence') }}</label>
                    <input class="ic" name="products[{{ $i }}][sku]" value="{{ $p->sku }}" maxlength="60">
                </div>
                <div>
                    <label>{{ __('Fournisseur') }}</label>
                    <select class="ic" name="products[{{ $i }}][supplier_id]">
                        <option value="">—</option>
                        @foreach($suppliers as $f)
                            <option value="{{ $f->id }}" @selected($p->supplier_id === $f->id)>{{ $f->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ __('Bouton') }}</label>
                    <div class="duo">
                        <input class="ic" name="products[{{ $i }}][emoji]" value="{{ $p->emoji }}" maxlength="16" placeholder="🍔">
                        <input class="ic" name="products[{{ $i }}][color]" type="color"
                               value="{{ $p->color ?: '#2cb809' }}" style="height:38px;padding:3px"
                               aria-label="{{ __('Couleur') }}">
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
                    <a class="btn btn-o btn-sm" href="{{ route('tagtoa.catalog.codes.index') }}?ref=pos:{{ $p->id }}">
                        <i class="fa-solid fa-barcode"></i> {{ __('Codes-barres') }}
                    </a>
                    @if($p->image_url)
                        <label class="chk"><input type="checkbox" name="products[{{ $i }}][remove_image]" value="1"> {{ __('Retirer la photo') }}</label>
                    @endif
                    <span class="marge" style="font-size:12px;color:var(--muted)"></span>
                </div>
            </div>
        </div>

        <div class="act">
            <label class="chk" title="{{ __('En vente') }}">
                <input type="checkbox" name="products[{{ $i }}][is_active]" value="1" @checked($p->is_active)>
            </label>
            <button type="button" class="ib togplus" title="{{ __('Plus de détails') }}"><i class="fa-solid fa-sliders"></i></button>
            <button type="button" class="ib rouge delrow" data-id="{{ $p->id }}" data-nom="{{ $p->name }}"
                    title="{{ __('Supprimer du catalogue') }}"><i class="fa-solid fa-trash"></i></button>
        </div>
    </div>
    @empty
        <div class="empty" style="padding:30px 16px">
            <i class="fa-solid fa-box-open"></i>
            {{ __('Aucun article. Ajoutez le premier ci-dessus, ou scannez-le.') }}
        </div>
    @endforelse

    {{-- SENTINELLE — posée en DERNIER, volontairement.
         Au-delà de max_input_vars, PHP coupe l'envoi sans un mot. Si ce champ
         n'arrive pas, c'est que la fin de la liste n'est pas arrivée non plus,
         et le contrôleur refuse d'enregistrer un envoi amputé. --}}
    @if($terminal->products->isNotEmpty())
        <input type="hidden" name="form_end" value="1">
    @endif
</form>

{{-- Supprimer est un acte à part, jamais un effet de bord de l'enregistrement. --}}
<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>
@endsection

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script>
window.addEventListener('load', function () {
    var DEL_URL  = "{{ url('/tagtoa/pos/'.$terminal->id.'/products') }}",
        SCAN_URL = "{{ route('tagtoa.catalog.scan') }}",
        CSRF     = "{{ csrf_token() }}";

    /* ---- Volets « plus de détails » ---- */
    document.getElementById('plusBtn').addEventListener('click', function () {
        var d = document.getElementById('addPlus');
        d.hidden = !d.hidden;
    });
    document.querySelectorAll('.togplus').forEach(function (b) {
        b.addEventListener('click', function () {
            var d = b.closest('.art').querySelector('.plus');
            d.hidden = !d.hidden;
            if (!d.hidden) majMarge(b.closest('.art'));
        });
    });

    /* ---- Aperçu immédiat de la photo choisie ----
       Sans aperçu, on ne sait pas si le fichier est parti : sur un téléphone,
       la galerie se referme et l'écran n'a pas bougé. */
    function apercu(input, boite) {
        var f = input.files && input.files[0];
        if (!f) return;
        var url = URL.createObjectURL(f),
            img = boite.querySelector('img'),
            txt = boite.querySelector('span:not(.cam)');
        if (!img) {
            img = document.createElement('img');
            boite.insertBefore(img, boite.firstChild);
        }
        img.src = url; img.hidden = false;
        if (txt) txt.hidden = true;
        boite.style.background = '#fafafa';
    }
    document.querySelectorAll('.vig input[type=file]').forEach(function (inp) {
        inp.addEventListener('change', function () { apercu(inp, inp.closest('.vig')); });
    });

    /* ---- Marge : la seule raison de remplir un prix d'achat ---- */
    function majMarge(art) {
        var v = parseFloat((art.querySelector('.pv') || {}).value),
            a = parseFloat((art.querySelector('.pa') || {}).value),
            c = art.querySelector('.marge');
        if (!c) return;
        if (isNaN(a) || isNaN(v) || v <= 0) { c.textContent = ''; return; }
        var m = v - a;
        c.textContent = (m < 0 ? "{{ __('À PERTE') }} " : "{{ __('Marge') }} ")
            + m.toFixed(2) + ' (' + (Math.round(m / v * 1000) / 10) + '%)';
        c.style.color = m < 0 ? 'var(--red)' : 'var(--muted)';
    }
    document.querySelectorAll('.art').forEach(function (art) {
        ['.pv', '.pa'].forEach(function (s) {
            var el = art.querySelector(s);
            if (el) el.addEventListener('input', function () { majMarge(art); });
        });
    });

    /* ---- Supprimer : acte délibéré, confirmé, jamais un effet de bord ---- */
    document.querySelectorAll('.delrow').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm("{{ __('Supprimer définitivement cet article du catalogue de toutes vos caisses ?') }}\n\n" + b.dataset.nom)) return;
            var f = document.getElementById('delform');
            f.action = DEL_URL + '/' + b.dataset.id;
            f.submit();
        });
    });

    /* ---- Scanner ----
       Un code inconnu REMPLIT le formulaire d'ajout, il ne crée plus une ligne
       vide de plus. Le marchand écrit un nom, un prix, valide — l'article est
       en base, le formulaire se vide, il scanne le suivant. C'est la boucle
       complète : scanner un inconnu, le créer, puis le revendre en le scannant. */
    function direScan(texte, erreur) {
        var el = document.getElementById('scanmsg');
        el.textContent = texte;
        el.style.color = erreur ? 'var(--red)' : '#1a7a05';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.textContent = ''; }, 6000);
    }

    /* Article déjà connu : on le montre plutôt que d'en créer un deuxième.
       Deux articles pour le même produit, c'est un stock coupé en deux. */
    function surlignerExistant(ref) {
        var art = document.querySelector('.art[data-ref="' + ref + '"]');
        if (!art) return false;
        art.scrollIntoView({ behavior: 'smooth', block: 'center' });
        art.style.transition = 'background .4s';
        art.style.background = 'rgba(44,184,9,.14)';
        setTimeout(function () { art.style.background = ''; }, 1600);
        return true;
    }

    function surCode(code) {
        fetch(SCAN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body: JSON.stringify({ code: code })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (window.TagtoaScanner) TagtoaScanner.close();

            if (d && d.found) {
                direScan("{{ __('Ce code est déjà celui de : ') }}" + d.article.name);
                surlignerExistant(d.article.ref);
                return;
            }

            document.getElementById('aCode').value = code;
            document.getElementById('codePose').textContent = code;
            var nom = document.getElementById('aName');
            nom.scrollIntoView({ behavior: 'smooth', block: 'center' });
            nom.focus();
            direScan("{{ __('Code retenu — nom, prix, puis Ajouter.') }}");
        })
        .catch(function () { direScan("{{ __('Vérification impossible. Réessayez.') }}", true); });
    }

    if (!window.TagtoaScanner) { document.getElementById('scanBtn').disabled = true; return; }

    TagtoaScanner.listenWedge(surCode);

    document.getElementById('scanBtn').addEventListener('click', function () {
        TagtoaScanner.open({
            onCode: surCode,
            once:   true,
            title:  "{{ __('Scanner un produit') }}",
            hint:   "{{ __('Un code inconnu remplit le formulaire. Un code connu vous montre son article.') }}",
            submit: "{{ __('Chercher') }}"
        });
    });
});
</script>
@endpush
