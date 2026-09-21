@extends('tagtoa::layouts.dashboard')
@section('title', __('Produits'))
@section('page', $terminal->name.' — '.__('Produits'))

@push('head')
<style>
/* [hidden] AVANT tout le reste : une règle d'affichage explicite (display:grid,
   display:flex) l'emporte sur le display:none que le navigateur applique à
   l'attribut. Sans cette ligne, tous les volets s'ouvrent dépliés. */
[hidden]{display:none!important}

/* Saisie DENSE : les champs se rangent en grille et remplissent la largeur,
   au lieu d'une ligne entière par champ pour trois caractères. */
.pf{display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:8px 7px;align-items:end}
.pf .w2{grid-column:span 2}
.pf label{display:block;font:600 11px var(--fh);color:var(--muted);margin-bottom:3px;
          text-transform:uppercase;letter-spacing:.04em}
.ic{width:100%;padding:9px 11px;border:1.5px solid var(--bd);border-radius:9px;
    font:14.5px var(--fb);background:#fff;min-width:0}
.ic:focus{outline:0;border-color:var(--blue)}
select.ic{padding:8px 8px}

/* La vignette : une PHOTO. À défaut, l'initiale de l'article sur sa couleur de
   bouton — lisible, jamais ridicule, et fidèle à ce que le caissier verra. */
.vig{position:relative;width:52px;height:52px;border-radius:12px;overflow:hidden;flex:0 0 auto;
     display:flex;align-items:center;justify-content:center;cursor:pointer;
     background:#eee;color:#fff;font:700 19px var(--fh);border:1.5px solid var(--bd)}
.vig img{width:100%;height:100%;object-fit:cover}
.vig input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.vig .cam{position:absolute;right:2px;bottom:2px;background:rgba(0,0,0,.6);color:#fff;
          border-radius:6px;font-size:9px;padding:1px 4px;pointer-events:none}

/* Une ligne d'article : un numéro, une vignette, un nom, un prix, trois points.
   Rien d'autre tant qu'on n'a pas demandé à modifier. */
.art + .art{border-top:1px solid var(--bd)}
.art .tete{display:flex;gap:11px;align-items:center;padding:11px 0}
.art .num{flex:0 0 22px;text-align:right;font:700 13px var(--fh);color:var(--muted)}
.art .res{flex:1;min-width:0}
.art .res b{font:700 15px var(--fh);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.art .res span{font-size:13px;color:var(--muted)}
.art.off .res b{color:var(--muted);text-decoration:line-through}

/* Le menu trois points. Un <details> natif : il s'ouvre sans JavaScript, et
   il se ferme tout seul quand on en ouvre un autre (script plus bas). */
.kebab{position:relative}
.kebab>summary{list-style:none;cursor:pointer;width:36px;height:36px;border-radius:9px;
       display:flex;align-items:center;justify-content:center;color:var(--muted)}
.kebab>summary::-webkit-details-marker{display:none}
.kebab>summary:hover{background:rgba(0,0,0,.05);color:var(--blk)}
.kebab .menu{position:absolute;right:0;top:38px;z-index:30;min-width:190px;background:var(--surface);
       border:1px solid var(--bd);border-radius:13px;padding:6px;
       box-shadow:0 12px 34px rgba(0,0,0,.14)}
.kebab .menu button,.kebab .menu a{display:flex;align-items:center;gap:10px;width:100%;
       padding:10px 11px;border:0;border-radius:9px;background:none;cursor:pointer;
       font:600 13.5px var(--fh);color:var(--blk);text-align:left}
.kebab .menu button:hover,.kebab .menu a:hover{background:rgba(0,0,0,.05)}
.kebab .menu i{width:16px;text-align:center;color:var(--muted)}
.kebab .menu .danger{color:var(--red)}
.kebab .menu .danger i{color:var(--red)}
.kebab .menu hr{border:0;border-top:1px solid var(--bd);margin:5px 2px}

.chk{display:inline-flex;align-items:center;gap:7px;font:600 12.5px var(--fh);color:var(--muted)}
.duo{display:flex;gap:6px}.duo>*{flex:1;min-width:0}.duo input[type=color]{flex:0 0 46px}
@media(max-width:560px){.vig{width:46px;height:46px;font-size:17px}}
</style>
@endpush

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.pos.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <span style="flex:1"></span>
    <a href="{{ route('tagtoa.pos.register',$terminal->id) }}" class="btn btn-d btn-sm"><i class="fa-solid fa-cash-register"></i> {{ __('Ouvrir caisse') }}</a>
</div>

{{-- ══ AJOUTER ══════════════════════════════════════════════════════════
     Un article, enregistré tout de suite, puis le formulaire se vide et
     l'article rejoint la liste en dessous. Il ne reste JAMAIS dans la zone de
     saisie : tant qu'il y est, on ne sait pas s'il est acquis. --}}
<form method="POST" action="{{ route('tagtoa.pos.products.add',$terminal->id) }}"
      enctype="multipart/form-data" class="card" id="fadd">
    @csrf
    <div class="h-row" style="margin-bottom:4px">
        <h2>{{ __('Ajouter un article') }}</h2>
        {{-- LE CHEMIN RAPIDE. Un commerce qui reçoit un carton passe la
             douchette sur trente articles d'affilée : l'article est créé au
             bip, on le nomme ensuite, assis. Demander un nom et un prix à
             chaque bip ferait abandonner à l'article cinq. --}}
        <button type="button" class="btn btn-d btn-sm" id="scanBtn">
            <i class="fa-solid fa-barcode"></i> {{ __('Scanner un code') }}
        </button>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin-bottom:12px">
        {{ __('Scanner enregistre l\'article tout de suite — vous le nommerez après. Ou remplissez la fiche ci-dessous.') }}
    </p>

    <div style="display:flex;gap:10px;align-items:flex-start">
        <label class="vig" title="{{ __('Photo de l\'article') }}" style="background:#2cb809">
            <span class="ini"><i class="fa-solid fa-image"></i></span>
            <span class="cam"><i class="fa-solid fa-camera"></i></span>
            <input type="file" name="image" accept="image/*">
        </label>

        <div style="flex:1;min-width:0">
            <div class="pf">
                <input class="ic w2" id="aName" name="name" required maxlength="120" autofocus
                       placeholder="{{ __('Nom de l\'article') }}" aria-label="{{ __('Nom') }}">
                <input class="ic" name="price" type="number" step="0.01" min="0"
                       placeholder="{{ __('Prix') }}" aria-label="{{ __('Prix de vente') }}">
                <input class="ic" id="aStock" name="stock" type="number" step="0.001"
                       placeholder="{{ __('Stock') }}" aria-label="{{ __('Stock') }}">
            </div>
            <label class="chk" style="margin-top:8px" title="{{ __('Une nuitée, une consultation : rien à compter, rien à scanner.') }}">
                <input type="checkbox" id="aService" name="is_service" value="1"> {{ __('Service (sans stock physique)') }}
            </label>

            <div class="pf" id="addPlus" hidden style="margin-top:10px">
                <div>
                    <label for="aDesc">{{ __('Description') }}</label>
                    <input class="ic" id="aDesc" name="description" maxlength="160"
                           placeholder="{{ __('Deux lignes, pas plus') }}">
                </div>
                <div>
                    <label for="aUnit">{{ __('Unité') }}</label>
                    <select class="ic" id="aUnit" name="unit">
                        @include('tagtoa::partials.unit-options', ['suggested' => $suggestedUnits ?? []])
                    </select>
                </div>
                <div>
                    <label for="aCost">{{ __('Prix d\'achat') }}</label>
                    <input class="ic" id="aCost" name="cost_price" type="number" step="0.01" min="0" placeholder="—">
                </div>
                <div id="aSeuilWrap">
                    <label for="aSeuil">{{ __('Alerte sous') }}</label>
                    <input class="ic" id="aSeuil" name="low_stock_threshold" type="number" step="0.001" min="0" placeholder="5">
                </div>
                <div>
                    <label for="aSku">{{ __('Référence') }}</label>
                    <input class="ic" id="aSku" name="sku" maxlength="60" placeholder="SKU">
                </div>
                <div>
                    <label for="aTaxe">{{ __('Taxe (%)') }}</label>
                    <input class="ic" id="aTaxe" name="tax_rate" type="number" step="0.01" min="0" max="99.999"
                           placeholder="{{ __('Du commerce') }}" title="{{ __('Vide = taux du commerce. 0 = article exonéré.') }}">
                </div>
                <div>
                    <label for="aRayon">{{ __('Rayon') }}</label>
                    <select class="ic" id="aRayon" name="category_id">
                        <option value="">—</option>
                        @foreach($categories as $r)<option value="{{ $r->id }}">{{ $r->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="aFour">{{ __('Fournisseur') }}</label>
                    <select class="ic" id="aFour" name="supplier_id">
                        <option value="">—</option>
                        @foreach($suppliers as $f)<option value="{{ $f->id }}">{{ $f->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="aAchat">{{ __('Date d\'achat') }}</label>
                    <input class="ic" id="aAchat" name="purchased_at" type="date">
                </div>
                <div>
                    <label for="aParent">{{ __('Se vend depuis') }}</label>
                    <select class="ic" id="aParent" name="parent_product_id">
                        <option value="">—</option>
                        @foreach($terminal->products->whereNull('parent_product_id')->where('is_service', false) as $par)
                            <option value="{{ $par->id }}">{{ $par->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="aRatio">{{ __('Unités par parent') }}</label>
                    <input class="ic" id="aRatio" name="units_per_parent" type="number" step="0.001" min="0.001"
                           placeholder="{{ __('Ex. 25 verres / bouteille') }}"
                           title="{{ __('Un bar tient son stock en bouteilles : combien de verres fait UNE bouteille.') }}">
                </div>
                <div>
                    <label for="aColor">{{ __('Couleur du bouton') }}</label>
                    <input class="ic" id="aColor" name="color" type="color" value="#2cb809" style="height:38px;padding:3px">
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

{{-- ══ VOS ARTICLES ═════════════════════════════════════════════════════
     Une liste numérotée, pas une pile de formulaires. Modifier est un geste
     qu'on demande — par les trois points — et chaque article s'enregistre
     SEUL : plus aucun envoi géant que PHP puisse tronquer. --}}
<div class="card">
    <div class="h-row">
        <h2>{{ __('Vos articles') }}
            <span style="color:var(--muted);font-weight:400">({{ $terminal->products->count() }})</span>
        </h2>
    </div>
    <p style="color:var(--muted);font-size:12.5px;margin:-6px 0 6px">
        <i class="fa-solid fa-circle-info"></i>
        {{ __('Catalogue du commerce : toutes vos caisses y vendent les mêmes articles.') }}
    </p>

    @forelse($terminal->products as $i => $p)
    <div class="art {{ $p->is_active ? '' : 'off' }}" data-ref="pos:{{ $p->id }}">
        <div class="tete">
            <span class="num">{{ $i + 1 }}</span>

            <span class="vig" style="{{ $p->image_url ? '' : 'background:'.($p->color ?: '#2cb809') }}">
                @if($p->image_url)
                    <img src="{{ $p->image_url }}" alt="{{ $p->name }}">
                @else
                    {{ mb_strtoupper(mb_substr($p->name, 0, 1)) }}
                @endif
            </span>

            <span class="res">
                <b>{{ $p->name }}</b>
                <span>
                    {{ \Modules\Tagtoa\App\Support\Money::format($p->price, $terminal->currency) }}
                    @if($p->stock !== null) · {{ __('Stock') }} {{ rtrim(rtrim(number_format($p->stock, 3, '.', ''), '0'), '.') }} @endif
                    @if($p->category) · {{ $p->category->name }} @endif
                    @unless($p->is_active) · {{ __('retiré de la vente') }} @endunless
                </span>
            </span>

            <details class="kebab">
                <summary aria-label="{{ __('Actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></summary>
                <div class="menu">
                    <button type="button" class="modifier"><i class="fa-solid fa-pen"></i> {{ __('Modifier') }}</button>
                    <a href="{{ route('tagtoa.catalog.codes.index') }}?ref=pos:{{ $p->id }}">
                        <i class="fa-solid fa-barcode"></i> {{ __('Codes-barres') }}
                    </a>
                    <button type="button" class="basculer" data-id="{{ $p->id }}">
                        <i class="fa-solid {{ $p->is_active ? 'fa-eye-slash' : 'fa-eye' }}"></i>
                        {{ $p->is_active ? __('Retirer de la vente') : __('Remettre en vente') }}
                    </button>
                    <hr>
                    <button type="button" class="danger supprimer" data-id="{{ $p->id }}" data-nom="{{ $p->name }}">
                        <i class="fa-solid fa-trash"></i> {{ __('Supprimer') }}
                    </button>
                </div>
            </details>
        </div>

        {{-- Le formulaire de CET article, et de lui seul. Un formulaire par
             ligne : plus aucun envoi géant, donc plus rien que PHP puisse
             tronquer au-delà de max_input_vars. --}}
        <form method="POST" action="{{ route('tagtoa.pos.products.save',$terminal->id) }}"
              enctype="multipart/form-data" class="edition" hidden style="padding:4px 0 16px 33px">
            @csrf
            <input type="hidden" name="products[0][id]" value="{{ $p->id }}">
            <input type="hidden" name="products[0][sort]" value="{{ $p->sort }}">

            <div style="display:flex;gap:10px;align-items:flex-start">
                <label class="vig" title="{{ __('Changer la photo') }}"
                       style="{{ $p->image_url ? '' : 'background:'.($p->color ?: '#2cb809') }}">
                    @if($p->image_url)<img src="{{ $p->image_url }}" alt="">@else<span class="ini">{{ mb_strtoupper(mb_substr($p->name,0,1)) }}</span>@endif
                    <span class="cam"><i class="fa-solid fa-camera"></i></span>
                    <input type="file" name="products[0][image]" accept="image/*">
                </label>

                <div style="flex:1;min-width:0">
                    <div class="pf">
                        <input class="ic w2" name="products[0][name]" value="{{ $p->name }}" maxlength="120" aria-label="{{ __('Nom') }}">
                        <input class="ic pv" name="products[0][price]" type="number" step="0.01" min="0"
                               value="{{ $p->price }}" placeholder="{{ __('Prix') }}" aria-label="{{ __('Prix de vente') }}">
                        <input class="ic" name="products[0][stock]" type="number" step="0.001" data-role="stock"
                               value="{{ $p->stock }}" placeholder="{{ __('Stock') }}" aria-label="{{ __('Stock') }}">
                    </div>
                    <label class="chk" style="margin-top:6px" title="{{ __('Une nuitée, une consultation : rien à compter, rien à scanner.') }}">
                        <input type="checkbox" class="jSvc" name="products[0][is_service]" value="1" @checked($p->is_service)>
                        {{ __('Service (sans stock physique)') }}
                    </label>
                    <div class="pf" style="margin-top:8px">
                        <div class="w2">
                            <label>{{ __('Description') }}</label>
                            <input class="ic" name="products[0][description]" value="{{ $p->description }}" maxlength="160">
                        </div>
                        <div>
                            <label>{{ __('Unité') }}</label>
                            <select class="ic" name="products[0][unit]">
                                @include('tagtoa::partials.unit-options', ['suggested' => $suggestedUnits ?? [], 'selected' => $p->unit_key])
                            </select>
                        </div>
                        <div>
                            <label>{{ __('Prix d\'achat') }}</label>
                            <input class="ic pa" name="products[0][cost_price]" type="number" step="0.01" min="0" value="{{ $p->cost_price }}" placeholder="—">
                        </div>
                        <div data-role="seuil-wrap">
                            <label>{{ __('Alerte sous') }}</label>
                            <input class="ic" name="products[0][low_stock_threshold]" type="number" step="0.001" min="0" value="{{ $p->low_stock_threshold }}" placeholder="5">
                        </div>
                        <div>
                            <label>{{ __('Référence') }}</label>
                            <input class="ic" name="products[0][sku]" value="{{ $p->sku }}" maxlength="60">
                        </div>
                        <div>
                            <label>{{ __('Taxe (%)') }}</label>
                            <input class="ic" name="products[0][tax_rate]" type="number" step="0.01" min="0" max="99.999"
                                   value="{{ $p->tax_rate }}" placeholder="{{ __('Du commerce') }}"
                                   title="{{ __('Vide = taux du commerce. 0 = article exonéré.') }}">
                        </div>
                        <div>
                            <label>{{ __('Rayon') }}</label>
                            <select class="ic" name="products[0][category_id]">
                                <option value="">—</option>
                                @foreach($categories as $r)
                                    <option value="{{ $r->id }}" @selected($p->category_id === $r->id)>{{ $r->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>{{ __('Fournisseur') }}</label>
                            <select class="ic" name="products[0][supplier_id]">
                                <option value="">—</option>
                                @foreach($suppliers as $f)
                                    <option value="{{ $f->id }}" @selected($p->supplier_id === $f->id)>{{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>{{ __('Date d\'achat') }}</label>
                            <input class="ic" name="products[0][purchased_at]" type="date"
                                   value="{{ optional($p->purchased_at)->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label>{{ __('Se vend depuis') }}</label>
                            <select class="ic" name="products[0][parent_product_id]">
                                <option value="">—</option>
                                @foreach($terminal->products->whereNull('parent_product_id')->where('is_service', false)->where('id', '!=', $p->id) as $par)
                                    <option value="{{ $par->id }}" @selected($p->parent_product_id === $par->id)>{{ $par->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label>{{ __('Unités par parent') }}</label>
                            <input class="ic" name="products[0][units_per_parent]" type="number" step="0.001" min="0.001"
                                   value="{{ $p->units_per_parent }}" placeholder="{{ __('Ex. 25 verres / bouteille') }}"
                                   title="{{ __('Un bar tient son stock en bouteilles : combien de verres fait UNE bouteille.') }}">
                        </div>
                        <div>
                            <label>{{ __('Couleur du bouton') }}</label>
                            <input class="ic" name="products[0][color]" type="color" value="{{ $p->color ?: '#2cb809' }}" style="height:38px;padding:3px">
                        </div>
                    </div>

                    <div style="display:flex;gap:12px;align-items:center;margin-top:12px;flex-wrap:wrap">
                        <button class="btn btn-p btn-sm"><i class="fa-solid fa-check"></i> {{ __('Enregistrer') }}</button>
                        <button type="button" class="btn btn-o btn-sm annuler">{{ __('Annuler') }}</button>
                        <label class="chk">
                            <input type="checkbox" name="products[0][is_active]" value="1" @checked($p->is_active)>
                            {{ __('En vente') }}
                        </label>
                        @if($p->image_url)
                            <label class="chk"><input type="checkbox" name="products[0][remove_image]" value="1"> {{ __('Retirer la photo') }}</label>
                        @endif
                        <span class="marge" style="font-size:12px;color:var(--muted)"></span>
                    </div>
                </div>
            </div>
            {{-- Sentinelle : une seule ligne par envoi, donc elle arrive
                 toujours — mais la garde du serveur reste la même pour tous. --}}
            <input type="hidden" name="form_end" value="1">
        </form>
    </div>
    @empty
        <div class="empty" style="padding:30px 16px">
            <i class="fa-solid fa-box-open"></i>
            {{ __('Aucun article. Ajoutez le premier ci-dessus, ou scannez-le.') }}
        </div>
    @endforelse
</div>

{{-- Supprimer et basculer la vente : des actes à part, jamais un effet de bord
     de l'enregistrement. --}}
<form id="delform" method="POST" style="display:none">@csrf @method('DELETE')</form>
<form id="togform" method="POST" style="display:none">@csrf
    <input type="hidden" name="products[0][id]" id="togId">
    <input type="hidden" name="products[0][toggle_active]" value="1">
    <input type="hidden" name="form_end" value="1">
</form>
@endsection

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}" defer></script>
<script>
window.addEventListener('load', function () {
    var DEL_URL  = "{{ url('/tagtoa/pos/'.$terminal->id.'/products') }}",
        SAVE_URL = "{{ route('tagtoa.pos.products.save', $terminal->id) }}",
        SCAN_URL = "{{ route('tagtoa.pos.products.scan', $terminal->id) }}",
        CSRF     = "{{ csrf_token() }}";

    function fermerMenus(sauf) {
        document.querySelectorAll('.kebab[open]').forEach(function (d) { if (d !== sauf) d.open = false; });
    }
    document.querySelectorAll('.kebab').forEach(function (d) {
        d.addEventListener('toggle', function () { if (d.open) fermerMenus(d); });
    });
    /* Cliquer ailleurs referme : un menu resté ouvert masque la ligne suivante. */
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.kebab')) fermerMenus(null);
    });

    document.getElementById('plusBtn').addEventListener('click', function () {
        var d = document.getElementById('addPlus'); d.hidden = !d.hidden;
    });

    /* Service (nuitée, consultation) : rien à compter, rien à alerter.
       On ne fait que MASQUER — la valeur elle-même est forcée à null côté
       serveur (voir PosController::addProduct), jamais fait confiance à ce
       que cache l'écran. */
    document.getElementById('aService').addEventListener('change', function () {
        document.getElementById('aStock').hidden = this.checked;
        document.getElementById('aSeuilWrap').hidden = this.checked;
    });

    /* ---- Modifier : on le DEMANDE, ce n'est plus l'état par défaut ---- */
    document.querySelectorAll('.art').forEach(function (art) {
        var form = art.querySelector('.edition'),
            tete = art.querySelector('.tete');

        art.querySelector('.modifier').addEventListener('click', function () {
            fermerMenus(null);
            form.hidden = false;
            tete.style.opacity = '.55';
            var n = form.querySelector('input[name$="[name]"]');
            if (n) { n.focus(); n.select(); }
            majMarge(form);
        });
        art.querySelector('.annuler').addEventListener('click', function () {
            form.hidden = true;
            tete.style.opacity = '';
        });

        ['.pv', '.pa'].forEach(function (s) {
            var el = form.querySelector(s);
            if (el) el.addEventListener('input', function () { majMarge(form); });
        });

        var f = form.querySelector('input[type=file]');
        if (f) f.addEventListener('change', function () { apercu(f); });

        // Service : rien à compter, rien à alerter. On ne fait que MASQUER —
        // la valeur est forcée à null côté serveur (PosController::saveProducts).
        var svc = form.querySelector('.jSvc'),
            stockField = form.querySelector('[data-role="stock"]'),
            seuilWrap = form.querySelector('[data-role="seuil-wrap"]');
        if (svc) {
            var syncSvc = function () {
                if (stockField) stockField.hidden = svc.checked;
                if (seuilWrap) seuilWrap.hidden = svc.checked;
            };
            svc.addEventListener('change', syncSvc);
            syncSvc();
        }
    });

    /* Ce qui reste sur une unité vendue : la seule raison pour laquelle un
       marchand prend le temps de remplir son prix d'achat. */
    function majMarge(form) {
        var v = parseFloat((form.querySelector('.pv') || {}).value),
            a = parseFloat((form.querySelector('.pa') || {}).value),
            c = form.querySelector('.marge');
        if (!c) return;
        if (isNaN(a) || isNaN(v) || v <= 0) { c.textContent = ''; return; }
        var m = v - a;
        c.textContent = (m < 0 ? "{{ __('À PERTE') }} " : "{{ __('Marge') }} ")
            + m.toFixed(2) + ' (' + (Math.round(m / v * 1000) / 10) + '%)';
        c.style.color = m < 0 ? 'var(--red)' : 'var(--muted)';
    }

    /* Aperçu immédiat : sur un téléphone, la galerie se referme et rien ne dit
       que le fichier est bien parti. */
    function apercu(input) {
        var f = input.files && input.files[0];
        if (!f) return;
        var boite = input.closest('.vig'),
            img = boite.querySelector('img'),
            ini = boite.querySelector('.ini');
        if (!img) { img = document.createElement('img'); boite.insertBefore(img, boite.firstChild); }
        img.src = URL.createObjectURL(f); img.hidden = false;
        if (ini) ini.hidden = true;
    }
    document.querySelectorAll('#fadd input[type=file]').forEach(function (i) {
        i.addEventListener('change', function () { apercu(i); });
    });

    /* ---- Retirer de la vente / remettre ---- */
    document.querySelectorAll('.basculer').forEach(function (b) {
        b.addEventListener('click', function () {
            var f = document.getElementById('togform');
            f.action = SAVE_URL;
            document.getElementById('togId').value = b.dataset.id;
            f.submit();
        });
    });

    /* ---- Supprimer : acte délibéré, confirmé ---- */
    document.querySelectorAll('.supprimer').forEach(function (b) {
        b.addEventListener('click', function () {
            if (!confirm("{{ __('Supprimer définitivement cet article du catalogue de toutes vos caisses ?') }}\n\n" + b.dataset.nom)) return;
            var f = document.getElementById('delform');
            f.action = DEL_URL + '/' + b.dataset.id;
            f.submit();
        });
    });

    /* ---- Scanner : un code inconnu REMPLIT le formulaire d'ajout ---- */
    function direScan(texte, erreur) {
        var el = document.getElementById('scanmsg');
        el.textContent = texte;
        el.style.color = erreur ? 'var(--red)' : '#1a7a05';
        clearTimeout(el._t);
        el._t = setTimeout(function () { el.textContent = ''; }, 6000);
    }

    function surlignerExistant(ref) {
        var art = document.querySelector('.art[data-ref="' + ref + '"]');
        if (!art) return false;
        art.scrollIntoView({ behavior: 'smooth', block: 'center' });
        art.style.transition = 'background .4s';
        art.style.background = 'rgba(44,184,9,.14)';
        setTimeout(function () { art.style.background = ''; }, 1600);
        return true;
    }

    /* L'article est créé ET ENREGISTRÉ au bip. La caméra reste ouverte : on
       passe la douchette sur tout le carton, puis on nomme les articles dans
       la liste. Un code déjà connu ne crée rien — deux articles pour le même
       produit, c'est un stock coupé en deux. */
    var scannes = 0;

    function surCode(code) {
        fetch(SCAN_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ code: code })
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (!d) return;
            direScan(d.message, d.result !== 'created');
            if (d.result === 'created') {
                scannes++;
                if (window.TagtoaSound) TagtoaSound.add();
            } else {
                if (window.TagtoaSound) TagtoaSound.error();
                if (d.product) surlignerExistant('pos:' + d.product.id);
            }
        })
        .catch(function () { direScan("{{ __('Enregistrement impossible. Réessayez ce code.') }}", true); });
    }

    /* On recharge SEULEMENT à la fermeture du scanner : recharger à chaque bip
       couperait la caméra et le marchand recommencerait tout. */
    function finDuScan() {
        if (scannes > 0) location.reload();
    }

    if (!window.TagtoaScanner) { document.getElementById('scanBtn').disabled = true; return; }
    TagtoaScanner.listenWedge(surCode);
    document.getElementById('scanBtn').addEventListener('click', function () {
        scannes = 0;
        TagtoaScanner.open({
            onCode: surCode,
            /* La caméra RESTE ouverte : c'est tout l'objet du chemin rapide. */
            once:   false,
            title:  "{{ __('Scanner vos produits') }}",
            hint:   "{{ __('Chaque code inconnu crée un article enregistré. Vous les nommerez ensuite.') }}",
            submit: "{{ __('Enregistrer') }}",
            onClose: finDuScan
        });
    });
});
</script>
@endpush
