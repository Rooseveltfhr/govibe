{{-- TAGTOA MENU — page publique (NFC/QR). Variables : $menu, $categories --}}
@php
    $dark = $menu->theme === 'dark';
    $accent = preg_match('/^#[0-9A-Fa-f]{3,8}$/', (string) $menu->accent_color) ? $menu->accent_color : '#2cb809';
    $bg   = $dark ? '#0A0A0A' : '#F5F5F3';
    $fg   = $dark ? '#FFFFFF' : '#0A0A0A';
    $surf = $dark ? '#161616' : '#FFFFFF';
    $mut  = $dark ? 'rgba(255,255,255,.6)' : '#888888';
    $bd   = $dark ? 'rgba(255,255,255,.10)' : 'rgba(0,0,0,.08)';
    $tm   = $menu->type_meta;
    $canOrder = $menu->ordering_enabled && $menu->whatsapp_digits;
    $cur = $menu->currency ?: 'HTG';
    $ouvert = $menu->isOpenNow();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $menu->name }} — TAGTOA Menu</title>
    {{-- Installable + hors ligne : une connexion mauvaise ou coupée ne doit
         pas empêcher de rouvrir une carte déjà vue. --}}
    <link rel="manifest" href="{{ route('tagtoa.menu.manifest', $menu->alias) }}">
    <link rel="apple-touch-icon" href="{{ route('tagtoa.menu.icon', $menu->alias) }}">
    <meta name="theme-color" content="{{ $accent }}">
    <link rel="stylesheet" href="{{ route('tagtoa.asset', 'tagtoa-fonts.css') }}">
    <link rel="stylesheet" href="/tagtoa-asset/fontawesome-6.5.1.css">
    {{-- Le retour sonore : sons synthétisés, aucun fichier à télécharger — donc
         ils fonctionnent encore quand la connexion est mauvaise, c'est-à-dire
         précisément quand le client doute que son geste soit passé. --}}
    <script src="/tagtoa-asset/tagtoa-sound.js" defer></script>
    <style>
        :root{
            --acc:{{ $accent }};--bg:{{ $bg }};--fg:{{ $fg }};--surf:{{ $surf }};--mut:{{ $mut }};--bd:{{ $bd }};
            --fh:'Space Grotesk',sans-serif;--fb:'Nunito',sans-serif;
        }
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:var(--fb);background:var(--bg);color:var(--fg);min-height:100vh;-webkit-font-smoothing:antialiased}
        a{text-decoration:none;color:inherit}
        .wrap{max-width:560px;margin:0 auto;padding-bottom:120px}
        /* Header */
        .cover{height:180px;background:linear-gradient(150deg,var(--acc),#0A0A0A);position:relative;background-size:cover;background-position:center;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .cover::after{content:"";position:absolute;inset:0;background:linear-gradient(to top,rgba(0,0,0,.45),transparent 60%)}
        .cover-fallback{font-size:96px;color:rgba(255,255,255,.22)}
        .head{padding:0 20px;margin-top:-44px;position:relative;z-index:2}
        .logo{width:84px;height:84px;border-radius:20px;border:3px solid var(--surf);background:var(--surf);object-fit:cover;display:flex;align-items:center;justify-content:center;font:700 30px var(--fh);color:var(--acc);box-shadow:0 8px 26px rgba(0,0,0,.18)}
        .title{font:700 24px var(--fh);margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
        .badge-type{display:inline-flex;align-items:center;gap:6px;font:600 12px var(--fh);background:color-mix(in srgb,var(--acc) 16%,transparent);color:var(--acc);padding:4px 10px;border-radius:999px}
        .tag{color:var(--mut);font-size:14.5px;margin-top:4px}
        .meta{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;color:var(--mut);font-size:13.5px}
        .meta a{display:inline-flex;align-items:center;gap:6px}.meta i{color:var(--acc)}
        /* Category nav */
        .catnav{position:sticky;top:0;z-index:30;background:color-mix(in srgb,var(--bg) 85%,transparent);backdrop-filter:blur(10px);border-bottom:1px solid var(--bd);padding:12px 16px;display:flex;gap:8px;overflow-x:auto;margin-top:18px;-ms-overflow-style:none;scrollbar-width:none}
        .catnav::-webkit-scrollbar{display:none}
        .chip{white-space:nowrap;font:600 13.5px var(--fh);color:var(--fg);background:var(--surf);border:1px solid var(--bd);padding:8px 14px;border-radius:999px;cursor:pointer}
        .chip.on{background:var(--acc);color:#fff;border-color:transparent}
        /* Sections */
        .sec{padding:22px 16px 4px}
        .sec h2{font:700 18px var(--fh);display:flex;align-items:center;gap:9px;margin-bottom:14px}
        /* ── LA VITRINE ──────────────────────────────────────────────────
           Une GRILLE de cartes, plus une liste de lignes.

           Une liste montre deux plats par écran de téléphone : le client fait
           défiler, se lasse, et commande ce qu'il a vu en premier. Une grille
           en montre six, avec la photo en grand — et c'est la photo qui fait
           commander, pas le nom.

           Deux colonnes sur téléphone (172 px de carte : assez pour une photo
           lisible), et autant que la largeur le permet ensuite. Personne ne
           choisit le nombre de colonnes : c'est la place disponible qui décide. */
        .grille{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
        @media(min-width:560px){.grille{grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px}}
        .item{display:flex;flex-direction:column;background:var(--surf);border:1px solid var(--bd);
              border-radius:16px;overflow:hidden;position:relative}
        /* La photo d'abord, en 4:3 : le format qui montre une assiette entière
           sans couper les bords, contrairement au carré. */
        .item .ph{width:100%;aspect-ratio:4/3;object-fit:cover;display:flex;align-items:center;
              justify-content:center;font:700 30px var(--fh);
              background:color-mix(in srgb,var(--acc) 12%,var(--surf));color:var(--acc)}
        .item .body{padding:11px 12px 12px;display:flex;flex-direction:column;flex:1;min-width:0}
        /* Description : DEUX lignes, jamais trois. Au-delà, le prix descend
           sous le pli et la carte cesse de vendre. */
        .item .ds{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .item .ft{margin-top:auto}
        .item .add{position:static}
        .item .nm{font:700 14.5px var(--fh);display:flex;align-items:center;gap:6px;flex-wrap:wrap;line-height:1.3}
        .pillb{font:700 10.5px var(--fh);background:var(--acc);color:#fff;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em}
        .item .ds{color:var(--mut);font-size:12.5px;margin-top:4px;line-height:1.45;min-height:2.9em}
        .item .ft{display:flex;align-items:center;justify-content:space-between;margin-top:10px;gap:8px}
        .item .specs{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
        .item .spec{font-size:12px;color:var(--mut);background:color-mix(in srgb,var(--acc) 8%,var(--surf));
                    border-radius:999px;padding:3px 10px;line-height:1.5}
        .item .spec b{font-weight:700;color:inherit}
        .item .psuf{font-weight:500;font-size:12px;color:var(--mut);margin-left:2px}
        .price{font:700 15px var(--fh);color:var(--acc)}
        .add{border:0;background:var(--acc);color:#fff;width:36px;height:36px;border-radius:11px;font-size:16px;cursor:pointer;flex:0 0 auto;transition:transform .12s}
        .add:active{transform:scale(.9)}
        .foot{text-align:center;margin:34px 0 10px;color:var(--mut);font-size:12px}.foot b{font-family:var(--fh);color:var(--fg)}
        /* Cart bar + drawer */
        .cartbar{position:fixed;left:0;right:0;bottom:0;z-index:40;display:none;justify-content:center;padding:14px 16px calc(14px + env(safe-area-inset-bottom))}
        .cartbar.show{display:flex}
        .cartbar button{width:100%;max-width:528px;border:0;background:var(--acc);color:#fff;border-radius:15px;padding:15px 20px;font:700 15.5px var(--fh);display:flex;align-items:center;justify-content:space-between;cursor:pointer;box-shadow:0 10px 30px rgba(0,0,0,.25)}
        .cartbar .cnt{background:rgba(255,255,255,.22);border-radius:999px;padding:2px 10px;font-size:13px}
        /* Agent IA (mots-clés locaux, voir OrderChatParser) */
        .ai-fab{position:fixed;right:16px;bottom:calc(96px + env(safe-area-inset-bottom));z-index:41;
                width:54px;height:54px;border-radius:50%;border:0;background:var(--acc);color:#fff;font-size:21px;
                box-shadow:0 10px 26px rgba(0,0,0,.28);cursor:pointer;display:flex;align-items:center;justify-content:center}
        .ai-msg{max-width:85%;padding:10px 13px;border-radius:14px;font-size:14px;line-height:1.4}
        .ai-msg.me{align-self:flex-end;background:var(--acc);color:#fff;border-bottom-right-radius:4px}
        .ai-msg.bot{align-self:flex-start;background:color-mix(in srgb,var(--acc) 10%,var(--surf));border-bottom-left-radius:4px}
        .sheet{position:fixed;inset:0;z-index:60;display:none}
        .sheet.show{display:block}
        .sheet .ov{position:absolute;inset:0;background:rgba(0,0,0,.5)}
        .sheet .pan{position:absolute;left:0;right:0;bottom:0;max-width:560px;margin:0 auto;background:var(--bg);border-radius:22px 22px 0 0;padding:18px 18px calc(18px + env(safe-area-inset-bottom));max-height:84vh;overflow:auto}
        .sheet h3{font:700 18px var(--fh);margin-bottom:6px;display:flex;justify-content:space-between;align-items:center}
        .sheet .x{background:none;border:0;color:var(--mut);font-size:22px;cursor:pointer}
        .crow{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--bd)}
        .crow .cn{flex:1;font:600 14.5px var(--fh)}
        .crow .cp{color:var(--mut);font-size:13px}
        .qty{display:flex;align-items:center;gap:10px}
        .qty button{width:30px;height:30px;border-radius:9px;border:1px solid var(--bd);background:var(--surf);color:var(--fg);font-size:15px;cursor:pointer}
        .qty span{min-width:18px;text-align:center;font:700 14px var(--fh)}
        .tot{display:flex;justify-content:space-between;font:700 17px var(--fh);margin:16px 0}
        .cta{display:flex;flex-direction:column;gap:10px}
        .cta a,.cta button{width:100%;border:0;border-radius:14px;padding:15px;font:700 15px var(--fh);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px}
        .cta .wa{background:#25D366;color:#fff}
        .cta .pay{background:var(--acc);color:#fff}
        .cta .clr{background:transparent;color:var(--mut)}
        .empty{color:var(--mut);text-align:center;padding:30px 0}
        .custf{display:flex;flex-direction:column;gap:8px;margin:6px 0 14px}
        .cin{width:100%;padding:12px 14px;border:1px solid var(--bd);border-radius:11px;font:15px var(--fb);background:var(--surf);color:var(--fg)}
        .cin:focus{outline:0;border-color:var(--acc)}
        .cta button:disabled{opacity:.6;cursor:default}
        /* Type de commande + pourboire */
        .otype{display:flex;gap:8px;margin:6px 0 14px}
        .otbtn{flex:1;border:1px solid var(--bd);background:var(--surf);color:var(--fg);border-radius:11px;padding:10px 6px;font:600 12.5px var(--fh);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px}
        .otbtn.on{background:var(--acc);color:#fff;border-color:transparent}
        .tiprow{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap}
        .tiplbl{font:600 13.5px var(--fh);color:var(--mut)}
        .tipbtns{display:flex;gap:6px}
        .tipbtn{border:1px solid var(--bd);background:var(--surf);color:var(--fg);border-radius:9px;padding:7px 12px;font:700 12.5px var(--fh);cursor:pointer}
        .tipbtn.on{background:var(--acc);color:#fff;border-color:transparent}
        /* Options de plat (modificateurs) */
        .optgrp{margin-bottom:14px}
        .optgrp .oh{font:700 14px var(--fh);margin-bottom:8px;display:flex;align-items:center;gap:6px}
        .optgrp .req{font-size:10.5px;color:#fff;background:var(--acc);border-radius:999px;padding:1px 8px;text-transform:uppercase}
        .optchoice{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd);font-size:14.5px;cursor:pointer}
        .optchoice:last-child{border-bottom:0}
        .optchoice input{margin-right:10px}
        .optchoice .od{color:var(--mut);font-size:13px}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
<div style="position:fixed;top:12px;right:12px;z-index:50">@include('tagtoa::partials.lang', ['onlyCodes' => \Modules\Tagtoa\App\Support\Locale::forMenu($menu->languages)])</div>
<div class="wrap">
    {{-- Sans couverture envoyée, jamais un bandeau vide : l'icône du métier
         (restaurant, bar, hôtel…) sert de couverture par défaut, en filigrane
         sur le dégradé — aucune photo de stock à charger, ça marche même hors
         ligne. --}}
    <div class="cover" @if($menu->cover_url) style="background-image:url('{{ $menu->cover_url }}')" @endif>
        @unless($menu->cover_url)<i class="cover-fallback {{ $tm['icon'] }}" aria-hidden="true"></i>@endunless
    </div>
    <div class="head">
        @if($menu->logo_url)<img class="logo" src="{{ $menu->logo_url }}" alt="">
        @else<div class="logo">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($menu->name,0,1)) }}</div>@endif
        <div class="title">{{ $menu->name }} <span class="badge-type"><i class="{{ $tm['icon'] }}"></i> {{ __($tm['label']) }}</span>
            {{-- Le commerce choisit d'afficher ses horaires (show_hours) ; sans
                 lui, une commande hors plage reste refusée, mais on n'affiche
                 pas « Fermé » à un visiteur pour un menu jamais configuré. --}}
            @if($menu->show_hours && $menu->hours)
                <span class="badge-type" style="{{ $ouvert ? '' : 'background:color-mix(in srgb,#e11 16%,transparent);color:#e11' }}">
                    <i class="fa-solid fa-clock"></i> {{ $ouvert ? __('Ouvert maintenant') : __('Fermé maintenant') }}
                </span>
            @endif
        </div>
        @if($menu->translated('tagline'))<div class="tag">{{ $menu->translated('tagline') }}</div>@endif
        <div class="meta">
            @if($menu->address)<span><i class="fa-solid fa-location-dot"></i> {{ $menu->address }}</span>@endif
            @if($menu->phone)<a href="tel:{{ $menu->phone }}"><i class="fa-solid fa-phone"></i> {{ $menu->phone }}</a>@endif
            @if($menu->whatsapp_digits)<a href="https://wa.me/{{ $menu->whatsapp_digits }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>@endif
        </div>
        @if($menu->show_hours && $menu->hours)
            <details style="margin-top:10px">
                <summary style="cursor:pointer;color:var(--mut);font-size:13.5px"><i class="fa-solid fa-clock"></i> {{ __('Horaires') }}</summary>
                <div style="margin-top:8px;display:flex;flex-direction:column;gap:3px;font-size:13.5px;color:var(--mut)">
                    @foreach(\Modules\Tagtoa\App\Support\Menu\BusinessHours::DAYS as $jour)
                        @php $joursLabels = ['mon'=>__('Lundi'),'tue'=>__('Mardi'),'wed'=>__('Mercredi'),'thu'=>__('Jeudi'),'fri'=>__('Vendredi'),'sat'=>__('Samedi'),'sun'=>__('Dimanche')]; @endphp
                        <div style="display:flex;justify-content:space-between;gap:12px;max-width:280px">
                            <span>{{ $joursLabels[$jour] }}</span>
                            <span>{{ \Modules\Tagtoa\App\Support\Menu\BusinessHours::rangeLabel($menu->hours, $jour) }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
        @if($menu->translated('description'))<p class="tag" style="margin-top:12px">{{ $menu->translated('description') }}</p>@endif
    </div>

    @if($categories->isEmpty())
        <div class="sec"><div class="empty"><i class="fa-solid fa-utensils" style="font-size:30px;display:block;margin-bottom:10px;opacity:.4"></i>{{ __('Le menu arrive bientôt.') }}</div></div>
    @else
        <nav class="catnav" id="catnav">
            {{-- Icône + nom, jamais un emoji : un emoji se dessine autrement
                 sur chaque téléphone et tombe en carré blanc sur beaucoup
                 d'Android bon marché — juste à côté du nom du restaurant. --}}
            @foreach($categories as $c)
                {{-- L'ICÔNE se déduit du nom de BASE, jamais de la traduction :
                     un mot-clé anglais ne doit pas soudain changer l'icône
                     d'une catégorie déjà réglée dans la langue du marchand. --}}
                <div class="chip" data-target="cat{{ $c->id }}">
                    <i class="fa-solid {{ \Modules\Tagtoa\App\Support\Menu\CategoryIcon::resolve($c->icon, $c->name) }}"></i>
                    {{ $c->translated() }}
                </div>
            @endforeach
        </nav>

        @php
            // Suffixe de prix propre au métier (« / nuit » pour un hôtel) :
            // calculé une seule fois, pas à chaque article.
            $priceSuffix = \Modules\Tagtoa\App\Support\Menu\BusinessProfile::priceSuffix($menu->type);
        @endphp
        @foreach($categories as $c)
            <section class="sec" id="cat{{ $c->id }}">
                <h2>
                    <i class="fa-solid {{ \Modules\Tagtoa\App\Support\Menu\CategoryIcon::resolve($c->icon, $c->name) }}"></i>
                    {{ $c->translated() }}
                </h2>
                <div class="grille">
                @foreach($c->availableItems as $it)
                    {{-- Un seul nom résolu, réutilisé partout dans la carte :
                         demander la traduction cinq fois de suite pour le même
                         article gaspillerait du calcul sans rien changer au
                         résultat. --}}
                    @php $out = ! $it->in_stock; $itNom = $it->translated('name'); $itDesc = $it->translated('description'); @endphp
                    <div class="item" @if($out) style="opacity:.55" @endif>
                        {{-- La photo d'abord : c'est elle qui fait commander,
                             pas le nom. À défaut, l'initiale du plat sur la
                             couleur du commerce — jamais un emoji, qui tombe en
                             carré blanc sur la moitié des téléphones. --}}
                        @if($it->image_url)<img class="ph" src="{{ $it->image_url }}" alt="{{ $itNom }}" loading="lazy">
                        @else<div class="ph">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($itNom, 0, 1)) }}</div>@endif
                        <div class="body">
                            <div class="nm">{{ $itNom }} @if($it->badge)<span class="pillb">{{ $it->badge }}</span>@endif @if($out)<span class="pillb" style="background:var(--mut)">{{ __('Épuisé') }}</span>@endif</div>
                            @if($itDesc)<div class="ds">{{ $itDesc }}</div>@endif
                            {{-- Détails propres au métier : capacité et équipements d'une
                                 chambre, degré d'alcool d'une boisson, temps de préparation
                                 d'un plat. C'est ce qui permet au client de comparer. --}}
                            @php $specs = \Modules\Tagtoa\App\Support\Menu\BusinessProfile::display($menu->type, $it->specs); @endphp
                            @if($specs)
                                <div class="specs">
                                    @foreach($specs as $sp)
                                        <span class="spec"><b>{{ __($sp['label']) }}</b> {{ $sp['value'] }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <div class="ft">
                                @if($menu->show_prices)
                                    <span class="price">
                                        {{ \Modules\Tagtoa\App\Support\Money::format($it->price, $cur) }}
                                        @if($priceSuffix)<small class="psuf">{{ $priceSuffix }}</small>@endif
                                    </span>
                                @else<span></span>@endif
                                @if($canOrder && ! $out)
                                    @php
                                        $opts = $it->options->map(fn ($o) => [
                                            'id' => $o->id, 'name' => $o->name, 'required' => $o->required, 'multiple' => $o->multiple,
                                            'choices' => $o->choices->map(fn ($c) => ['id' => $c->id, 'label' => $c->label, 'price_delta' => (float) $c->price_delta])->values(),
                                        ])->values();
                                    @endphp
                                    <button class="add" aria-label="{{ __('Ajouter') }} — {{ $itNom }}" data-id="{{ $it->id }}" data-name="{{ $itNom }}" data-price="{{ (float) $it->price }}" data-options='@json($opts)' onclick="add(this)"><i class="fa-solid fa-plus"></i></button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                </div>
            </section>
        @endforeach
    @endif

    @include('tagtoa::partials.reviews', ['subjectType' => 'menu', 'subjectId' => $menu->id, 'subjectAlias' => $menu->alias, 'reviews' => $reviews, 'summary' => $summary])

    <div class="foot">{{ __('Propulsé par') }} <b>TAGTOA</b> · tagtoa.com</div>
</div>

@if($canOrder)
    <div class="cartbar" id="cartbar">
        <button onclick="openCart()"><span><i class="fa-solid fa-bag-shopping"></i> {{ __('Voir la commande') }} <span class="cnt" id="cnt">0</span></span><span id="bartot">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></button>
    </div>

    <div class="sheet" id="sheet">
        <div class="ov" onclick="closeCart()"></div>
        <div class="pan">
            <h3>{{ __('Votre commande') }} <button class="x" onclick="closeCart()">&times;</button></h3>

            {{-- État 1 : panier + infos client --}}
            <div id="orderForm">
                <div id="clist"></div>

                @php
                    // Un menu qui n'a jamais réglé ses modes de service garde
                    // les trois, comme avant ce réglage — voir
                    // Order::serviceTypesFor(). Le premier de la liste sert
                    // de défaut : un menu livraison-seule ne doit pas ouvrir
                    // sur « Sur place », un mode qu'il n'offre pas.
                    $modesOfferts = \Modules\Tagtoa\App\Models\Menu\Order::serviceTypesFor($menu->service_types);
                    $modesIcones = ['dine_in' => 'fa-utensils', 'pickup' => 'fa-bag-shopping', 'delivery' => 'fa-motorcycle'];
                @endphp
                <div class="otype" id="otype">
                    @foreach($modesOfferts as $i => $mode)
                        <button type="button" class="otbtn @if($i === 0) on @endif" data-type="{{ $mode }}" onclick="setOrderType('{{ $mode }}')">
                            <i class="fa-solid {{ $modesIcones[$mode] }}"></i> {{ __(\Modules\Tagtoa\App\Models\Menu\Order::ORDER_TYPE_LABELS[$mode]) }}
                        </button>
                    @endforeach
                </div>

                <div class="tiprow">
                    <span class="tiplbl">{{ __('Pourboire') }}</span>
                    <div class="tipbtns" id="tipbtns">
                        <button type="button" class="tipbtn on" data-pct="0" onclick="setTipPct(0)">0%</button>
                        <button type="button" class="tipbtn" data-pct="5" onclick="setTipPct(5)">5%</button>
                        <button type="button" class="tipbtn" data-pct="10" onclick="setTipPct(10)">10%</button>
                        <button type="button" class="tipbtn" data-pct="15" onclick="setTipPct(15)">15%</button>
                    </div>
                </div>

                <div class="tot" style="font-weight:500;font-size:14px;color:var(--mut)"><span>{{ __('Sous-total') }}</span><span id="subtotal">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>
                <div class="tot" id="tiprowtot" style="display:none;font-weight:500;font-size:14px;color:var(--mut)"><span>{{ __('Pourboire') }}</span><span id="tipamt">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>
                <div class="tot" id="deliveryrowtot" style="display:none;font-weight:500;font-size:14px;color:var(--mut)"><span>{{ __('Frais de livraison') }}</span><span id="deliveryamt">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>
                <div class="tot"><span>{{ __('Total') }}</span><span id="total">{{ \Modules\Tagtoa\App\Support\Money::format(0, $cur) }}</span></div>

                <div class="custf">
                    <input id="cName" class="cin" placeholder="{{ __('Votre nom') }}" maxlength="120">
                    <input id="cPhone" class="cin" type="tel" placeholder="{{ __('Téléphone (WhatsApp)') }}" maxlength="40">
                    @if($table)
                        {{-- Table vérifiée par le QR scanné : fixe, jamais un
                             texte que le client pourrait changer pour une
                             autre table que la sienne. --}}
                        <div id="cTableFixe" class="cin" style="display:flex;align-items:center;gap:8px;color:var(--acc)">
                            <i class="fa-solid fa-chair"></i> {{ __('Table') }} : <b>{{ $table->label }}</b>
                        </div>
                        <input id="cTable" type="hidden" value="{{ $table->label }}">
                    @else
                        <input id="cTable" class="cin" placeholder="{{ __('N° table (optionnel)') }}" maxlength="40">
                    @endif
                    <input id="cAddress" class="cin" placeholder="{{ __('Adresse de livraison') }}" maxlength="200" style="display:none">
                    @if($menu->activeDeliveryZones->isNotEmpty())
                        {{-- Zone D'ABORD dans le flux visuel du frais : c'est elle
                             qui fixe le prix, l'adresse ci-dessus ne sert qu'à
                             trouver la porte une fois sur place. --}}
                        <select id="cZone" class="cin" style="display:none" onchange="render()">
                            @foreach($menu->activeDeliveryZones as $z)
                                <option value="{{ $z->id }}" data-fee="{{ $z->fee }}">{{ $z->name }} — {{ \Modules\Tagtoa\App\Support\Money::format($z->fee, $cur) }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="cta">
                    <button class="wa" id="confirmBtn" onclick="submitOrder()"><i class="fa-solid fa-bag-shopping"></i> {{ __('Confirmer la commande') }}</button>
                    <button class="clr" onclick="clearCart()">{{ __('Vider la commande') }}</button>
                </div>
            </div>

            {{-- État 2 : commande confirmée --}}
            <div id="orderDone" style="display:none">
                <div style="text-align:center;padding:14px 0 4px">
                    <div style="width:64px;height:64px;border-radius:50%;background:#25D366;color:#fff;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 12px"><i class="fa-solid fa-check"></i></div>
                    <div style="font:700 18px var(--fh)">{{ __('Commande créée') }}</div>
                    <div style="color:var(--mut);margin-top:4px">{{ __('Référence') }} : <b id="okRef"></b></div>
                    <div style="color:var(--mut)">{{ __('Total') }} : <b id="okTotal"></b></div>
                    {{-- Visible uniquement quand la commande a été mise en file
                         faute de réseau — voir showQueued() plus bas. --}}
                    <div id="okQueuedNote" style="display:none;margin-top:10px;padding:10px 12px;background:rgba(255,180,0,.14);border-radius:10px;font-size:13.5px">
                        <i class="fa-solid fa-wifi" style="opacity:.7"></i>
                        {{ __('Pas de connexion : votre commande est enregistrée sur cet appareil et sera envoyée automatiquement dès que la connexion revient. Ne fermez pas cette page tant que ce n\'est pas fait.') }}
                    </div>
                </div>
                <div class="cta" style="margin-top:8px">
                    <a class="pay" id="okTrack" href="#"><i class="fa-solid fa-location-crosshairs"></i> {{ __('Suivre ma commande') }}</a>
                    <a class="wa" id="okWa" href="#" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i> {{ __('Commander sur WhatsApp') }}</a>
                    <a class="pay" id="okPay" href="#" style="display:none"><i class="fa-solid fa-credit-card"></i> {{ __('Payer maintenant') }}</a>
                    <button class="clr" onclick="newOrder()">{{ __('Nouvelle') }}</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Sélecteur d'options d'un plat (taille, extras…) avant ajout au panier --}}
    <div class="sheet" id="modsheet">
        <div class="ov" onclick="closeMod()"></div>
        <div class="pan">
            <h3 id="modTitle">{{ __('Options') }} <button class="x" onclick="closeMod()">&times;</button></h3>
            <div id="modBody"></div>
            <div class="cta" style="margin-top:12px">
                <button class="wa" id="modAdd" onclick="confirmModAdd()"><i class="fa-solid fa-plus"></i> {{ __('Ajouter au panier') }}</button>
            </div>
        </div>
    </div>

    {{--
        « Commander via Agent IA » — mots-clés locaux (OrderChatParser), pas
        de LLM branché (aucun n'existe dans TAGTOA aujourd'hui). Le client
        décrit sa commande en une phrase ; ce qui est reconnu part
        directement dans le panier existant, ce qui ne l'est pas reste dit
        clairement — jamais deviné.
    --}}
    <button class="ai-fab" id="aiFab" onclick="openAgent()" aria-label="{{ __('Commander via Agent IA') }}">
        <i class="fa-solid fa-robot"></i>
    </button>
    <div class="sheet" id="aisheet">
        <div class="ov" onclick="closeAgent()"></div>
        <div class="pan">
            <h3><i class="fa-solid fa-robot"></i> {{ __('Commander via Agent IA') }} <button class="x" onclick="closeAgent()">&times;</button></h3>
            <p style="color:var(--mut);font-size:13px;margin:-6px 0 12px">
                {{ __('Décrivez ce que vous voulez en une phrase — ex. « 2 griot, un jus naturel ». Les prix restent toujours ceux du menu.') }}
            </p>
            <div id="aiLog" style="max-height:44vh;overflow-y:auto;display:flex;flex-direction:column;gap:8px;margin-bottom:12px"></div>
            <div style="display:flex;gap:8px">
                <input id="aiInput" class="cin" placeholder="{{ __('Ex. 2 griot et un jus naturel') }}" onkeydown="if(event.key==='Enter'){event.preventDefault();sendAgent();}">
                <button class="wa" id="aiSend" onclick="sendAgent()" style="flex:0;width:auto;padding:0 18px"><i class="fa-solid fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <script>
        var CURMETA = @json(\Modules\Tagtoa\App\Support\Money::meta($cur));
        var ORDER_URL = @json(route('tagtoa.menu.order', $menu->alias));
        // Affichage seulement : le total réel, avec les frais, est TOUJOURS
        // recalculé côté serveur (MenuOrderService::insertOrder()).
        var DELIVERY_FEE = @json((float) ($menu->delivery_fee ?: 0));
        var HAS_ZONES = @json($menu->activeDeliveryZones->isNotEmpty());
        var CSRF = document.querySelector('meta[name=csrf-token]').getAttribute('content');
        var T = { empty:@json(__('Votre commande est vide.')), confirm:@json(__('Confirmer la commande')), wait:@json(__('Patientez…')), err:@json(__('Réessayez.')), required:@json(__('Choisissez une option obligatoire.')) };
        var cart = {};
        // Même défaut que le bouton .otbtn.on rendu côté serveur — un menu
        // livraison-seule ne doit pas démarrer sur un mode qu'il n'offre pas.
        var orderType = @json($modesOfferts[0] ?? 'dine_in');
        var tipPct = 0;
        var modItem = null, modChosen = {};
        var ORDER_UUID = 'mo-' + Date.now().toString(36) + Math.random().toString(36).slice(2,10);

        /* ------------------------------------------------------------------
           HORS LIGNE — une connexion coupée ne doit jamais perdre une
           commande déjà composée. Ce que le serveur refuse (rupture de
           stock, article invalide) reste une vraie erreur affichée tout de
           suite ; seule l'ABSENCE de réseau met la commande de côté, pour
           l'envoyer dès que la connexion revient — avec le MÊME client_uuid,
           donc jamais en double (voir MenuOrderService::placeOrder()).
           ------------------------------------------------------------------ */
        var QUEUE_KEY = 'tagtoa_menu_queue_' + @json($menu->alias);
        function lireFile(){ try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); } catch(e){ return []; } }
        function ecrireFile(f){ try { localStorage.setItem(QUEUE_KEY, JSON.stringify(f)); } catch(e){} }

        /** Envoie une commande. Rejette avec `.horsLigne = true` si c'est le
            réseau qui a manqué — jamais pour un refus du serveur. */
        function envoyerCommande(payload){
            return fetch(ORDER_URL,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF},
                body:JSON.stringify(payload)})
                .catch(function(){ var e = new Error(T.err); e.horsLigne = true; throw e; })
                .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});})
                .then(function(res){
                    if(!res.ok||!res.j.ok){ throw new Error(res.j && res.j.message); }
                    return res.j;
                });
        }

        /** Rejoue la file dans l'ORDRE (une addition avant une autre reste
            servie avant une autre), s'arrête au premier échec pour ne pas
            envoyer une commande plus récente avant une plus ancienne. */
        function retenterFile(){
            var file = lireFile();
            if (!file.length) return;
            envoyerCommande(file[0]).then(function(j){
                file.shift(); ecrireFile(file);
                son('ok');
                retenterFile();
            }).catch(function(){ /* toujours hors ligne : on réessaiera plus tard */ });
        }
        window.addEventListener('online', retenterFile);
        setInterval(retenterFile, 20000); // filet : certains navigateurs ne déclenchent pas 'online' de façon fiable
        retenterFile(); // la connexion est peut-être déjà revenue depuis la dernière visite
        function fmt(n){
            var d = (CURMETA.decimals==null) ? 2 : CURMETA.decimals;
            var s = Number(n).toLocaleString('en-US',{minimumFractionDigits:d,maximumFractionDigits:d});
            return CURMETA.position==='before' ? CURMETA.symbol+s : s+' '+CURMETA.symbol;
        }
        function val(id){ var e=document.getElementById(id); return e?e.value.trim():''; }
        function add(el){
            var opts = [];
            try { opts = JSON.parse(el.getAttribute('data-options') || '[]'); } catch(e){}
            var id = el.getAttribute('data-id'), name = el.getAttribute('data-name'), price = parseFloat(el.getAttribute('data-price'))||0;
            if (opts.length){ openMod(id, name, price, opts); return; }
            addToCart(id, name, price, [], '');
        }
        /* Le son est un ACCUSÉ DE RÉCEPTION, pas une décoration.
           Sur un téléphone, le panier est en bas de page : le client qui touche
           « + » ne voit RIEN bouger. Sans bruit, il retouche — et commande deux
           fois le même plat. C'est le son qui l'évite, pas un message. */
        function son(quoi){ if (window.TagtoaSound && TagtoaSound[quoi]) TagtoaSound[quoi](); }

        function addToCart(id, name, price, optionIds, optionLabel){
            var key = String(id) + (optionIds.length ? '|'+optionIds.slice().sort(function(a,b){return a-b;}).join(',') : '');
            if(!cart[key]) cart[key] = {id:Number(id), name:name, price:price, qty:0, options:optionIds, optionLabel:optionLabel};
            cart[key].qty++; render(); son('add');
        }
        function chg(id,d){
            if(!cart[id])return;
            cart[id].qty+=d;
            if(cart[id].qty<=0) delete cart[id];
            render();
            son(d>0 ? 'add' : 'remove');
        }
        function clearCart(){ cart={}; render(); closeCart(); }
        function totals(){ var n=0,t=0; for(var k in cart){ n+=cart[k].qty; t+=cart[k].qty*cart[k].price; } return {n:n,t:t}; }
        function tipAmount(subtotal){ return Math.round(subtotal*tipPct)/100; }
        function setOrderType(t){
            orderType = t;
            document.querySelectorAll('#otype .otbtn').forEach(function(b){ b.classList.toggle('on', b.getAttribute('data-type')===t); });
            var cTableFixe = document.getElementById('cTableFixe');
            if (cTableFixe) { cTableFixe.style.display = (t==='dine_in') ? '' : 'none'; }
            else { document.getElementById('cTable').style.display = (t==='dine_in') ? '' : 'none'; }
            document.getElementById('cAddress').style.display = (t==='delivery') ? '' : 'none';
            var zoneSel = document.getElementById('cZone');
            if (zoneSel) { zoneSel.style.display = (t==='delivery') ? '' : 'none'; }
            render();
        }
        /* Frais du mode Livraison : celui de la zone choisie si le menu en a
           défini, sinon le frais unique du menu — jamais les deux. */
        function fraisLivraison(){
            var zoneSel = document.getElementById('cZone');
            if (HAS_ZONES && zoneSel && zoneSel.selectedOptions.length){
                return Number(zoneSel.selectedOptions[0].getAttribute('data-fee')) || 0;
            }
            return DELIVERY_FEE;
        }
        function setTipPct(p){
            tipPct = p;
            document.querySelectorAll('#tipbtns .tipbtn').forEach(function(b){ b.classList.toggle('on', Number(b.getAttribute('data-pct'))===p); });
            render();
        }
        function render(){
            var s = totals();
            var tip = tipAmount(s.t);
            var frais = (orderType==='delivery') ? fraisLivraison() : 0;
            document.getElementById('cnt').textContent = s.n;
            document.getElementById('bartot').textContent = fmt(s.t+tip+frais);
            document.getElementById('subtotal').textContent = fmt(s.t);
            document.getElementById('tipamt').textContent = fmt(tip);
            document.getElementById('tiprowtot').style.display = tip>0 ? '' : 'none';
            document.getElementById('deliveryamt').textContent = fmt(frais);
            document.getElementById('deliveryrowtot').style.display = frais>0 ? '' : 'none';
            document.getElementById('total').textContent = fmt(s.t+tip+frais);
            document.getElementById('cartbar').classList.toggle('show', s.n>0);
            var list = document.getElementById('clist'), html='';
            if(s.n===0){ html = '<div class="empty">'+T.empty+'</div>'; }
            else { for(var k in cart){ var c=cart[k];
                html += '<div class="crow"><div class="cn">'+esc(c.name)+(c.optionLabel?'<div class="od" style="font-weight:400">'+esc(c.optionLabel)+'</div>':'')+'<div class="cp">'+fmt(c.price)+'</div></div>'+
                        '<div class="qty"><button onclick="chg(\''+k+'\',-1)">−</button><span>'+c.qty+'</span><button onclick="chg(\''+k+'\',1)">+</button></div></div>';
            }}
            list.innerHTML = html;
        }
        function openMod(id, name, price, opts){
            modItem = {id:id, name:name, price:price, opts:opts}; modChosen = {};
            document.getElementById('modTitle').innerHTML = esc(name)+' <button class="x" onclick="closeMod()">&times;</button>';
            var html = '';
            opts.forEach(function(g){
                modChosen[g.id] = [];
                html += '<div class="optgrp" data-gid="'+g.id+'"><div class="oh">'+esc(g.name)+(g.required?' <span class="req">'+@json(__('Requis'))+'</span>':'')+'</div>';
                g.choices.forEach(function(c){
                    var inputType = g.multiple ? 'checkbox' : 'radio';
                    html += '<label class="optchoice"><span><input type="'+inputType+'" name="grp'+g.id+'" onchange="toggleChoice('+g.id+','+c.id+',this,'+(g.multiple?'true':'false')+')"> '+esc(c.label)+'</span><span class="od">'+(c.price_delta ? (c.price_delta>0?'+':'')+fmt(c.price_delta) : '')+'</span></label>';
                });
                html += '</div>';
            });
            document.getElementById('modBody').innerHTML = html;
            document.getElementById('modsheet').classList.add('show');
        }
        function toggleChoice(gid, cid, el, multiple){
            if (!modChosen[gid]) modChosen[gid] = [];
            if (multiple){
                if (el.checked){ modChosen[gid].push(cid); } else { modChosen[gid] = modChosen[gid].filter(function(x){return x!==cid;}); }
            } else {
                modChosen[gid] = el.checked ? [cid] : [];
            }
        }
        function closeMod(){ document.getElementById('modsheet').classList.remove('show'); modItem=null; }
        function confirmModAdd(){
            if (!modItem) return;
            var ids = [], labels = [], extra = 0;
            for (var i=0;i<modItem.opts.length;i++){
                var g = modItem.opts[i]; var chosen = modChosen[g.id] || [];
                if (g.required && chosen.length===0){ alert(T.required); return; }
                chosen.forEach(function(cid){
                    var c = g.choices.find(function(x){return x.id===cid;});
                    if (c){ ids.push(cid); labels.push(c.label); extra += Number(c.price_delta)||0; }
                });
            }
            addToCart(modItem.id, modItem.name, modItem.price+extra, ids, labels.join(', '));
            closeMod(); openCart();
        }
        function submitOrder(){
            var s = totals(); if(s.n===0) return;
            var items=[]; for(var k in cart){ items.push({id:cart[k].id, qty:cart[k].qty, options:cart[k].options}); }
            var payload = {items:items,client_uuid:ORDER_UUID,channel:'menu',order_type:orderType,tip:tipAmount(s.t),
                customer_name:val('cName'),customer_phone:val('cPhone'),table_label:val('cTable'),
                table_code:@json($table->code ?? null),delivery_address:val('cAddress'),
                delivery_zone_id:(HAS_ZONES ? val('cZone') : null)};
            var btn=document.getElementById('confirmBtn'); btn.disabled=true; var old=btn.innerHTML; btn.textContent=T.wait;
            envoyerCommande(payload).then(function(j){
                showConfirmed(j);
            }).catch(function(e){
                if (e && e.horsLigne){
                    // Pas de réseau : la commande part dans la file plutôt
                    // que de se perdre, et sera envoyée automatiquement.
                    var file = lireFile(); file.push(payload); ecrireFile(file);
                    showQueued();
                    return;
                }
                btn.disabled=false; btn.innerHTML=old; alert((e && e.message) || T.err);
            });
        }
        function showQueued(){
            document.getElementById('okRef').textContent = @json(__('en attente de connexion'));
            document.getElementById('okTotal').textContent = fmt(totals().t + tipAmount(totals().t));
            document.getElementById('okTrack').style.display='none';
            document.getElementById('okWa').style.display='none';
            document.getElementById('okPay').style.display='none';
            var note = document.getElementById('okQueuedNote');
            if (note) { note.style.display=''; }
            document.getElementById('orderForm').style.display='none';
            document.getElementById('orderDone').style.display='';
        }
        function showConfirmed(j){
            document.getElementById('okQueuedNote').style.display='none';
            document.getElementById('okRef').textContent = j.reference;
            document.getElementById('okTotal').textContent = j.total;
            var track=document.getElementById('okTrack');
            if(j.track_url){ track.href=j.track_url; track.style.display=''; } else { track.style.display='none'; }
            var wa=document.getElementById('okWa');
            if(j.whatsapp_url){ wa.href=j.whatsapp_url; wa.style.display=''; } else { wa.style.display='none'; }
            var pay=document.getElementById('okPay');
            var payUrl=j.checkout_url||j.pay_url; if(payUrl){ pay.href=payUrl; pay.style.display=''; } else { pay.style.display='none'; }
            document.getElementById('orderForm').style.display='none';
            document.getElementById('orderDone').style.display='';
        }
        function newOrder(){
            cart={}; ORDER_UUID='mo-'+Date.now().toString(36)+Math.random().toString(36).slice(2,10);
            document.getElementById('orderDone').style.display='none';
            document.getElementById('orderForm').style.display='';
            var b=document.getElementById('confirmBtn'); b.disabled=false; b.innerHTML='<i class="fa-solid fa-bag-shopping"></i> '+T.confirm;
            render(); closeCart();
        }
        function esc(x){ return String(x).replace(/[&<>"]/g,function(m){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m];}); }
        function openCart(){ document.getElementById('sheet').classList.add('show'); }
        function closeCart(){ document.getElementById('sheet').classList.remove('show'); }

        /* ------------------------------------------------------------------
           « Commander via Agent IA » — voir OrderChatParser (mots-clés
           locaux, aucun LLM). Le serveur ne fait que SUGGÉRER des
           correspondances ; c'est ce même navigateur, avec les mêmes
           fonctions que le reste de la page, qui remplit le panier — le prix
           vient toujours de la carte affichée, jamais de la réponse du chat.
           ------------------------------------------------------------------ */
        var AGENT_URL = @json(route('tagtoa.menu.agent', $menu->alias));
        var aiEnCours = false;

        /** Catalogue lu dans la page déjà rendue : id → {name, price, hasOptions}. */
        function catalogueAffiche(){
            var out = {};
            document.querySelectorAll('.add[data-id]').forEach(function(el){
                var opts = [];
                try { opts = JSON.parse(el.getAttribute('data-options') || '[]'); } catch(e){}
                out[el.getAttribute('data-id')] = {
                    name: el.getAttribute('data-name'),
                    price: parseFloat(el.getAttribute('data-price')) || 0,
                    hasOptions: opts.length > 0,
                };
            });
            return out;
        }

        function aiBulle(texte, cote){
            var log = document.getElementById('aiLog');
            var d = document.createElement('div');
            d.className = 'ai-msg ' + cote;
            d.textContent = texte;
            log.appendChild(d);
            log.scrollTop = log.scrollHeight;
        }

        function openAgent(){
            document.getElementById('aisheet').classList.add('show');
            var log = document.getElementById('aiLog');
            if (!log.children.length){
                aiBulle({{ json_encode(__('Dites-moi ce que vous voulez commander, en une phrase.')) }}, 'bot');
            }
            document.getElementById('aiInput').focus();
        }
        function closeAgent(){ document.getElementById('aisheet').classList.remove('show'); }

        function sendAgent(){
            if (aiEnCours) return;
            var input = document.getElementById('aiInput');
            var texte = input.value.trim();
            if (!texte) return;
            aiBulle(texte, 'me');
            input.value = '';
            aiEnCours = true;

            fetch(AGENT_URL, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF}, body: JSON.stringify({message: texte})})
                .then(function(r){ return r.json(); })
                .then(function(res){
                    aiEnCours = false;
                    if (!res || !res.ok){ aiBulle(T.err, 'bot'); return; }

                    var catalogue = catalogueAffiche();
                    var ajoutes = [], besoinOptions = [];
                    (res.matches || []).forEach(function(m){
                        var article = catalogue[String(m.id)];
                        if (!article){ return; } // plus au menu depuis le chargement de la page
                        if (article.hasOptions){ besoinOptions.push(article.name); return; }
                        for (var i = 0; i < m.qty; i++){ addToCart(m.id, article.name, article.price, [], ''); }
                        ajoutes.push(m.qty + '× ' + article.name);
                    });

                    var reponse = [];
                    if (ajoutes.length){ reponse.push({{ json_encode(__('Ajouté au panier :')) }} + ' ' + ajoutes.join(', ') + '.'); }
                    if (besoinOptions.length){ reponse.push({{ json_encode(__('Ces articles ont des options à choisir, ajoutez-les depuis la carte :')) }} + ' ' + besoinOptions.join(', ') + '.'); }
                    if (res.unmatched && res.unmatched.length){ reponse.push({{ json_encode(__('Je n\'ai pas trouvé sur la carte :')) }} + ' ' + res.unmatched.join(', ') + '.'); }
                    if (!reponse.length){ reponse.push({{ json_encode(__('Je n\'ai rien reconnu. Essayez avec le nom exact d\'un plat de la carte.')) }}); }

                    aiBulle(reponse.join(' '), 'bot');
                })
                .catch(function(){ aiEnCours = false; aiBulle(T.err, 'bot'); });
        }

        render();
    </script>
@endif

<script>
    // Installable + hors ligne — voir PublicController::serviceWorker(). Une
    // page déjà visitée doit se rouvrir même sans réseau, et rien de plus :
    // aucune commande (POST) n'est jamais interceptée par le worker.
    if ('serviceWorker' in navigator){
        navigator.serviceWorker.register("{{ route('tagtoa.menu.sw', $menu->alias) }}", {scope:"{{ url('/menu/'.$menu->alias) }}"}).catch(function(){});
    }
</script>

<script>
    // Surlignage de la catégorie active dans la barre de navigation.
    (function(){
        var chips = [].slice.call(document.querySelectorAll('.catnav .chip'));
        chips.forEach(function(ch){ ch.addEventListener('click', function(){
            var el = document.getElementById(ch.getAttribute('data-target'));
            if(el){ var y = el.getBoundingClientRect().top + window.pageYOffset - 64; window.scrollTo({top:y,behavior:'smooth'}); }
        }); });
        var secs = chips.map(function(ch){ return document.getElementById(ch.getAttribute('data-target')); });
        function onScroll(){
            var i = secs.length-1;
            for(var j=0;j<secs.length;j++){ if(secs[j] && secs[j].getBoundingClientRect().top<=90){ i=j; } }
            chips.forEach(function(c,k){ c.classList.toggle('on', k===i); });
        }
        window.addEventListener('scroll', onScroll, {passive:true}); onScroll();
    })();
</script>
</body>
</html>
