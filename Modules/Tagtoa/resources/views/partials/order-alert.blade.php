{{-- TAGTOA — alerte « nouvelle commande » sur TOUTES les pages du tableau de bord.
     Le marchand n'a pas à ouvrir l'écran des commandes pour savoir qu'une
     commande est arrivée : pastille sur les onglets Menu / Commandes, bandeau
     et son. (L'écran Commandes sonne déjà lui-même — window.TAGTOA_ORDERS_PAGE —
     on n'y double pas le son.) --}}
@if(\Illuminate\Support\Facades\Route::has('tagtoa.menu.dashboard.orders.alerts'))
<style>
    .ord-bdg{position:absolute;top:4px;right:calc(50% - 22px);min-width:18px;height:18px;padding:0 5px;border-radius:9px;
        background:#e5484d;color:#fff;font:700 11px/18px var(--fb,system-ui);text-align:center;display:none}
    #ordAlert{display:none;position:fixed;left:12px;right:12px;bottom:calc(76px + env(safe-area-inset-bottom));z-index:95;
        background:var(--blue,#1a56db);color:#fff;border-radius:14px;padding:12px 14px;box-shadow:0 10px 30px rgba(0,0,0,.3);
        align-items:center;gap:10px;font-weight:700}
    #ordAlert a{color:#fff;text-decoration:underline;flex:1}
    #ordAlert button{border:0;border-radius:10px;padding:7px 10px;font-weight:700;background:#fff;color:#000}
</style>
<div id="ordAlert" role="alert">
    <i class="fa-solid fa-bell fa-shake"></i>
    <a href="{{ url('/tagtoa/menu/orders') }}">{{ __('Nouvelle commande !') }}</a>
    <button type="button" id="ordAlertStop">{{ __('Arrêter le son') }}</button>
</div>
<script src="{{ route('tagtoa.asset', 'tagtoa-sound.js') }}"></script>
<script>
(function () {
    var URL_ALERTS = @json(route('tagtoa.menu.dashboard.orders.alerts'));
    var KEY = 'tagtoa_seen_order';
    var bandeau = document.getElementById('ordAlert');
    var surPageCommandes = !!window.TAGTOA_ORDERS_PAGE;

    function lu() { try { var v = parseInt(localStorage.getItem(KEY), 10); return isNaN(v) ? null : v; } catch (e) { return null; } }
    function ecrire(v) { try { localStorage.setItem(KEY, String(v)); } catch (e) {} }

    function pastille(n) {
        document.querySelectorAll('.tabs a[href$="/tagtoa/menu"], .tabs a[href$="/tagtoa/orders"]').forEach(function (a) {
            var b = a.querySelector('.ord-bdg');
            if (!b) { b = document.createElement('span'); b.className = 'ord-bdg'; a.appendChild(b); }
            b.textContent = n > 99 ? '99+' : String(n);
            b.style.display = n > 0 ? 'block' : 'none';
        });
    }

    document.getElementById('ordAlertStop').addEventListener('click', function () {
        if (window.TagtoaSound) TagtoaSound.alerteStop();
        bandeau.style.display = 'none';
    });

    function verifier() {
        if (document.hidden) return;
        fetch(URL_ALERTS, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                if (!j) return;
                pastille(j.pending || 0);
                var vu = lu();
                if (vu === null) { ecrire(j.latest_id); return; }   // premier appareil : rien à annoncer
                if (j.latest_id > vu) {
                    ecrire(j.latest_id);
                    if (surPageCommandes) return;                   // cet écran sonne déjà
                    bandeau.style.display = 'flex';
                    if (window.TagtoaSound) { TagtoaSound.alerte(15); }
                }
            })
            .catch(function () { /* hors ligne : on réessaiera */ });
    }

    verifier();
    setInterval(verifier, 10000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) verifier(); });
})();
</script>
@endif
