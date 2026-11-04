@extends('tagtoa::layouts.dashboard')
@php use Modules\Tagtoa\App\Support\Money; @endphp
@section('title', __('Commandes'))
@section('page', __('Commandes') . ' — ' . $menu->name)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.edit',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Commandes') }} @if($pending)<span class="pill a">{{ $pending }} {{ __('en attente') }}</span>@endif</h2>
    <a href="{{ url('/menu/'.$menu->alias) }}" target="_blank" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-eye"></i> {{ __('Voir') }}</a>
</div>

<style>
    /* Gris lisible en plein soleil : le gris de base (#8a8a8a) tombait sous 4,5:1 sur le blanc. */
    .ord-meta{color:#4b5563;font-size:13px}
    .ord-total{font-family:var(--fh);font-size:17px;color:#111}
    .ord-total.paid{color:#0a7a3d}
    .ord-total.unpaid{color:#c2410c}
    .ord-age{display:inline-flex;align-items:center;gap:4px;font-size:12.5px;font-weight:700;color:#4b5563;margin-left:4px}
    .ord-age.late{color:#b42318}
    .ord-day{margin:18px 0 8px;font-family:var(--fh);font-size:13px;text-transform:uppercase;letter-spacing:.05em;color:#4b5563}
    .ord-actions{display:flex;gap:10px;align-items:stretch;flex-wrap:wrap;margin-top:14px}
    .ord-next{width:100%;justify-content:center;min-height:46px;font-size:15px}
    .ord-pay{display:flex;gap:8px;align-items:center;flex-wrap:wrap;flex:1 1 260px}
    .chip-pay{position:relative}
    .chip-pay input{position:absolute;opacity:0;inset:0;width:100%;height:100%;margin:0;cursor:pointer}
    .chip-pay span{display:inline-block;padding:9px 14px;border-radius:999px;border:1.5px solid #c9ccd1;background:#fff;color:#111;font-weight:700;font-size:13.5px}
    .chip-pay input:checked + span{background:#111;color:#fff;border-color:#111}
    .chip-pay input:focus-visible + span{outline:3px solid var(--blue);outline-offset:2px}
    .ord-cash[disabled]{opacity:.45;cursor:not-allowed}
    .ord-more{position:relative;margin-left:auto}
    .ord-more summary{list-style:none;cursor:pointer;border:1.5px solid #c9ccd1;border-radius:12px;padding:10px 14px;background:#fff;color:#111}
    .ord-more summary::-webkit-details-marker{display:none}
    .ord-more-panel{position:absolute;right:0;bottom:calc(100% + 6px);z-index:30;width:250px;background:#fff;border:1px solid var(--bd);border-radius:14px;padding:12px;box-shadow:0 10px 30px rgba(0,0,0,.18)}
    .ord-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
    .ord-filters a.f{display:inline-flex;align-items:center;gap:6px;padding:9px 14px;border-radius:999px;border:1.5px solid #c9ccd1;background:#fff;color:#111;font-weight:700;font-size:13.5px;text-decoration:none}
    .ord-filters a.f.on{background:#111;color:#fff;border-color:#111}
    .ord-filters a.f b{font-size:12px;background:rgba(0,0,0,.08);border-radius:999px;padding:1px 7px}
    .ord-filters a.f.on b{background:rgba(255,255,255,.25)}
    .ord-search{display:flex;gap:6px;flex:1 1 220px;min-width:200px}
    @media(max-width:860px){ .subnav{display:none} } /* la barre du bas (Commandes) suffit sur téléphone : une seule navigation */
</style>
@php
    $base = ['q' => $recherche !== '' ? $recherche : null];
    $chip = fn (?string $f) => url()->current().'?'.http_build_query(array_filter($base + ['f' => $f]));
@endphp
<nav class="ord-filters" aria-label="{{ __('Filtrer les commandes') }}">
    @foreach([[null, 'Tout', 'all'], ['new', 'Nouveau', 'new'], ['unpaid', 'À encaisser', 'unpaid'], ['delivery', 'Livraison', 'delivery']] as [$f, $lbl, $ck])
        <a class="f {{ $filtre === $f ? 'on' : '' }}" href="{{ $chip($f) }}" @if($filtre === $f) aria-current="true" @endif>{{ __($lbl) }} <b data-count="{{ $ck }}">{{ $counts[$ck] ?? 0 }}</b></a>
    @endforeach
    <form method="GET" action="{{ url()->current() }}" class="ord-search" role="search">
        @if($filtre)<input type="hidden" name="f" value="{{ $filtre }}">@endif
        <input class="inp" type="search" name="q" value="{{ $recherche }}" maxlength="60" placeholder="{{ __('Nom ou téléphone…') }}" aria-label="{{ __('Rechercher une commande par nom ou téléphone') }}">
        <button class="btn btn-o btn-sm" style="flex:0" aria-label="{{ __('Rechercher') }}"><i class="fa-solid fa-magnifying-glass"></i></button>
    </form>
</nav>

<div id="orders-list">
    @include('tagtoa::menu._orders-list', ['orders' => $orders, 'done' => $done])
</div>

{{-- Alerte « nouvelle commande » : l'écran se met à jour tout seul (aucun
     clic), et une commande qui arrive sonne 15 secondes — le temps qu'un
     patron occupé à la plonge ou en cuisine l'entende. --}}
<div id="neworder" role="alert" style="display:none;position:fixed;left:12px;right:12px;bottom:14px;z-index:90;background:var(--blue);color:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 10px 30px rgba(0,0,0,.3);align-items:center;gap:12px;font-weight:700">
    <i class="fa-solid fa-bell fa-shake"></i>
    <span style="flex:1">{{ __('Nouvelle commande !') }}</span>
    {{-- L'action naturelle est de VOIR la commande (ça coupe aussi le son). Couper le son seul reste possible, en second. --}}
    <button type="button" onclick="voirCommande()" class="btn btn-o btn-sm" style="flex:0;background:#fff;color:#000;font-weight:800">{{ __('Voir la commande') }}</button>
    <button type="button" onclick="stopAlerte()" class="btn btn-o btn-sm" style="flex:0;background:transparent;color:#fff;border-color:rgba(255,255,255,.6)" aria-label="{{ __('Arrêter le son') }}" title="{{ __('Arrêter le son') }}"><i class="fa-solid fa-volume-xmark"></i></button>
</div>
<div id="soundhint" style="display:none;color:var(--muted);font-size:12.5px;margin-top:10px"><i class="fa-solid fa-volume-xmark"></i> {{ __('Touchez la page une fois pour activer le son des nouvelles commandes.') }}</div>

<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}"></script>
<script>
    window.TAGTOA_ORDERS_PAGE = true; // l'alerte globale (partials/order-alert) ne double pas ce son
    (function(){
        var FEED = @json(route('tagtoa.menu.dashboard.orders.feed', $menu->id)) + window.location.search;  // le fil garde le filtre et la recherche
        var dernier = @json((int) ($menu->orders()->max('id') ?? 0));
        var signature = @json($orders->concat($done)->map(fn ($o) => $o->id.':'.$o->status.':'.$o->payment_status)->implode('|'));
        try { localStorage.setItem('tagtoa_seen_order', String(dernier)); } catch (e) {}  // vu : l'alerte globale ne re-sonne pas
        var liste = document.getElementById('orders-list');
        var bandeau = document.getElementById('neworder');
        var ALERTE_SECONDES = 15; // entre 10 et 20 : assez long pour s'entendre, pas assez pour harceler

        window.voirCommande = function(){
            window.stopAlerte();
            window.scrollTo({top: 0, behavior: 'smooth'});
            var premiere = liste.querySelector('.ord-card');
            if (premiere) { premiere.setAttribute('tabindex', '-1'); premiere.focus({preventScroll: true}); }
        };

        window.stopAlerte = function(){
            if (window.TagtoaSound) TagtoaSound.alerteStop();
            bandeau.style.display = 'none';
        };

        function actif(){
            // Ne jamais remplacer la liste sous les doigts du caissier : un
            // moyen de paiement à moitié choisi disparaîtrait.
            var a = document.activeElement;
            if (a && liste.contains(a) && (a.tagName === 'SELECT' || a.tagName === 'INPUT')) return true;
            // Safari ne donne pas le focus à une pastille cliquée : un moyen choisi, un « ⋯ » ouvert ne doivent pas disparaître.
            return !!liste.querySelector('input[name=payment_method]:checked, details.ord-more[open]');
        }

        function rafraichir(){
            fetch(FEED, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                .then(function(r){ return r.ok ? r.json() : null; })
                .then(function(j){
                    if (!j) return;
                    if (j.latest_id > dernier){
                        dernier = j.latest_id;
                        try { localStorage.setItem('tagtoa_seen_order', String(dernier)); } catch (e) {}
                        bandeau.style.display = 'flex';
                        if (window.TagtoaSound){
                            TagtoaSound.alerte(ALERTE_SECONDES);
                            if (!TagtoaSound.pret()) document.getElementById('soundhint').style.display = '';
                        }
                    }
                    if (j.counts){
                        document.querySelectorAll('.ord-filters [data-count]').forEach(function(b){
                            if (j.counts[b.getAttribute('data-count')] !== undefined) b.textContent = j.counts[b.getAttribute('data-count')];
                        });
                    }
                    if (j.signature !== signature && !actif()){
                        signature = j.signature;
                        liste.innerHTML = j.html;
                        ages();
                    }
                })
                .catch(function(){ /* hors ligne : on réessaiera */ });
        }
        setInterval(rafraichir, 8000);

        // « Encaisser » ne s'allume qu'une fois un moyen choisi (délégation : la liste est remplacée par le fil).
        liste.addEventListener('change', function(e){
            if (e.target && e.target.name === 'payment_method'){
                var b = e.target.form && e.target.form.querySelector('button[type=submit]');
                if (b){ b.disabled = false; b.classList.remove('btn-o'); b.classList.add('btn-p'); }
            }
        });

        // « il y a 12 min » se met à jour sans recharger ; rouge après 15 min. (Même règle que Order::LATE_AFTER_MINUTES.)
        var LATE = @json(\Modules\Tagtoa\App\Models\Menu\Order::LATE_AFTER_MINUTES);
        function ages(){
            var now = Math.floor(Date.now() / 1000);
            document.querySelectorAll('.ord-age[data-ts]').forEach(function(el){
                var m = Math.max(0, Math.floor((now - parseInt(el.getAttribute('data-ts'), 10)) / 60));
                el.querySelector('span').textContent = m < 1 ? "à l'instant" : m < 60 ? 'il y a ' + m + ' min' : m < 1440 ? 'il y a ' + Math.floor(m / 60) + ' h' : 'il y a ' + Math.floor(m / 1440) + ' j';
                el.classList.toggle('late', m > LATE);
            });
        }
        setInterval(ages, 30000);
    })();
</script>
@endsection
