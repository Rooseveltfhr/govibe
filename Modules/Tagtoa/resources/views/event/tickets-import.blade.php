@extends('tagtoa::layouts.dashboard')
@section('title', __('Import billets imprimés'))
@section('page', $event->title)

@section('content')
<div class="h-row">
    <a href="{{ route('tagtoa.event.dashboard.index') }}" style="color:var(--muted);font-size:14px"><i class="fa-solid fa-arrow-left"></i> {{ __('Retour') }}</a>
</div>

<div class="grid g4">
    <div class="stat"><div class="ic"><i class="fa-solid fa-boxes-stacked"></i></div><div class="v">{{ $stats['imported'] }}</div><div class="k">{{ __('Billets importés') }}</div></div>
    <div class="stat"><div class="ic" style="background:#fff5e6;color:#7a5200"><i class="fa-solid fa-hourglass-half"></i></div><div class="v">{{ $stats['unsold'] }}</div><div class="k">{{ __('En attente de vente') }}</div></div>
    <div class="stat"><div class="ic" style="background:#eafaf3;color:#0e5f44"><i class="fa-solid fa-circle-check"></i></div><div class="v">{{ $stats['sold'] }}</div><div class="k">{{ __('Vendus') }}</div></div>
</div>

<div class="card" style="margin-top:16px">
    <h2>{{ __('Importer un lot de billets déjà imprimés') }}</h2>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">
        {{ __('Fichier CSV, une ligne par billet : le code du QR imprimé, puis (optionnel) le second code imprimé au dos, séparés par une virgule. Exemple :') }}
        <code>696bb99d76e28407,25H165407</code>
    </p>
    <form method="POST" action="{{ route('tagtoa.event.dashboard.tickets.import.store', $event->id) }}" enctype="multipart/form-data" style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        @csrf
        <input class="inp" type="file" name="file" accept=".csv,text/csv,text/plain" required style="flex:1;min-width:220px">
        <button class="btn btn-p"><i class="fa-solid fa-upload"></i> {{ __('Importer') }}</button>
    </form>
</div>

<div class="card" style="margin-top:16px">
    <h2>{{ __('Pas de liste ? Scannez chaque billet physique') }}</h2>
    <p style="color:var(--muted);font-size:14px;margin-top:4px">
        {{ __('Pas besoin de fichier : scannez le QR de chaque carte imprimée une à une avec la caméra — chaque scan l\'enregistre automatiquement comme billet disponible à la vente.') }}
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-top:10px">
        <button class="btn btn-p" id="scBtn" type="button"><i class="fa-solid fa-qrcode"></i> {{ __('Démarrer le scanner') }}</button>
        <span style="font-size:14px"><b id="scNew">0</b> {{ __('nouveaux') }} · <b id="scDup" style="color:var(--muted)">0</b> {{ __('déjà connus') }}</span>
    </div>
    <div id="scReader" style="margin-top:10px;max-width:340px;border-radius:12px;overflow:hidden;display:none"></div>
    <div id="scRes" class="scRes"><i id="scIcon" class="fa-solid"></i> <span id="scMsg"></span></div>
</div>

<div class="card" style="margin-top:16px">
    <div class="h-row"><h2>{{ __('En attente de vente') }} ({{ $pending->total() }})</h2></div>
    @if($pending->isEmpty())
        <div class="empty"><i class="fa-solid fa-ticket"></i>{{ __('Aucun billet importé en attente. Importez un fichier ci-dessus.') }}</div>
    @else
    <div style="overflow-x:auto"><table>
        <tr><th>{{ __('Code (QR)') }}</th><th>{{ __('Serial (dos)') }}</th><th>{{ __('Importé le') }}</th><th></th></tr>
        @foreach($pending as $t)
        <tr>
            <td><code>{{ $t->code }}</code></td>
            <td>{{ $t->serial ?: '—' }}</td>
            <td style="color:var(--muted)">{{ $t->created_at->format('d/m/y H:i') }}</td>
            <td>
                <form method="POST" action="{{ route('tagtoa.event.dashboard.tickets.destroy', [$event->id, $t->id]) }}" onsubmit="return confirm('{{ __('Retirer ce billet de la liste ?') }}')" style="display:inline">@csrf @method('DELETE')
                    <button class="btn btn-o btn-sm" style="color:var(--red)"><i class="fa-solid fa-trash"></i></button>
                </form>
            </td>
        </tr>
        @endforeach
    </table></div>
    <div style="margin-top:14px">{{ $pending->links() }}</div>
    @endif
