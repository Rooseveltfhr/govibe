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

<div id="orders-list">
    @include('tagtoa::menu._orders-list', ['orders' => $orders])
</div>

{{-- Alerte « nouvelle commande » : l'écran se met à jour tout seul (aucun
     clic), et une commande qui arrive sonne 15 secondes — le temps qu'un
     patron occupé à la plonge ou en cuisine l'entende. --}}
<div id="neworder" role="alert" style="display:none;position:fixed;left:12px;right:12px;bottom:14px;z-index:90;background:var(--blue);color:#fff;border-radius:14px;padding:14px 16px;box-shadow:0 10px 30px rgba(0,0,0,.3);align-items:center;gap:12px;font-weight:700">
    <i class="fa-solid fa-bell fa-shake"></i>
    <span style="flex:1">{{ __('Nouvelle commande !') }}</span>
    <button type="button" onclick="stopAlerte()" class="btn btn-o btn-sm" style="flex:0;background:#fff;color:#000">{{ __('Arrêter le son') }}</button>
</div>
<div id="soundhint" style="display:none;color:var(--muted);font-size:12.5px;margin-top:10px"><i class="fa-solid fa-volume-xmark"></i> {{ __('Touchez la page une fois pour activer le son des nouvelles commandes.') }}</div>

<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}"></script>
<script>
    (function(){
        var FEED = @json(route('tagtoa.menu.dashboard.orders.feed', $menu->id));
        var dernier = @json((int) ($menu->orders()->max('id') ?? 0));
        var signature = @json($orders->map(fn ($o) => $o->id.':'.$o->status.':'.$o->payment_status)->implode('|'));
        var liste = document.getElementById('orders-list');
        var bandeau = document.getElementById('neworder');
        var ALERTE_SECONDES = 15; // entre 10 et 20 : assez long pour s'entendre, pas assez pour harceler

        window.stopAlerte = function(){
            if (window.TagtoaSound) TagtoaSound.alerteStop();
            bandeau.style.display = 'none';
        };

        function actif(){
            // Ne jamais remplacer la liste sous les doigts du caissier : un
            // moyen de paiement à moitié choisi disparaîtrait.
            var a = document.activeElement;
            return a && liste.contains(a) && (a.tagName === 'SELECT' || a.tagName === 'INPUT');
        }

        function rafraichir(){
            fetch(FEED, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'})
                .then(function(r){ return r.ok ? r.json() : null; })
                .then(function(j){
                    if (!j) return;
                    if (j.latest_id > dernier){
                        dernier = j.latest_id;
                        bandeau.style.display = 'flex';
                        if (window.TagtoaSound){
                            TagtoaSound.alerte(ALERTE_SECONDES);
                            if (!TagtoaSound.pret()) document.getElementById('soundhint').style.display = '';
                        }
                    }
                    if (j.signature !== signature && !actif()){
                        signature = j.signature;
                        liste.innerHTML = j.html;
                    }
                })
                .catch(function(){ /* hors ligne : on réessaiera */ });
        }
        setInterval(rafraichir, 8000);
    })();
</script>
@endsection
