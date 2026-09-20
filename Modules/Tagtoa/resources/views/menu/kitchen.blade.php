@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran cuisine'))
@section('page', __('Cuisine') . ' — ' . $menu->name)

{{--
    TAGTOA MENU — écran cuisine : lecture + polling + son, et depuis F3 une
    seule action volontairement étroite (faire avancer une commande d'une
    étape) — jamais servir/encaisser/annuler, qui restent sur l'écran
    « Commandes », seul à connaître le reste du cycle.

    L'identification (PIN) est optionnelle : sans elle, c'est le patron qui
    opère l'écran directement (déjà authentifié par le garde du dashboard) et
    l'avancement reste toujours permis. Une fois un employé identifié, seul
    celui coché « Écran cuisine » (ou patron/gérant) peut avancer une
    commande — voir Staff::canRunKitchen().
--}}

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.orders',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Cuisine') }} — {{ $menu->name }}</h2>
    <span id="kw-etat" style="color:var(--muted);font-size:12.5px"></span>
</div>

<div class="card" id="kw-staff-bar" style="margin-top:12px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <span id="kw-staff-qui" style="font-size:13.5px;color:var(--muted)"></span>
    <form id="kw-staff-logout" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.logout',$menu->id) }}" style="display:none">
        @csrf
        <button class="btn btn-o btn-sm"><i class="fa-solid fa-right-from-bracket"></i> {{ __('Changer de personne') }}</button>
    </form>
    <form id="kw-staff-login" method="POST" action="{{ route('tagtoa.menu.dashboard.staff.login',$menu->id) }}" style="display:flex;gap:8px;align-items:center">
        @csrf
        <input class="inp" type="password" inputmode="numeric" name="pin" maxlength="6" placeholder="{{ __('Code employé (optionnel)') }}" style="max-width:180px">
        <button class="btn btn-o btn-sm">{{ __('S\'identifier') }}</button>
        @error('pin')<span style="color:var(--red);font-size:12.5px">{{ $message }}</span>@enderror
    </form>
</div>

<div id="kw-vide" class="card" style="display:none;margin-top:14px"><div class="empty"><i class="fa-solid fa-mug-hot"></i>{{ __('Aucune commande à préparer.') }}</div></div>

<div id="kw-grille" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:14px"></div>

<form id="kw-advance-form" method="POST" style="display:none">@csrf</form>

<style>
    .kw-carte{background:var(--surf,#fff);border:2px solid var(--bd,rgba(0,0,0,.08));border-radius:16px;padding:16px}
    .kw-carte.kw-urgent{border-color:var(--red,#e11)}
    .kw-tete{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px}
    .kw-ref{font:700 17px var(--fh,sans-serif)}
    .kw-age{font:700 13px var(--fh,sans-serif);color:var(--muted,#888)}
    .kw-tag{font-size:12.5px;color:var(--muted,#888);margin-bottom:10px}
    .kw-item{display:flex;justify-content:space-between;gap:10px;padding:6px 0;border-top:1px dashed var(--bd,rgba(0,0,0,.08));font-size:15px}
    .kw-item:first-child{border-top:none}
    .kw-qty{font-weight:700;min-width:28px}
    .kw-opts{font-size:12.5px;color:var(--muted,#888);margin-top:2px}
    .kw-note{margin-top:10px;padding:8px 10px;background:rgba(255,180,0,.12);border-radius:10px;font-size:13px}
    .kw-avancer{width:100%;margin-top:12px;padding:10px;border:0;border-radius:10px;background:var(--acc,#2cb809);color:#fff;font:700 14px var(--fh,sans-serif);cursor:pointer}
</style>

@push('scripts')
<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}"></script>
<script>
(function(){
    var FEED = @json(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id));
    var ADVANCE_BASE = @json(url('/tagtoa/menu/'.$menu->id.'/kitchen/orders'));
    var connus = null; // null = premier chargement : pas d'alerte sur ce qui existait déjà
    var etat = document.getElementById('kw-etat');
    var grille = document.getElementById('kw-grille');
    var vide = document.getElementById('kw-vide');
    var quiSpan = document.getElementById('kw-staff-qui');
    var formLogin = document.getElementById('kw-staff-login');
    var formLogout = document.getElementById('kw-staff-logout');
    var advanceForm = document.getElementById('kw-advance-form');

    var LABELS_AVANCER = {preparing: '{{ __('Commencer la préparation') }}', ready: '{{ __('Prête') }}'};

    function avancer(orderId){
        advanceForm.action = ADVANCE_BASE + '/' + orderId + '/advance';
        advanceForm.submit();
    }
    window.kwAvancer = avancer;

    function esc(v){
        return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function minutesEcoulees(iso){
        if (!iso) { return 0; }
        return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    }

    function carte(o, peutAvancer){
        var age = minutesEcoulees(o.placed_at);
        var urgent = age >= 15;
        var html = '<div class="kw-carte' + (urgent ? ' kw-urgent' : '') + '">'
            + '<div class="kw-tete"><span class="kw-ref">' + esc(o.reference) + '</span>'
            + '<span class="kw-age">' + age + ' min</span></div>'
            + '<div class="kw-tag">' + esc(o.order_type_label)
            + (o.table_label ? ' · {{ __('Table') }} ' + esc(o.table_label) : '') + '</div>';

        (o.items || []).forEach(function(it){
            html += '<div class="kw-item"><div><div><span class="kw-qty">' + it.qty + '×</span> ' + esc(it.name) + '</div>'
                + (it.options && it.options.length ? '<div class="kw-opts">' + it.options.map(esc).join(', ') + '</div>' : '')
                + '</div></div>';
        });

        if (o.note) { html += '<div class="kw-note"><i class="fa-solid fa-note-sticky"></i> ' + esc(o.note) + '</div>'; }

        if (o.next_status && peutAvancer){
            html += '<button type="button" class="kw-avancer" onclick="kwAvancer(' + o.id + ')">'
                + esc(LABELS_AVANCER[o.next_status] || o.next_status) + '</button>';
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

                var idsActuels = orders.map(function(o){ return o.id; });
                if (connus !== null){
                    var nouveaux = idsActuels.filter(function(id){ return connus.indexOf(id) === -1; });
                    if (nouveaux.length && window.TagtoaSound){
                        // Une commande qui vient d'arriver doit s'entendre depuis
                        // l'autre bout de la cuisine, pas seulement se voir.
                        TagtoaSound.ok();
                    }
                }
                connus = idsActuels;

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
                grille.innerHTML = orders.map(function(o){ return carte(o, !!data.can_advance); }).join('');
            })
            .catch(function(){ etat.textContent = '{{ __('Connexion perdue — nouvel essai…') }}'; });
    }

    rafraichir();
    setInterval(rafraichir, 8000);
})();
</script>
@endpush
@endsection