</div>

@push('head')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}"></script>
<style>
    .scRes{display:none;border-radius:14px;padding:18px;text-align:center;margin-top:10px;font:800 22px var(--fh);align-items:center;justify-content:center;gap:10px}
    .scRes.show{display:flex}
    .scRes.green{background:#eafaf3;color:#0e5f44;border:1px solid var(--green)}
    .scRes.red{background:#fdecea;color:#9a2820;border:1px solid var(--red)}
</style>
@endpush

@push('scripts')
<script>
(function(){
    var CSRF = document.querySelector('meta[name=csrf-token]').content;
    var URL_SCAN = @json(route('tagtoa.event.dashboard.tickets.scan-import', $event->id));
    var btn = document.getElementById('scBtn'), reader = document.getElementById('scReader');
    var res = document.getElementById('scRes'), icon = document.getElementById('scIcon'), msg = document.getElementById('scMsg');
    var nNew = document.getElementById('scNew'), nDup = document.getElementById('scDup');
    var qr = null, on = false, busy = false, lastCode = null, lastAt = 0;
    var newCount = 0, dupCount = 0;

    /* ---------- Retour sonore (succès/erreur) ---------- */
    var _actx;
    function beep(t){
        try{_actx=_actx||new (window.AudioContext||window.webkitAudioContext)();var o=_actx.createOscillator(),g=_actx.createGain();o.connect(g);g.connect(_actx.destination);var f={success:[880,1320],error:[200,160]}[t]||[600];o.frequency.value=f[0];o.type='sine';g.gain.value=.12;o.start();if(f[1])setTimeout(function(){o.frequency.value=f[1];},90);setTimeout(function(){o.stop();},t==='error'?260:170);}catch(e){}
        if(navigator.vibrate){try{navigator.vibrate(t==='success'?80:[60,40,60]);}catch(e){}}
    }

    function showRes(kind, text){
        res.className = 'scRes show ' + (kind === 'success' ? 'green' : 'red');
        icon.className = 'fa-solid ' + (kind === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation');
        msg.textContent = text;
        beep(kind);
    }

    function stop(){
        on = false; reader.style.display = 'none';
        if (qr) { try { qr.stop().then(function(){ try { qr.clear(); } catch(e){} }).catch(function(){}); } catch(e){} }
        btn.innerHTML = '<i class="fa-solid fa-qrcode"></i> {{ __('Démarrer le scanner') }}';
    }

    function handle(code){
        code = (code || '').trim();
        if (!code) return;
        var now = Date.now();
        if (code === lastCode && (now - lastAt) < 2500) return; // évite le double-scan de la même carte
        lastCode = code; lastAt = now;
        if (busy) return;
        busy = true;
        fetch(URL_SCAN, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':CSRF}, body: JSON.stringify({code: code})})
            .then(function(r){ return r.json(); })
            .then(function(j){
                busy = false;
                if (j && j.ok) {
                    if (j.status === 'duplicate') { dupCount++; nDup.textContent = dupCount; showRes('error', '{{ __('Oups, déjà scanné !') }}'); }
                    else { newCount++; nNew.textContent = newCount; showRes('success', '{{ __('Scanné avec succès !') }}'); }
                } else {
                    showRes('error', '{{ __('Erreur, réessayez.') }}');
                }
            })
            .catch(function(){ busy = false; showRes('error', '{{ __('Erreur, réessayez.') }}'); });
    }

    btn.addEventListener('click', function(){
        if (on) { stop(); return; }
        if (!window.Html5Qrcode) { showRes('error', '{{ __('Caméra/QR indisponible sur cet appareil.') }}'); return; }
        reader.style.display = 'block';
        qr = new Html5Qrcode('scReader');
        qr.start({facingMode:'environment'}, {fps:10, qrbox:220}, handle, function(){})
            .then(function(){ on = true; btn.innerHTML = '<i class="fa-solid fa-stop"></i> {{ __('Arrêter le scanner') }}'; })
            .catch(function(){ showRes('error', '{{ __('Caméra/QR indisponible sur cet appareil.') }}'); reader.style.display = 'none'; });
    });
})();
</script>
@endpush
@endsection
