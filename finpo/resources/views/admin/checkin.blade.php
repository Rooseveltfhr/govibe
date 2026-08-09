@extends('layouts.admin', ['title' => 'Check-in'])

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <h1 class="h3 mb-0">Check-in</h1>
    <div class="d-flex gap-3 small fp-muted">
        <span>✅ <strong id="stat-in">{{ $checkedIn }}</strong> / {{ $total }} enregistrés</span>
        <span>🕐 {{ $lastHour }} dernière heure</span>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="fp-card p-4">
            <h2 class="h5 mb-3">📷 Scanner QR</h2>
            <div id="qr-reader" class="rounded-3 overflow-hidden" style="max-width: 420px;"></div>
            <button id="qr-start" class="btn btn-fp-primary btn-sm mt-3">Démarrer la caméra</button>
            <p class="fp-muted small mt-2 mb-0">Scannez le QR du billet ou du badge. Le check-in est automatique.</p>
        </div>
        <div class="fp-card p-4 mt-3">
            <h2 class="h5 mb-3">⌨️ Saisie manuelle</h2>
            <div class="input-group">
                <input id="manual-code" class="form-control" placeholder="N° de billet (FINPO26-…) ou token QR">
                <button id="manual-go" class="btn btn-fp-primary">Valider</button>
            </div>
            <hr style="border-color: var(--fp-card-border);">
            <label class="form-label small" for="search-q">Recherche participant</label>
            <input id="search-q" class="form-control" placeholder="Nom, email…">
            <div id="search-results" class="d-grid gap-2 mt-2"></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="fp-card p-4 position-sticky" style="top: 1rem;">
            <h2 class="h5 mb-3">Résultat</h2>
            <div id="scan-result" class="text-center fp-muted py-5">En attente de scan…</div>
        </div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('assets/vendor/html5-qrcode.min.js') }}"></script>
<script>
(function () {
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const resultBox = document.getElementById('scan-result');
    const statIn = document.getElementById('stat-in');
    let lastCode = '';
    let lastTime = 0;

    async function send(code, method) {
        try {
            const res = await fetch(@json(route('admin.checkin.scan')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ code: code, method: method }),
            });
            render(await res.json());
        } catch (e) {
            resultBox.innerHTML = '<div class="alert alert-danger">Erreur réseau.</div>';
        }
    }

    function render(data) {
        const themes = { ok: 'success', already: 'warning', refused: 'danger', not_found: 'danger' };
        const icons = { ok: '✅', already: '⚠️', refused: '⛔', not_found: '❓' };
        let html = '<div class="alert alert-' + (themes[data.status] || 'secondary') + ' text-start">';
        html += '<div style="font-size:2rem;">' + (icons[data.status] || '') + '</div>';
        if (data.name) {
            html += '<h3 class="h4 mb-1">' + data.name + '</h3>';
            html += '<p class="mb-1">' + (data.number || '') + ' · ' + (data.category || '') + ' · ' + (data.audience || '') + '</p>';
        }
        html += '<strong>' + data.message + '</strong></div>';
        resultBox.innerHTML = html;
        if (data.status === 'ok') statIn.textContent = parseInt(statIn.textContent, 10) + 1;
        if (navigator.vibrate) navigator.vibrate(data.status === 'ok' ? 80 : [60, 60, 60]);
    }

    document.getElementById('qr-start').addEventListener('click', function () {
        this.disabled = true;
        const reader = new Html5Qrcode('qr-reader');
        reader.start({ facingMode: 'environment' }, { fps: 8, qrbox: 230 }, (text) => {
            const now = Date.now();
            if (text === lastCode && now - lastTime < 4000) return;
            lastCode = text; lastTime = now;
            send(text, 'qr');
        }, () => {});
    });

    document.getElementById('manual-go').addEventListener('click', () => {
        const code = document.getElementById('manual-code').value.trim();
        if (code) send(code, 'manual');
    });
    document.getElementById('manual-code').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); document.getElementById('manual-go').click(); }
    });

    let searchTimer;
    document.getElementById('search-q').addEventListener('input', function () {
        clearTimeout(searchTimer);
        const q = this.value.trim();
        searchTimer = setTimeout(async () => {
            if (q.length < 2) { document.getElementById('search-results').innerHTML = ''; return; }
            const res = await fetch(@json(route('admin.checkin.search')) + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            document.getElementById('search-results').innerHTML = data.results.map((r) =>
                '<button type="button" class="btn btn-fp-outline btn-sm text-start" data-token="' + r.token + '">' +
                (r.checked_in ? '✅ ' : '') + r.name + ' <small class="fp-muted">· ' + r.number + ' · ' + (r.category || '') + '</small></button>'
            ).join('') || '<span class="fp-muted small">Aucun résultat.</span>';
            document.querySelectorAll('#search-results [data-token]').forEach((btn) => {
                btn.addEventListener('click', () => send(btn.dataset.token, 'manual'));
            });
        }, 300);
    });
})();
</script>
@endpush
@endsection
