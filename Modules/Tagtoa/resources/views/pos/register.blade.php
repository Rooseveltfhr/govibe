{{-- TAGTOA POS — Caisse tactile (offline-first, Web Audio, split, reçu WhatsApp).
     Variables : $terminal, $products, $methods. Standalone. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"><meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $terminal->name }} — TAGTOA POS</title>
    {{-- PWA : installable + hors ligne --}}
    <link rel="manifest" href="{{ route('tagtoa.pos.manifest',$terminal->id) }}">
    <meta name="theme-color" content="#2cb809">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <link rel="apple-touch-icon" href="{{ route('tagtoa.pos.icon') }}">
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    {{-- Scanner : auto-hébergé comme le reste. Un CDN injoignable, c'est un
         scanner cassé le jour où la connexion est mauvaise. --}}
    <script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
    <script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
    <script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}" defer></script>
    <style>
        :root{--blk:#0A0A0A;--blue:#2cb809;--green:#1D9E75;--red:#E0473E;--bg:#F5F5F3;--bd:rgba(0,0,0,.08);--fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif}
        *{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
        body{font-family:var(--fb);background:var(--bg);color:var(--blk);height:100vh;height:100dvh;overflow:hidden}
        .app{display:grid;grid-template-columns:1fr 320px;height:100vh;height:100dvh}
        /* ------------------------------------------------------------------
           TÉLÉPHONE — la grille défile, et le panier ne mange plus l'écran.

           AVANT : `.app` était une grille dont la ligne du catalogue se
           dimensionnait sur SON CONTENU. `.grid` avait bien `overflow-y:auto`,
           mais aucune hauteur à ne pas dépasser : elle poussait donc sous le
           pli, et `body{overflow:hidden}` coupait net le reste. Les articles
           au-delà du sixième existaient et étaient INATTEIGNABLES — aucun
           défilement, aucune erreur.

           MAINTENANT : colonne flexible, et la grille prend « tout ce qui
           reste » (`flex:1` + `min-height:0`, sans quoi un enfant flex refuse
           de rétrécir sous son contenu et le défaut revient à l'identique).

           Et le panier n'est plus un tiroir collé en bas qui prenait la moitié
           de la hauteur en permanence : il s'ouvre par son icône, en haut.
           ------------------------------------------------------------------ */
        @media(max-width:760px){
            .app{display:flex;flex-direction:column}
            .top,.poste{flex:0 0 auto}
            .grid{flex:1 1 auto;min-height:0}
            .cart{position:fixed;left:0;right:0;bottom:0;top:auto;max-height:84dvh;
                  border-radius:18px 18px 0 0;box-shadow:0 -8px 30px rgba(0,0,0,.2);
                  border-left:0;z-index:55;transform:translateY(100%);transition:transform .18s ease-out}
            .cart.show{transform:translateY(0)}
            /* Voile : on ferme le panier en touchant à côté, le geste que tout
               le monde tente en premier. */
            .voile{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:54;display:none}
            .voile.show{display:block}
        }
        /* Sur grand écran le panier reste une colonne : il y a la place, et le
           caissier veut voir le total pendant qu'il compose la commande. */
        @media(min-width:761px){.cartbtn{display:none}.cart{transform:none}.voile{display:none}}
        .top{grid-column:1/-1;display:flex;align-items:center;gap:12px;padding:12px 18px;background:var(--blk);color:#fff}.top h1{font:600 16px var(--fh);flex:1}.top .net{font-size:12px;padding:4px 9px;border-radius:999px;background:rgba(255,255,255,.15)}.top .net.off{background:#E08A1E}.top a{color:#fff;opacity:.8;text-decoration:none}
        .grid{padding:14px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(122px,1fr));gap:10px;align-content:start}
        /* Les rayons — quarante articles ne doivent plus être un mur de
           boutons. Défilement horizontal : sur téléphone, une liste de rayons
           dépasse vite la largeur de l'écran. */
        .rayons{flex:0 0 auto;display:flex;gap:8px;padding:10px 14px 0;overflow-x:auto;-webkit-overflow-scrolling:touch}
        .rayons::-webkit-scrollbar{display:none}
        .rayons button{flex:0 0 auto;border:0;border-radius:999px;padding:8px 14px;font:600 12.5px var(--fh);
                       background:#fff;color:var(--blk);border:1px solid var(--bd);cursor:pointer;white-space:nowrap}
        .rayons button.on{background:var(--blk);color:#fff;border-color:var(--blk)}
        /* Le bouton de caisse porte une PHOTO quand il en a une. Un emoji ne
           distingue pas trois plats de riz ni quatre tailles de la même bière,
           et c'est exactement là que le caissier se trompe de bouton, en pleine
           affluence. À défaut de photo : l'initiale sur la couleur — lisible de
           loin, jamais un carré blanc. */
        .p{border:0;border-radius:16px;padding:0;color:#fff;cursor:pointer;font:600 13px var(--fh);display:flex;flex-direction:column;min-height:112px;transition:transform .1s;overflow:hidden;text-align:left}
        .p:active{transform:scale(.94)}
        .p .ph{width:100%;height:60px;object-fit:cover;display:flex;align-items:center;justify-content:center;font:700 24px var(--fh);background:rgba(0,0,0,.14)}
        .p .lb{padding:7px 8px 8px;display:flex;flex-direction:column;gap:2px;flex:1;justify-content:center;width:100%}
        .p .nm{line-height:1.25;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .p .pr{font-size:12px;opacity:.85}
        .cart{background:#fff;border-left:1px solid var(--bd);display:flex;flex-direction:column}.cart h2{font:600 15px var(--fh);padding:14px 16px;border-bottom:1px solid var(--bd)}
        .lines{flex:1;overflow-y:auto;padding:8px 12px}.ln{display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid var(--bd)}.ln .nm{flex:1;font-size:14px}.ln .nm small{display:block;color:#888}.ln .q button{width:26px;height:26px;border-radius:7px;border:1px solid var(--bd);background:#fff;cursor:pointer}
        .tot{padding:12px 16px;border-top:1px solid var(--bd)}.tot .r{display:flex;justify-content:space-between;font-size:14px;padding:3px 0}.tot .r.g{font:700 20px var(--fh)}
        .pay{display:block;width:100%;background:var(--green);color:#fff;border:0;border-radius:14px;padding:15px;font:600 16px var(--fh);cursor:pointer;margin-top:8px}.pay:disabled{background:#bcd;cursor:not-allowed}
        .modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:none;align-items:flex-end;z-index:50}.modal.show{display:flex}
        .sheet{background:#fff;width:100%;max-width:480px;margin:0 auto;border-radius:22px 22px 0 0;padding:18px;max-height:88vh;overflow-y:auto}.sheet h3{font-family:var(--fh);margin-bottom:6px}
        .methods{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:12px 0}.m{border:1.5px solid var(--bd);border-radius:12px;padding:10px 6px;text-align:center;cursor:pointer;font-size:12px;background:#fff}.m.on{border-color:var(--blue);background:rgba(44,184,9,.08);color:var(--blue);font-weight:700}
        .split{font-size:13px;color:#666;margin:8px 0}.split input{width:90px;padding:6px;border:1px solid var(--bd);border-radius:8px;text-align:right}
        .field{width:100%;padding:11px;border:1.5px solid var(--bd);border-radius:10px;margin:6px 0;font:15px var(--fb)}
        .done{position:fixed;inset:0;background:var(--green);color:#fff;display:none;flex-direction:column;align-items:center;justify-content:center;z-index:60;text-align:center;padding:20px}.done.show{display:flex}.done i{font-size:64px}.done h2{font:700 24px var(--fh);margin:12px 0 4px}.done .acts{display:flex;gap:10px;margin-top:20px}.done .acts a,.done .acts button{background:rgba(255,255,255,.2);color:#fff;border:0;border-radius:12px;padding:12px 18px;font:600 14px var(--fh);text-decoration:none;cursor:pointer}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body data-terminal="{{ $terminal->id }}" data-currency="{{ $terminal->currency }}">
    <style>
        .grid .p{position:relative}
        .grid .p .st{position:absolute;top:5px;right:7px;background:rgba(0,0,0,.55);color:#fff;
               border-radius:999px;padding:1px 7px;font-size:11px;font-weight:700}
        .who{width:30px;height:30px;border-radius:50%;background:var(--blue);color:#fff;
             display:inline-flex;align-items:center;justify-content:center;
             font:700 12px var(--fh,sans-serif);flex:0 0 30px}
        .poste{display:flex;align-items:center;gap:10px;flex-wrap:wrap;
               padding:9px 12px;background:#fff;border-bottom:1px solid var(--bd);font-size:14px}
        .poste form{display:flex;align-items:center;gap:8px;margin:0}
        .poste label{color:#666;font-size:13px}
        .poste input{width:96px;padding:9px 11px;border:1px solid var(--bd);border-radius:8px;
                     font-size:17px;letter-spacing:.3em;text-align:center}
        .poste button{padding:9px 15px;border:0;border-radius:8px;background:var(--blue);
                      color:#fff;font-weight:600;font-size:14px;cursor:pointer}
        .poste .err{color:var(--red);font-weight:600;font-size:13px}
    </style>
    <div class="app">
        <div class="top"><i class="fa-solid fa-cash-register" style="color:var(--blue)"></i><h1>{{ $terminal->name }}</h1><button id="installBtn" class="net" style="display:none;border:0;cursor:pointer;background:var(--blue)" title="{{ __('Installer l\'application') }}"><i class="fa-solid fa-download"></i> {{ __('Installer') }}</button><button id="scanBtn" class="net" style="border:0;cursor:pointer;background:var(--blue)" title="{{ __('Scanner un code-barres') }}"><i class="fa-solid fa-barcode"></i></button>{{-- Le panier s'ouvre d'ICI, à côté du scanner : les deux gestes de la
             caisse sont côte à côte, et l'écran reste entier pour le catalogue.
             Le compteur dit combien d'articles attendent — sans lui, un panier
             fermé est un panier qu'on oublie. --}}<button id="cartBtn" class="net cartbtn" style="border:0;cursor:pointer;background:var(--blue);position:relative" onclick="openCart()" title="{{ __('Voir le panier') }}"><i class="fa-solid fa-basket-shopping"></i><span id="cartN" style="display:none;position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;border-radius:999px;background:#E0473E;color:#fff;font:700 11px/18px var(--fh);text-align:center;padding:0 4px">0</span></button><span class="net" id="net">●</span>
            @if($staff)
                <span class="who" title="{{ $staff->role_label }}">{{ $staff->initials }}</span>
            @endif
            <a href="{{ route('tagtoa.pos.report',$terminal->id) }}"><i class="fa-solid fa-chart-simple"></i></a></div>

        {{-- Qui tient la caisse. Tant que le commerce n'a créé aucun employé,
             ce bandeau n'apparaît pas et la caisse fonctionne comme avant. --}}
        @if($hasStaff)
            <div class="poste">
                @if($staff)
                    <span><b>{{ $staff->name }}</b> · {{ $staff->role_label }}</span>
                    <form method="POST" action="{{ route('tagtoa.pos.staff.logout',$terminal->id) }}">@csrf
                        <button type="submit">{{ __('Fermer le poste') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('tagtoa.pos.staff.login',$terminal->id) }}">@csrf
                        <label for="pin">{{ __('Votre code') }}</label>
                        <input id="pin" name="pin" type="password" inputmode="numeric" autocomplete="off"
                               maxlength="6" pattern="[0-9]*" placeholder="••••" required>
                        <button type="submit">{{ __('Ouvrir') }}</button>
                    </form>
                @endif
                @if(session('error'))<span class="err">{{ session('error') }}</span>@endif
            </div>
        @endif
        @php($rayons = collect($sellable)->pluck('group')->filter()->unique()->sort()->values())
        @if($rayons->isNotEmpty())
            {{-- Quarante articles donnaient quarante boutons d'affilée : le
                 caissier cherchait à l'œil au moment où il a le moins de temps.
                 Les rayons existaient déjà côté back-office, invisibles ici. --}}
            <div class="rayons" id="rayons">
                <button type="button" class="on" data-rayon="">{{ __('Tout') }}</button>
                @foreach($rayons as $r)
                    <button type="button" data-rayon="{{ $r }}">{{ $r }}</button>
                @endforeach
            </div>
        @endif
        <div class="grid" id="grid">
            {{-- Boutons de la caisse ET articles du menu du commerce. Chaque
                 article porte sa référence d'origine (« menu:7 », « pos:7 ») :
                 le plat n°7 et le bouton n°7 sont deux choses différentes. --}}
            @foreach($sellable as $a)
                <button class="p" style="background:{{ $a['color'] }}"
                        data-ref="{{ $a['ref'] }}" data-name="{{ $a['name'] }}" data-price="{{ $a['price'] }}"
                        data-group="{{ $a['group'] }}"
                        @if($a['group']) title="{{ $a['group'] }}" @endif>
                    @if(!empty($a['image']))
                        {{-- Si la photo ne charge pas — lien /storage absent sur
                             le serveur, fichier effacé, réseau coupé — on
                             retombe sur l'initiale. Une icône « image cassée »
                             sur un bouton de caisse est pire que pas de photo :
                             elle ne se reconnaît pas d'un coup d'œil, et c'est
                             précisément ce qu'on demande au caissier en pleine
                             affluence. --}}
                        <img class="ph" src="{{ $a['image'] }}" alt="" loading="lazy"
                             data-initiale="{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a['name'], 0, 1)) }}"
                             onerror="tagtoaPhotoCassee(this)">
                    @else
                        <span class="ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($a['name'], 0, 1)) }}</span>
                    @endif
                    <span class="lb">
                        <span class="nm">{{ $a['name'] }}</span>
                        <span class="pr">{{ number_format($a['price'], 2) }}</span>
                    </span>
                    @if($a['stock'] !== null)<span class="st">{{ $a['stock'] }}</span>@endif
                </button>
            @endforeach
        </div>
        <div class="cart" id="cart">
            <h2 style="display:flex;align-items:center;gap:10px">
                <span style="flex:1">{{ __('Panier') }}</span>
                {{-- Visible seulement sur téléphone : sur grand écran le panier
                     est une colonne, elle ne se ferme pas. --}}
                <button class="cartbtn" onclick="closeCart()" aria-label="{{ __('Fermer') }}"
                        style="border:0;background:transparent;font-size:20px;cursor:pointer;color:#666;padding:0 2px">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </h2>
            <div class="lines" id="lines"><p style="color:#999;padding:14px;font-size:14px">{{ __('Touchez un produit') }}</p></div>
            <div class="tot">
                <div class="r"><span>{{ __('Sous-total') }}</span><span id="sub">0.00</span></div>
                <div class="r"><span>{{ __('Remise') }}</span><span><input id="disc" type="number" value="0" min="0" style="width:80px;text-align:right;border:1px solid var(--bd);border-radius:6px" oninput="render()"></span></div>
                <div class="r" id="taxrow" style="display:none"><span id="taxlbl">{{ __('Taxe') }}</span><span id="taxval">0.00</span></div>
                <div class="r g"><span>{{ __('Total') }}</span><span id="tot">0.00</span></div>
                <button class="pay" id="paybtn" onclick="openPay()" disabled><i class="fa-solid fa-credit-card"></i> {{ __('Encaisser') }}</button>
            </div>
        </div>
    </div>

    <div class="voile" id="voile" onclick="closeCart()"></div>

    <div class="modal" id="modal"><div class="sheet">
        <h3>{{ __('Paiement') }} — <span id="pt">0.00</span> {{ $terminal->currency }}</h3>
        <div class="methods" id="methods">@foreach($methods as $k=>$label)<div class="m" data-m="{{ $k }}" onclick="pickM('{{ $k }}',this)">{{ $label }}</div>@endforeach</div>
        <div class="split"><label><input type="checkbox" id="splitchk" onchange="render()"> {{ __('Paiement séparé (split)') }}</label><div id="splitbox" style="display:none;margin-top:6px"></div></div>
        <input class="field" id="phone" placeholder="{{ __('Téléphone client (reçu WhatsApp)') }}">
        <button class="pay" onclick="confirmSale()"><i class="fa-solid fa-check"></i> {{ __('Confirmer') }}</button>
        <button class="pay" style="background:#888;margin-top:6px" onclick="closePay()">{{ __('Annuler') }}</button>
    </div></div>

    <div class="done" id="done"><i class="fa-solid fa-circle-check"></i><h2 id="dref"></h2><p id="dtot"></p>
        <div class="acts"><a id="wa" target="_blank"><i class="fa-brands fa-whatsapp"></i> {{ __('Reçu') }}</a><button onclick="imprimerRecu()"><i class="fa-solid fa-print"></i> {{ __('Imprimer') }}</button><button onclick="newSale()"><i class="fa-solid fa-plus"></i> {{ __('Nouvelle') }}</button></div>
    </div>
<script>
/* Photo introuvable → l'initiale, sur la couleur du bouton. Fonction globale
   parce qu'elle est appelée depuis l'attribut onerror des images, qui est
   évalué AVANT que le reste du script soit descendu. */
function tagtoaPhotoCassee(img){
    var span = document.createElement('span');
    span.className = 'ph';
    span.textContent = img.getAttribute('data-initiale') || '';
    if (img.parentNode) { img.parentNode.replaceChild(span, img); }
}

var T=document.body.dataset.terminal,CUR=document.body.dataset.currency,CSRF=document.querySelector('meta[name=csrf-token]').content;
var SALE_URL="{{ route('tagtoa.pos.sale',$terminal->id) }}",SYNC_URL="{{ route('tagtoa.pos.sync',$terminal->id) }}",QKEY='tagtoa_pos_q_'+T;
// Le panier EN COURS (pas encore encaissé) survit à un rechargement ou une
// coupure de courant — fréquente là où cette caisse tourne. `QKEY` protège
// déjà la vente une fois ENVOYÉE ; ceci protège ce qui a été sonné avant.
var CARTKEY='tagtoa_pos_cart_'+T;
var cart={},method='cash';
try{cart=JSON.parse(localStorage.getItem(CARTKEY)||'{}');}catch(e){cart={};}
function sauvegarderPanier(){try{localStorage.setItem(CARTKEY,JSON.stringify(cart));}catch(e){}}

/* Le régime de taxe du commerce. La caisse s'en sert UNIQUEMENT pour
   annoncer le bon montant : avec des prix hors taxe, afficher le sous-total
   ferait annoncer moins que ce que le client paiera. Le calcul qui compte
   reste celui du serveur. */
var TAX = {
    on:        @json($tax->enabled),
    rate:      @json($tax->rate ?? 0),
    inclusive: @json($tax->inclusive),
    label:     @json($tax->enabled ? $tax->label() : null)
};
var TAUX_ARTICLE = {};
@foreach($sellable as $a)
    TAUX_ARTICLE[@json($a['ref'])] = @json($a['tax_rate']);
@endforeach

function tauxDe(ref){
    if(!TAX.on) return 0;
    var t = TAUX_ARTICLE[ref];
    return (t === null || t === undefined) ? TAX.rate : t;
}

/* Taxe du panier, remise répartie au prorata — même règle qu'au serveur.
   L'imputer sur une seule ligne changerait la taxe selon l'ordre des
   articles. */
function taxeDuPanier(){
    if(!TAX.on) return 0;

    var brut = sub();
    if(brut <= 0) return 0;

    var remise = Math.min(parseFloat(document.getElementById('disc').value) || 0, brut);
    var facteur = (brut - remise) / brut;
    var taxe = 0;

    for(var k in cart){
        var montant = Math.round(cart[k].price * cart[k].qty * facteur * 100) / 100;
        var taux = tauxDe(k);
        if(taux <= 0) continue;

        taxe += TAX.inclusive
            ? montant - Math.round(montant / (1 + taux/100) * 100) / 100
            : Math.round(montant * taux) / 100;
    }

    return Math.round(taxe * 100) / 100;
}
/* Panier indexé par RÉFÉRENCE : sans cela, le plat n°7 et le bouton n°7
   partageraient la même ligne et l'un écraserait l'autre. */
function add(ref,name,price){if(!cart[ref])cart[ref]={ref:ref,name:name,price:price,qty:0};cart[ref].qty++;beep('add');render();}
/* ------------------------------------------------------------------
   Scanner — vendre sans chercher l'article dans la grille.

   La table des codes est EMBARQUÉE avec le catalogue. La caisse doit
   continuer de vendre quand la connexion tombe, et c'est justement le
   moment où le commerçant ne peut pas se permettre de chercher.

   Le serveur n'est interrogé qu'en dernier recours, pour un code qu'on ne
   connaît pas encore (article créé sur une autre caisse il y a dix
   minutes). Hors ligne, ce recours n'existe pas : on le dit, on ne fait
   pas semblant.
   ------------------------------------------------------------------ */
var SCAN_URL = "{{ route('tagtoa.catalog.scan') }}";
var CREATE_URL = "{{ route('tagtoa.pos.products.scan', $terminal->id) }}";
var PAR_CODE = {};   // code -> {ref, name, price}

(function indexerLesCodes(){
    @foreach($sellable as $a)
        @foreach($a['codes'] as $c)
            PAR_CODE[@json($c)] = {ref:@json($a['ref']), name:@json($a['name']), price:{{ (float) $a['price'] }}};
        @endforeach
    @endforeach
})();

function nettoyerCode(c){ return String(c||'').toUpperCase().replace(/[^A-Z0-9\-]/g,''); }

/* Message court en haut de la grille : le caissier ne lit pas un roman
   entre deux clients. */
function direScan(texte, erreur){
    var el = document.getElementById('scanmsg');
    if(!el){
        el = document.createElement('div');
        el.id = 'scanmsg';
        el.style.cssText = 'grid-column:1/-1;padding:10px 12px;border-radius:10px;font-size:14px';
        var g = document.getElementById('grid');
        g.insertBefore(el, g.firstChild);
    }
    el.textContent = texte;
    el.style.background = erreur ? 'rgba(224,71,62,.12)' : 'rgba(44,184,9,.12)';
    el.style.color = erreur ? 'var(--red)' : '#1a7a05';
    clearTimeout(el._t);
    el._t = setTimeout(function(){ if(el.parentNode) el.parentNode.removeChild(el); }, 4000);
}

/* ------------------------------------------------------------------
   CE QUI SE PASSE QUAND UN CODE EST LU.

   Le défaut d'avant, signalé depuis un comptoir : « ça fait le son quand
   le code passe devant la caméra, puis plus rien ». L'article ÉTAIT
   ajouté — mais la caméra couvre tout l'écran, et le panier est
   désormais une fenêtre fermée. Rien de ce qui changeait n'était
   visible. Un travail fait sans preuve ressemble à un travail non fait,
   et le caissier rescanne, ou renonce.

   Deux corrections, et elles vont ensemble :

     • le scanner DIT lui-même ce qu'il vient d'ajouter, dans son propre
       écran, seul endroit que le caissier regarde à ce moment-là ;
     • un ajout réussi FERME la caméra et rend la main, comme demandé.
       On revoit alors le catalogue, le compteur du panier, et le bouton
       pour scanner le suivant.

   Un code INCONNU ne ferme rien : on reste caméra ouverte pour viser à
   nouveau, sinon il faudrait rouvrir le scanner après chaque étiquette
   abîmée.
   ------------------------------------------------------------------ */
function direDansScanner(texte, erreur){
    if(window.TagtoaScanner && TagtoaScanner.isOpen && TagtoaScanner.isOpen()){
        TagtoaScanner.say(texte, !!erreur);
        return true;
    }
    return false;
}

/** Article trouvé : on l'ajoute, on le dit, et on rend la main. */
function ajouterEtRendreLaMain(ref, name, price, note){
    add(ref, name, price);

    var texte = '\u2713 ' + name + (note ? ' — ' + note : '');

    if(direDansScanner(texte)){
        // Le message s'affiche, PUIS l'écran se ferme : fermer d'abord
        // effacerait la seule confirmation que le caissier aura vue.
        setTimeout(function(){
            if(window.TagtoaScanner) TagtoaScanner.close();
            direScan(texte);
        }, 600);
        return;
    }

    // Douchette USB, ou saisie hors scanner : rien à fermer.
    direScan(texte);
}

function vendreParCode(code){
    code = nettoyerCode(code);
    if(code.length < 4) return;

    var a = PAR_CODE[code];
    if(a){ ajouterEtRendreLaMain(a.ref, a.name, a.price); return; }

    if(!navigator.onLine){
        beep('error');
        var horsLigne = @js(__('Code inconnu de cette caisse, et pas de connexion pour vérifier.'));
        if(!direDansScanner(horsLigne, true)) direScan(horsLigne, true);
        return;
    }

    // Le serveur met un instant à répondre : on le dit, sinon l'attente
    // ressemble exactement à la panne qu'on vient de corriger.
    direDansScanner(@js(__('Recherche…')));

    fetch(SCAN_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:JSON.stringify({code:code})})
      .then(function(r){return r.json();})
      .then(function(d){
          if(d && d.found){
              // Retenu pour la suite de la journée : le même article repasse
              // souvent à la caisse.
              PAR_CODE[code] = {ref:d.article.ref, name:d.article.name, price:d.article.price};
              ajouterEtRendreLaMain(d.article.ref, d.article.name, d.article.price,
                  d.article.out ? @js(__('stock épuisé')) : null);
              return;
          }
          // Code inconnu de tout le catalogue (POS + Menu) : on ne devine
          // JAMAIS son prix — mais on n'abandonne pas non plus le caissier
          // devant un bip qui ne sert à rien.
          creerArticlePourCode(code);
      })
      .catch(function(){
          beep('error');
          var rate = @js(__('Vérification impossible. Touchez l\'article dans la grille.'));
          if(!direDansScanner(rate, true)) direScan(rate, true);
      });
}

/* ------------------------------------------------------------------
   CODE VRAIMENT INCONNU : on crée un article provisoire — inactif, sans
   prix — plutôt que de renvoyer le caissier les mains vides. Même geste
   qu'à la réception d'un carton (voir PosController::scanProduct), mais
   déclenché depuis la caisse elle-même : la caméra RESTE ouverte pour
   enchaîner sur le code suivant.

   L'article créé n'est JAMAIS ajouté au panier : il est inactif et à prix
   zéro tant que personne ne l'a rempli — l'encaisser tel quel encaisserait
   zéro gourde. Le serveur refuse en silence (403) si le caissier connecté
   n'a pas le droit de toucher au catalogue ; on retombe alors sur le
   message « code inconnu » ordinaire, sans rien créer.
   ------------------------------------------------------------------ */
function creerArticlePourCode(code){
    fetch(CREATE_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},
        body:JSON.stringify({code:code})})
      .then(function(r){ if(!r.ok) throw 0; return r.json(); })
      .then(function(d){
          beep('success');
          var texte = (d && d.message) ? d.message
              : @js(__('Article créé. Donnez-lui un nom et un prix dans Produits.'));
          if(!direDansScanner(texte)) direScan(texte);
      })
      .catch(function(){
          beep('error');
          if(window.TagtoaScanner) TagtoaScanner.reject();
          var inconnu = @js(__('Code inconnu : ')) + code;
          if(!direDansScanner(inconnu, true)) direScan(inconnu, true);
      });
}

window.addEventListener('load', function(){
    if(!window.TagtoaScanner) return;

    // La douchette USB/Bluetooth marche sans rien ouvrir : c'est le matériel
    // le plus courant derrière un comptoir.
    TagtoaScanner.listenWedge(vendreParCode);

    var b = document.getElementById('scanBtn');
    if(b) b.addEventListener('click', function(){
        TagtoaScanner.open({
            onCode: vendreParCode,
            title:  @js(__('Scanner pour vendre')),
            // Le texte dit ce qui va RÉELLEMENT se passer. Annoncer « chaque
            // lecture ajoute au panier » alors que l'écran se referme après la
            // première fabriquait la surprise que le caissier prenait pour une
            // panne.
            hint:   @js(__('Visez le code-barres. L\'article s\'ajoute et l\'écran se referme.')),
            submit: @js(__('Ajouter'))
        });
    });
});

document.querySelectorAll('.grid .p').forEach(function(b){
    b.addEventListener('click',function(){add(this.dataset.ref,this.dataset.name,parseFloat(this.dataset.price));});
});

/* Filtrer par rayon — entièrement côté client, comme le reste de la caisse :
   fonctionne hors ligne, sans un aller-retour au serveur par onglet touché. */
(function(){
    var barre = document.getElementById('rayons');
    if(!barre) return;
    var boutons = barre.querySelectorAll('button'),
        articles = document.querySelectorAll('.grid .p');
    barre.addEventListener('click', function(e){
        var b = e.target.closest('button');
        if(!b) return;
        boutons.forEach(function(x){x.classList.toggle('on', x === b);});
        var rayon = b.dataset.rayon;
        articles.forEach(function(a){
            a.style.display = (!rayon || a.dataset.group === rayon) ? '' : 'none';
        });
    });
})();
function chg(id,d){if(cart[id]){cart[id].qty+=d;if(cart[id].qty<=0)delete cart[id];render();}}
function sub(){var s=0;for(var k in cart)s+=cart[k].price*cart[k].qty;return s;}
/* Ce que le client va payer. Prix taxe comprise : la taxe est déjà dedans.
   Prix hors taxe : elle s'ajoute, et c'est CE montant que le caissier
   annonce — sinon il annoncerait moins que ce qui sera encaissé. */
function total(){
    var net = Math.max(0, sub() - (parseFloat(document.getElementById('disc').value) || 0));

    return TAX.on && !TAX.inclusive ? Math.round((net + taxeDuPanier()) * 100) / 100 : net;
}
function render(){var L=document.getElementById('lines'),ks=Object.keys(cart);
    L.innerHTML=ks.length?ks.map(function(k){var c=cart[k];return '<div class="ln"><div class="nm">'+c.name+'<small>'+c.price.toFixed(2)+'</small></div><div class="q"><button onclick="chg(\''+k+'\',-1)">−</button> '+c.qty+' <button onclick="chg(\''+k+'\',1)">+</button></div></div>';}).join(''):'<p style="color:#999;padding:14px;font-size:14px">{{ __('Touchez un produit') }}</p>';
    document.getElementById('sub').textContent=sub().toFixed(2);document.getElementById('tot').textContent=total().toFixed(2);document.getElementById('pt').textContent=total().toFixed(2);document.getElementById('paybtn').disabled=!ks.length;
    // Le compteur sur l'icône. Panier fermé, c'est le SEUL signe qu'un article
    // est entré : sans lui, on rescanne le même article en croyant l'avoir raté.
    var n=0;for(var kk in cart)n+=cart[kk].qty;
    var bulle=document.getElementById('cartN');
    if(bulle){bulle.textContent=n;bulle.style.display=n?'block':'none';}
    // Le dernier article retiré referme le panier : garder un tiroir vide
    // ouvert par-dessus le catalogue n'aide personne.
    if(!ks.length) closeCart();
    // La taxe se voit AVANT d'encaisser : un client qui découvre 10 % de plus
    // au moment de payer, c'est une discussion au comptoir.
    var lt=document.getElementById('taxrow');
    if(lt){var t=taxeDuPanier();lt.style.display=(TAX.on&&t>0)?'flex':'none';document.getElementById('taxval').textContent=t.toFixed(2);}
    var sb=document.getElementById('splitbox'),on=document.getElementById('splitchk').checked;sb.style.display=on?'block':'none';if(on&&!sb.innerHTML)sb.innerHTML='{{ __('MonCash') }}: <input type="number" id="sp1" value="0"> · {{ __('Cash') }}: <input type="number" id="sp2" value="0">';
    sauvegarderPanier();}
if(TAX.label){var _l=document.getElementById('taxlbl');if(_l)_l.textContent=TAX.label;}
function pickM(m,el){method=m;document.querySelectorAll('.m').forEach(function(x){x.classList.remove('on');});el.classList.add('on');}
/* ------------------------------------------------------------------
   Le panier s'ouvre et se ferme. Sur grand écran il ne bouge pas : la
   règle est dans la feuille de style, pas ici — deux endroits qui
   décident de la même chose finissent par se contredire.
   ------------------------------------------------------------------ */
function openCart(){document.getElementById('cart').classList.add('show');document.getElementById('voile').classList.add('show');}
function closeCart(){document.getElementById('cart').classList.remove('show');document.getElementById('voile').classList.remove('show');}

function openPay(){closeCart();document.getElementById('modal').classList.add('show');}function closePay(){document.getElementById('modal').classList.remove('show');}
function payments(){if(document.getElementById('splitchk').checked){var a=parseFloat((document.getElementById('sp1')||{}).value||0),b=parseFloat((document.getElementById('sp2')||{}).value||0);return [{method:'moncash',amount:a},{method:'cash',amount:b}];}return [{method:method,amount:total()}];}
function uuid(){return 'pxxxxxxxxyxx'.replace(/[xy]/g,function(c){var r=Math.random()*16|0;return (c==='x'?r:(r&0x3|0x8)).toString(16);})+Date.now();}
/* Le retour sonore passe par le module commun de TAGTOA : même son à la
   caisse, au menu du client et partout ailleurs, et surtout ASSEZ FORT.
   L'ancien gain de 0,1 s'entendait dans un bureau silencieux, pas dans une
   salle pleine — c'est-à-dire jamais au moment où il sert.
   Repli sur l'ancienne synthèse si le module n'a pas pu se charger : une
   caisse ne doit pas devenir muette parce qu'un fichier manque. */
var actx;
function beep(t){
    if (window.TagtoaSound) {
        if (t === 'add') return TagtoaSound.add();
        if (t === 'success') return TagtoaSound.ok();
        return TagtoaSound.error();
    }
    try{actx=actx||new (window.AudioContext||window.webkitAudioContext)();var o=actx.createOscillator(),g=actx.createGain();o.connect(g);g.connect(actx.destination);var f={add:660,success:[880,1320],error:[200,160]}[t];o.frequency.value=Array.isArray(f)?f[0]:f;o.type='sine';g.gain.value=.3;o.start();if(Array.isArray(f))setTimeout(function(){o.frequency.value=f[1];},80);setTimeout(function(){o.stop();},t==='error'?240:150);}catch(e){}
}
function setNet(){var on=navigator.onLine;document.getElementById('net').textContent=on?'● online':'● offline';document.getElementById('net').classList.toggle('off',!on);if(on)flush();}
window.addEventListener('online',setNet);window.addEventListener('offline',setNet);
// Filet : certains navigateurs ne déclenchent pas 'online' de façon fiable
// (même règle que la file d'attente du menu public) — sans lui, une vente
// en attente pourrait rester bloquée bien après le retour réel du réseau.
setInterval(flush,20000);
function q(){return JSON.parse(localStorage.getItem(QKEY)||'[]');}function setQ(a){localStorage.setItem(QKEY,JSON.stringify(a));}
function flush(){var a=q();if(!a.length)return;fetch(SYNC_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify({sales:a})}).then(function(r){return r.json();}).then(function(){setQ([]);}).catch(function(){});}
function confirmSale(){var p={items:Object.values(cart),discount:parseFloat(document.getElementById('disc').value)||0,payments:payments(),customer_phone:document.getElementById('phone').value,client_uuid:uuid()};
    if(navigator.onLine){fetch(SALE_URL,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':CSRF},body:JSON.stringify(p)}).then(function(r){return r.json();}).then(function(d){ok(d.reference,p,d);}).catch(function(){off(p);});}else off(p);}
function off(p){var a=q();a.push(p);setQ(a);ok(p.client_uuid.substr(0,8).toUpperCase()+' ({{ __('hors-ligne') }})',p);}
function ok(ref,p,srv){beep('success');closePay();document.getElementById('dref').textContent=ref;
    // La référence n'est retenue que si le SERVEUR l'a donnée : hors ligne, la
    // vente n'existe pas encore en base et son reçu n'est pas imprimable.
    derniereRef=(srv&&srv.reference)?srv.reference:null;
    // Le montant qui s'affiche est celui que le SERVEUR a enregistré. Hors
    // ligne il n'y en a pas encore : on montre le calcul local, qui est le
    // même tant que le catalogue n'a pas changé.
    var montant=(srv&&srv.total!=null)?srv.total:total();
    document.getElementById('dtot').textContent=Number(montant).toFixed(2)+' '+CUR
        +((srv&&srv.tax>0)?'  ('+(srv.tax_label||'{{ __('Taxe') }}')+' '+Number(srv.tax).toFixed(2)+')':'');
    var lines=Object.values(cart).map(function(c){return c.qty+'x '+c.name+' = '+(c.qty*c.price).toFixed(2);}).join('%0A');
    var msg='{{ __('Reçu') }} TAGTOA%0A'+ref+'%0A'+lines+'%0A{{ __('Total') }}: '+total().toFixed(2)+' '+CUR;
    document.getElementById('wa').href='https://wa.me/'+(p.customer_phone||'').replace(/[^0-9]/g,'')+'?text='+msg;document.getElementById('done').classList.add('show');}
/* ------------------------------------------------------------------
   IMPRIMER — le vrai reçu, pas l'écran de confirmation.

   `window.print()` imprimait CETTE page : une feuille A4 presque blanche
   avec les boutons dessus, sur deux pages. Le reçu de caisse est une page
   à part, calée sur 58 mm, qui porte l'en-tête du commerce, toutes les
   lignes et le mot du patron.

   Hors ligne, la vente n'est pas encore en base : il n'y a rien à
   imprimer, et on le dit plutôt que d'ouvrir une page en erreur. Le reçu
   s'imprimera depuis Tickets une fois la caisse resynchronisée.
   ------------------------------------------------------------------ */
var RECU_URL="{{ route('tagtoa.pos.receipt', ['reference' => '__REF__']) }}";
var derniereRef=null;

function imprimerRecu(){
    if(!derniereRef){
        beep('error');
        alert(@js(__('Vente enregistrée hors ligne : le reçu s\'imprimera depuis Tickets dès le retour du réseau.')));
        return;
    }
    window.open(RECU_URL.replace('__REF__', encodeURIComponent(derniereRef)) + '?print=1', '_blank');
}

function newSale(){cart={};document.getElementById('disc').value=0;document.getElementById('phone').value='';document.getElementById('done').classList.remove('show');render();}
window.addEventListener('load',function(){setNet();render();});

// --- PWA : service worker + invite d'installation ---
if('serviceWorker' in navigator){
    navigator.serviceWorker.register("{{ route('tagtoa.pos.sw') }}", {scope:"{{ rtrim(url('/tagtoa/pos'),'/') }}/"}).catch(function(){});
}
var deferredPrompt=null;
window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferredPrompt=e;var b=document.getElementById('installBtn');if(b)b.style.display='inline-block';});
(function(){var b=document.getElementById('installBtn');if(b)b.addEventListener('click',function(){if(!deferredPrompt)return;deferredPrompt.prompt();deferredPrompt.userChoice.finally(function(){deferredPrompt=null;b.style.display='none';});});})();
window.addEventListener('appinstalled',function(){var b=document.getElementById('installBtn');if(b)b.style.display='none';});
</script>
</body>
</html>
