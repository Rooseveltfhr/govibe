@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran livraison'))
@section('page', __('Livraison') . ' — ' . $menu->name)

{{--
    TAGTOA MENU — écran livraison : le trajet d'une commande LIVRAISON après
    « Prête ». Le patron/gérant assigne un livreur (liste déroulante) ; le
    livreur assigné — ou le patron/gérant directement — fait ensuite avancer
    la commande en deux gestes : « Récupérée » puis « Livrée ». Un livreur
    identifié ne voit ses actions activées QUE sur ses propres livraisons
    (can_advance, calculé côté serveur) — jamais celles d'un collègue.
--}}

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.orders',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Livraison') }} — {{ $menu->name }}</h2>
    <span id="dv-etat" style="color:var(--muted);font-size:12.5px"></span>
</div>

<div class="card" id="dv-staff-bar" style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span id="dv-staff-qui" style="font-size:13.5px;color:var(--muted)"></span>
    <form id="dv-staff-logout" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.logout',$menu->id) }}" style="display:none">
        @csrf
        <button class="btn btn-o btn-sm"><i class="fa-solid fa-right-from-bracket"></i> {{ __('Changer de personne') }}</button>
    </form>
    <form id="dv-staff-login" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.login',$menu->id) }}" style="display:flex;gap:8px;align-items:center">
        @csrf
        <input class="inp" type="password" inputmode="numeric" name="pin" maxlength="6" placeholder="{{ __('Code employé (optionnel)') }}" style="max-width:180px">
        <button class="btn btn-o btn-sm">{{ __('S\'identifier') }}</button>
        @error('pin')<span style="color:var(--red);font-size:12.5px">{{ $message }}</span>@enderror
    </form>
</div>

<div id="dv-vide" class="card" style="display:none;margin-top:14px"><div class="empty"><i class="fa-solid fa-motorcycle"></i>{{ __('Aucune livraison en cours.') }}</div></div>

<div id="dv-grille" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:14px;margin-top:14px"></div>

<form id="dv-assign-form" method="POST" style="display:none">@csrf</form>
<form id="dv-advance-form" method="POST" style="display:none">@csrf</form>

<style>
    .dv-carte{background:var(--surf,#fff);border:2px solid var(--bd,rgba(0,0,0,.08));border-radius:16px;padding:16px}
    .dv-tete{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
    .dv-ref{font:700 17px var(--fh,sans-serif)}
    .dv-total{font:700 17px var(--fh,sans-serif);color:var(--acc,#2cb809)}
    .dv-tag{font-size:12.5px;color:var(--muted,#888);margin-bottom:6px}
    .dv-adresse{font-size:13.5px;margin-bottom:10px}
    .dv-item{display:flex;justify-content:space-between;gap:10px;padding:4px 0;font-size:14px;color:var(--muted,#888)}
    .dv-avancer{width:100%;margin-top:12px;padding:10px;border:0;border-radius:10px;background:var(--acc,#2cb809);color:#fff;font:700 14px var(--fh,sans-serif);cursor:pointer}
    .dv-assign{width:100%;margin-top:10px;padding:8px;border-radius:10px}
</style>

@push('scripts')
<script>
(function(){
    var FEED = @json(route('tagtoa.menu.dashboard.delivery.feed', $menu->id));
    var ASSIGN_BASE = @json(url('/tagtoa/menu/'.$menu->id.'/delivery/orders'));
    var etat = document.getElementById('dv-etat');
    var grille = document.getElementById('dv-grille');
    var vide = document.getElementById('dv-vide');
    var quiSpan = document.getElementById('dv-staff-qui');
    var formLogin = document.getElementById('dv-staff-login');
    var formLogout = document.getElementById('dv-staff-logout');
    var assignForm = document.getElementById('dv-assign-form');
    var advanceForm = document.getElementById('dv-advance-form');

    function esc(v){
        return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function avancer(orderId){
        advanceForm.action = ASSIGN_BASE + '/' + orderId + '/advance';
        advanceForm.submit();
    }
    window.dvAvancer = avancer;

    function assigner(orderId, select){
        var courierId = select.value;
        if (!courierId) return;
        assignForm.action = ASSIGN_BASE + '/' + orderId + '/courier';
        var input = document.createElement('input');
        input.type = 'hidden'; input.name = 'courier_id'; input.value = courierId;
        assignForm.appendChild(input);
        assignForm.submit();
    }
    window.dvAssigner = assigner;

    function carte(o, data){
        var html = '<div class="dv-carte">'
            + '<div class="dv-tete"><span class="dv-ref">' + esc(o.reference) + '</span>'
            + '<span class="dv-total">' + esc(o.total) + ' ' + esc(o.currency) + '</span></div>'
            + '<div class="dv-tag">' + esc(o.status_label)
            + (o.customer_name ? ' · ' + esc(o.customer_name) : '')
            + (o.customer_phone ? ' · ' + esc(o.customer_phone) : '') + '</div>';

        if (o.delivery_address || o.delivery_zone_label){
            html += '<div class="dv-adresse"><i class="fa-solid fa-location-dot"></i> '
                + (o.delivery_zone_label ? esc(o.delivery_zone_label) + ' — ' : '')
                + esc(o.delivery_address || '') + '</div>';
        }

        (o.items || []).forEach(function(it){
            html += '<div class="dv-item"><span>' + it.qty + '× ' + esc(it.name) + '</span></div>';
        });

        if (o.status === 'ready'){
            if (o.courier){
                html += '<div class="dv-adresse"><i class="fa-solid fa-motorcycle"></i> {{ __('Livreur') }} : ' + esc(o.courier.name) + '</div>';
            } else if (data.can_assign) {
                html += '<select class="sel dv-assign" onchange="dvAssigner(' + o.id + ', this)">'
                    + '<option value="">{{ __('Assigner un livreur…') }}</option>'
                    + (data.couriers || []).map(function(c){ return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('')
                    + '</select>';
            } else {
                html += '<div class="dv-adresse" style="color:var(--muted)">{{ __('En attente d\'un livreur.') }}</div>';
            }
        }

        if (o.can_advance && o.courier && o.status === 'ready'){
            html += '<button type="button" class="dv-avancer" onclick="dvAvancer(' + o.id + ')">{{ __('Récupérée par le livreur') }}</button>';
        } else if (o.can_advance && o.status === 'picked_up'){
            html += '<button type="button" class="dv-avancer" onclick="dvAvancer(' + o.id + ')">{{ __('Livrée') }}</button>';
        }

        html += '</div>';
        return html;
    }

    function rafraichir(){
        fetch(FEED, {headers: {'Accept': 'application/json'}})
            .then(function(r){ return r.json(); })
            .then(function(data){
                var orders = data.orders || [];
                etat.textContent = '{{ __('Mis à jour') }} ' + new Date().toLocaleTimeString();

                if (data.staff){
                    quiSpan.textContent = '{{ __('Identifié·e') }} : ' + data.staff.name;
                    formLogin.style.display = 'none';
                    formLogout.style.display = 'block';
                } else {
                    quiSpan.textContent = '{{ __('Personne identifié — le patron opère directement.') }}';
                    formLogin.style.display = 'flex';
                    formLogout.style.display = 'none';
                }

                vide.style.display = orders.length ? 'none' : 'block';
                grille.innerHTML = orders.map(function(o){ return carte(o, data); }).join('');
            })
            .catch(function(){ etat.textContent = '{{ __('Connexion perdue — nouvel essai…') }}'; });
    }

    rafraichir();
    setInterval(rafraichir, 8000);
})();
</script>
@endpush
@endsection
