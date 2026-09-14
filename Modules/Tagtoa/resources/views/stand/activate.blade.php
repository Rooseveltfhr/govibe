@extends('tagtoa::layouts.dashboard')
@section('title', __('Activer mes stands'))
@section('page', __('Activer mes stands'))

@section('content')
{{-- L'écran est fait pour être tenu d'une main, debout dans une salle, pendant
     qu'on pose les stands sur les tables. Tout ce qui demanderait de s'asseoir —
     un formulaire par stand, une page qui se recharge — a été retiré. --}}
<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)">
        <i class="fa-solid fa-bolt" style="color:#2cb809"></i> {{ __('Quarante tables, quatre minutes') }}
    </b>
    <ol style="color:var(--muted);font-size:14px;margin:10px 0 0 18px;max-width:64ch;line-height:1.8">
        <li>{{ __('Grattez le panneau argenté au DOS du stand.') }}</li>
        <li>{{ __('Visez le petit code qui apparaît — pas le grand QR du dessus.') }}</li>
        <li>{{ __('Posez le stand sur la table et passez au suivant. La caméra reste ouverte.') }}</li>
    </ol>
</div>

<div class="card">
    <div class="row" style="align-items:flex-end">
        <div>
            <label class="lbl" for="prefixe">{{ __('Nommer les emplacements') }}</label>
            <input class="inp" id="prefixe" value="{{ $prefix }}" maxlength="30"
                   placeholder="{{ __('Table') }}">
            <p style="color:var(--muted);font-size:12.5px;margin-top:6px">
                {{ __('Numérotés automatiquement. Prochain :') }}
                <b id="prochain">{{ $nextLabel }}</b>
            </p>
        </div>
        <div style="flex:0 0 auto;min-width:auto">
            <button class="btn btn-p" id="ouvrir" type="button" style="width:100%">
                <i class="fa-solid fa-camera"></i> {{ __('Ouvrir la caméra') }}
            </button>
        </div>
    </div>
</div>

<div class="card">
    <div class="h-row">
        <h2>{{ __('Activés à l\'instant') }}</h2>
        <span class="pill g" id="compteur">0</span>
    </div>

    <div id="vide" class="empty" style="padding:30px 16px">
        <i class="fa-solid fa-qrcode"></i>
        {{ __('Rien encore. Ouvrez la caméra et visez votre premier code gratté.') }}
        @if($deja > 0)
            <div style="margin-top:8px;font-size:13px">
                {{ __('Vous avez déjà :count stand(s) activé(s).', ['count' => $deja]) }}
                <a href="{{ route('tagtoa.stand.index') }}" style="color:var(--blue-deep);font-weight:700">{{ __('Les voir') }}</a>
            </div>
        @endif
    </div>

    <div id="liste" style="display:flex;flex-direction:column;gap:8px"></div>
</div>

<div style="margin-top:16px;text-align:center">
    <a class="btn btn-o" href="{{ route('tagtoa.stand.index') }}">
        <i class="fa-solid fa-list"></i> {{ __('Voir tous mes stands') }}
    </a>
</div>
@endsection

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script>
window.addEventListener('load', function () {
    var URL_SCAN = "{{ route('tagtoa.stand.activate.scan') }}",
        JETON    = document.querySelector('meta[name=csrf-token]').content,
        liste    = document.getElementById('liste'),
        vide     = document.getElementById('vide'),
        compteur = document.getElementById('compteur'),
        prochain = document.getElementById('prochain'),
        prefixe  = document.getElementById('prefixe'),
        faits    = 0,
        enCours  = false;

    /* Les verdicts qui comptent comme un succès. Le reste s'affiche aussi —
       un marchand doit VOIR ce qui n'a pas marché, sinon il croit avoir
       activé quarante stands alors qu'il en a trente-huit. */
    var REUSSI = { ok: 1, already_mine: 1 };

    function ligne(r) {
        var ok = !!REUSSI[r.result],
            el = document.createElement('div'),
            id = (r.stand && r.stand.public_id) || '—',
            lb = (r.stand && r.stand.label) || '';

        el.style.cssText = 'display:flex;gap:10px;align-items:center;padding:11px 13px;border-radius:12px;'
            + 'border:1.5px solid ' + (ok ? 'var(--green)' : 'var(--red)')
            + ';background:' + (ok ? '#eafaf3' : '#fdecea');

        el.innerHTML =
            '<i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-exclamation')
            + '" style="color:' + (ok ? '#0e5f44' : '#9a2820') + '"></i>'
            + '<div style="flex:1;min-width:0">'
            + '<b style="font-family:monospace;font-size:13.5px"></b>'
            + '<div style="font-size:13px;color:#444"></div></div>'
            + '<span style="font:600 13px var(--fh);white-space:nowrap"></span>';

        /* textContent, jamais innerHTML, sur tout ce qui vient du serveur. */
        el.querySelector('b').textContent = id;
        el.querySelector('div div').textContent = r.message || '';
        el.querySelector('span').textContent = lb;

        liste.insertBefore(el, liste.firstChild);
        vide.style.display = 'none';

        if (ok) { faits++; compteur.textContent = faits; }
        if (r.next_label) { prochain.textContent = r.next_label; }
    }

    function envoyer(charge) {
        /* Une lecture à la fois : deux requêtes simultanées prendraient le même
           numéro de table, puisque le libellé se calcule côté serveur. */
        if (enCours) return;
        enCours = true;

        fetch(URL_SCAN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': JETON, 'Accept': 'application/json' },
            /* Le secret voyage en CORPS de requête. Jamais dans l'URL : une URL
               finit dans l'historique, dans le Referer et dans le journal
               d'accès du serveur. */
            body: JSON.stringify({ payload: charge, label_prefix: prefixe.value })
        })
        .then(function (r) { return r.json(); })
        .then(ligne)
        .catch(function () {
            ligne({ result: 'erreur', message: "{{ __('Connexion perdue. Réessayez ce stand.') }}" });
        })
        .then(function () { enCours = false; });
    }

    if (!window.TagtoaScanner) {
        document.getElementById('ouvrir').disabled = true;
        return;
    }

    /* Une douchette branchée sur un ordinateur tape le code comme un clavier :
       même chemin, sans caméra. */
    TagtoaScanner.listenWedge(envoyer);

    document.getElementById('ouvrir').addEventListener('click', function () {
        TagtoaScanner.open({
            onCode: envoyer,
            /* La caméra NE se ferme PAS après une lecture : c'est tout l'objet
               de cet écran. Refermer entre chaque table rendrait les quarante
               activations aussi longues qu'avant. */
            once:   false,
            title:  "{{ __('Visez le code gratté') }}",
            hint:   "{{ __('Le petit code sous le panneau argenté, au dos du stand.') }}",
            submit: "{{ __('Activer') }}"
        });
    });
});
</script>
@endpush
