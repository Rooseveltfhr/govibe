@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran caisse'))
@section('page', __('Caisse') . ' — ' . $menu->name)

{{--
    TAGTOA MENU — écran caisse : complète le cycle ouvert par la cuisine.

    La cuisine (kitchen.blade.php) amène une commande jusqu'à « Prête ». Cet
    écran ne montre QUE les commandes prêtes — jamais celles encore en
    préparation, qu'un comptoir n'a aucune raison de voir — et sert un seul
    geste : servir + encaisser ensemble, jamais l'un sans l'autre (marquer
    servi sans encaisser ferait disparaître une addition non réglée de la
    file, invisible jusqu'au rapport du soir).
--}}

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.orders',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Caisse') }} — {{ $menu->name }}</h2>
    <span id="ct-etat" style="color:var(--muted);font-size:12.5px"></span>
</div>

<div class="card" id="ct-staff-bar" style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span id="ct-staff-qui" style="font-size:13.5px;color:var(--muted)"></span>
    <form id="ct-staff-logout" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.logout',$menu->id) }}" style="display:none">
        @csrf
        <button class="btn btn-o btn-sm"><i class="fa-solid fa-right-from-bracket"></i> {{ __('Changer de personne') }}</button>
    </form>
    <form id="ct-staff-login" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.login',$menu->id) }}" style="display:flex;gap:8px;align-items:center">
        @csrf
        <input class="inp" type="password" inputmode="numeric" name="pin" maxlength="6" placeholder="{{ __('Code employé (optionnel)') }}" style="max-width:180px">
        <button class="btn btn-o btn-sm">{{ __('S\'identifier') }}</button>
        @error('pin')<span style="color:var(--red);font-size:12.5px">{{ $message }}</span>@enderror
    </form>
</div>

<div id="ct-vide" class="card" style="display:none;margin-top:14px"><div class="empty"><i class="fa-solid fa-bell-concierge"></i>{{ __('Aucune commande prête à servir.') }}</div></div>

<div id="ct-grille" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:14px"></div>

<form id="ct-complete-form" method="POST" style="display:none">@csrf</form>

<style>
    .ct-carte{background:var(--surf,#fff);border:2px solid var(--bd,rgba(0,0,0,.08));border-radius:16px;padding:16px}
    .ct-tete{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
    .ct-ref{font:700 17px var(--fh,sans-serif)}
    .ct-total{font:700 17px var(--fh,sans-serif);color:var(--acc,#2cb809)}
    .ct-tag{font-size:12.5px;color:var(--muted,#888);margin-bottom:10px}
    .ct-item{display:flex;justify-content:space-between;gap:10px;padding:4px 0;font-size:14px;color:var(--muted,#888)}
    .ct-servir{width:100%;margin-top:12px;padding:10px;border:0;border-radius:10px;background:var(--acc,#2cb809);color:#fff;font:700 14px var(--fh,sans-serif);cursor:pointer}
</style>

@push('scripts')
<script>
(function(){
    var FEED = @json(route('tagtoa.menu.dashboard.counter.feed', $menu->id));
    var COMPLETE_BASE = @json(url('/tagtoa/menu/'.$menu->id.'/counter/orders'));
    var etat = document.getElementById('ct-etat');
    var grille = document.getElementById('ct-grille');
    var vide = document.getElementById('ct-vide');
    var quiSpan = document.getElementById('ct-staff-qui');
    var formLogin = document.getElementById('ct-staff-login');
    var formLogout = document.getElementById('ct-staff-logout');
    var completeForm = document.getElementById('ct-complete-form');

    function esc(v){
        return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function completer(orderId){
        completeForm.action = COMPLETE_BASE + '/' + orderId + '/complete';
        completeForm.submit();
    }
    window.ctCompleter = completer;

    function carte(o, peutEncaisser){
        var html = '<div class="ct-carte">'
            + '<div class="ct-tete"><span class="ct-ref">' + esc(o.reference) + '</span>'
            + '<span class="ct-total">' + esc(o.total) + ' ' + esc(o.currency) + '</span></div>'
            + '<div class="ct-tag">' + esc(o.order_type_label)
            + (o.table_label ? ' · {{ __('Table') }} ' + esc(o.table_label) : '') + '</div>';

        (o.items || []).forEach(function(it){
            html += '<div class="ct-item"><span>' + it.qty + '× ' + esc(it.name) + '</span></div>';
        });

        if (peutEncaisser){
            html += '<button type="button" class="ct-servir" onclick="ctCompleter(' + o.id + ')">'
                + '{{ __('Servir + encaisser') }}</button>';
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
                grille.innerHTML = orders.map(function(o){ return carte(o, !!data.can_complete); }).join('');
            })
            .catch(function(){ etat.textContent = '{{ __('Connexion perdue — nouvel essai…') }}'; });
    }

    rafraichir();
    setInterval(rafraichir, 8000);
})();
</script>
@endpush
@endsection
