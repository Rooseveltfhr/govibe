@extends('tagtoa::layouts.dashboard')
@section('title', __('Écran cuisine'))
@section('page', __('Cuisine') . ' — ' . $menu->name)

{{--
    TAGTOA MENU — écran cuisine : LECTURE SEULE, polling, son.

    Pas un écran qu'on navigue : un écran qu'on pose sur le plan de travail
    et qu'on regarde du coin de l'œil toute la journée. Il n'y a donc aucun
    bouton d'action ici (changer un statut reste sur l'écran « Commandes ») —
    juste ce qu'il faut préparer, la plus vieille commande en premier, et un
    son qui prévient sans qu'il faille lire l'écran.
--}}

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.menu.dashboard.orders',$menu->id) }}" class="btn btn-o btn-sm" style="flex:0"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
    <h2 style="flex:1">{{ __('Cuisine') }} — {{ $menu->name }}</h2>
    <span id="kw-etat" style="color:var(--muted);font-size:12.5px"></span>
</div>

<div id="kw-vide" class="card" style="display:none"><div class="empty"><i class="fa-solid fa-mug-hot"></i>{{ __('Aucune commande à préparer.') }}</div></div>

<div id="kw-grille" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:14px"></div>

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
</style>

@push('scripts')
<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}"></script>
<script>
(function(){
    var FEED = @json(route('tagtoa.menu.dashboard.kitchen.feed', $menu->id));
    var connus = null; // null = premier chargement : pas d'alerte sur ce qui existait déjà
    var etat = document.getElementById('kw-etat');
    var grille = document.getElementById('kw-grille');
    var vide = document.getElementById('kw-vide');

    function esc(v){
        return String(v == null ? '' : v).replace(/[&<>"']/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    function minutesEcoulees(iso){
        if (!iso) { return 0; }
        return Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 60000));
    }

    function carte(o){
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

                vide.style.display = orders.length ? 'none' : 'block';
                grille.innerHTML = orders.map(carte).join('');
            })
            .catch(function(){ etat.textContent = '{{ __('Connexion perdue — nouvel essai…') }}'; });
    }

    rafraichir();
    setInterval(rafraichir, 8000);
})();
</script>
@endpush
@endsection
