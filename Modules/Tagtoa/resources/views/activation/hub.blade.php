@extends('tagtoa::layouts.dashboard')
@section('title', __('Activer un produit'))
@section('page', __('Activer un produit'))

@section('content')
{{-- Un seul écran pour les deux mécanismes existants (Smart Stand : code à
     gratter ; Carte TAGTOA : UID NFC + PIN). On ne les fusionne PAS — ce sont
     deux preuves de possession différentes — on pose seulement la question
     « que tenez-vous ? » avant de montrer le bon formulaire. --}}
<div class="card" style="border-left:4px solid #2cb809">
    <b style="font-family:var(--fh,sans-serif)"><i class="fa-solid fa-bolt" style="color:#2cb809"></i> {{ __('Que tenez-vous en main ?') }}</b>
    <p style="color:var(--muted);font-size:13.5px;margin-top:6px">{{ __('Choisissez le produit, puis entrez le code qui est dessus.') }}</p>
</div>

<div class="grid g4" id="type-picker" style="margin-top:14px">
    <label class="type-card">
        <input type="radio" name="type_choice" value="stand:menu" hidden>
        <i class="fa-solid fa-utensils"></i>
        <b>{{ __('Smart Stand — Menu') }}</b>
        <span>{{ __('NFC + QR') }}</span>
    </label>
    <label class="type-card">
        <input type="radio" name="type_choice" value="stand:pay" hidden>
        <i class="fa-solid fa-money-bill-transfer"></i>
        <b>{{ __('Smart Stand — Paiement') }}</b>
        <span>{{ __('NFC') }}</span>
    </label>
    <label class="type-card">
        <input type="radio" name="type_choice" value="stand:links" hidden>
        <i class="fa-solid fa-link"></i>
        <b>{{ __('Smart Stand — Liens') }}</b>
        <span>{{ __('Carte de visite · NFC + QR') }}</span>
    </label>
    <label class="type-card">
        <input type="radio" name="type_choice" value="card" hidden>
        <i class="fa-solid fa-credit-card"></i>
        <b>{{ __('Carte NFC TAGTOA') }}</b>
        <span>{{ __('Carte prépayée / business card') }}</span>
    </label>
</div>

<style>
    .type-card{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:18px 12px;border:1.5px solid var(--bd);border-radius:16px;cursor:pointer;background:var(--surface);transition:border-color .15s,background .15s}
    .type-card i{font-size:22px;color:var(--blue-deep,#1a56db)}
    .type-card b{font-family:var(--fh,sans-serif);font-size:13.5px}
    .type-card span{color:var(--muted);font-size:11.5px}
    .type-card.selected{border-color:#2cb809;background:#eafaf3}
    @media(max-width:1100px){#type-picker.grid.g4{grid-template-columns:repeat(2,1fr)}}
</style>

{{-- ---------------- Formulaire STAND (Menu / Paiement / Liens) ---------------- --}}
<div class="card" id="form-stand" hidden style="margin-top:16px">
    <div class="h-row"><h2><i class="fa-solid fa-sign-hanging"></i> <span id="stand-titre"></span></h2></div>
    <ol style="color:var(--muted);font-size:14px;margin:10px 0 16px 18px;max-width:64ch;line-height:1.8">
        <li>{{ __('Grattez le panneau argenté au DOS du stand.') }}</li>
        <li>{{ __('Tapez le petit code qui apparaît, ou visez-le à la caméra.') }}</li>
        <li>{{ __('Posez le stand et passez au suivant — la caméra reste ouverte.') }}</li>
    </ol>

    <div class="row" style="align-items:flex-end">
        <div>
            <label class="lbl" for="prefixe">{{ __('Nommer les emplacements') }}</label>
            <input class="inp" id="prefixe" value="{{ $prefix }}" maxlength="30" placeholder="{{ __('Table') }}">
            <p style="color:var(--muted);font-size:12.5px;margin-top:6px">
                {{ __('Numérotés automatiquement. Prochain :') }} <b id="prochain">{{ $nextLabel }}</b>
            </p>
        </div>
    </div>

    <div class="row" style="align-items:flex-end;margin-top:10px">
        <div style="flex:2">
            <label class="lbl" for="code-manuel">{{ __('Code gratté (8 caractères)') }}</label>
            <input class="inp" id="code-manuel" placeholder="{{ __('Ex. A3F9K2MP') }}" maxlength="48" autocomplete="off">
        </div>
        <div style="flex:0 0 auto;min-width:auto">
            <button class="btn btn-p" id="valider-code" type="button"><i class="fa-solid fa-check"></i> {{ __('Activer') }}</button>
        </div>
        <div style="flex:0 0 auto;min-width:auto">
            <button class="btn btn-o" id="ouvrir" type="button"><i class="fa-solid fa-camera"></i> {{ __('Caméra') }}</button>
        </div>
    </div>

    <div class="h-row" style="margin-top:18px">
        <h2 style="margin:0">{{ __('Activés à l\'instant') }}</h2>
        <span class="pill g" id="compteur">0</span>
    </div>
    <div id="vide" class="empty" style="padding:26px 16px">
        <i class="fa-solid fa-qrcode"></i>
        {{ __('Rien encore. Entrez ou visez votre premier code gratté.') }}
    </div>
    <div id="liste" style="display:flex;flex-direction:column;gap:8px;margin-top:8px"></div>

    <div style="margin-top:16px;text-align:center">
        <a class="btn btn-o" href="{{ route('tagtoa.stand.index') }}"><i class="fa-solid fa-list"></i> {{ __('Voir tous mes stands') }}</a>
    </div>
</div>

{{-- ---------------- Formulaire CARTE TAGTOA ---------------- --}}
<div class="card" id="form-card" hidden style="margin-top:16px">
    <div class="h-row"><h2><i class="fa-solid fa-credit-card"></i> {{ __('Activer une carte NFC') }}</h2></div>
    <p style="color:var(--muted);font-size:13.5px;margin:0 0 14px">{{ __('Une carte NFC TAGTOA porte un solde utilisable chez tous vos points de vente (paiements, menu, événements, caisse). C\'est aussi la carte utilisée comme business card NFC.') }}</p>

    <form method="POST" action="{{ route('tagtoa.cards.store') }}">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
            <div>
                <label class="lbl">{{ __('UID de la carte (NFC)') }} *</label>
                <div style="display:flex;gap:8px">
                    <input class="inp" id="issue-uid" name="card_uid" value="{{ old('card_uid') }}" placeholder="{{ __('Tapez la carte…') }}" required>
                    <button type="button" class="btn btn-o" style="flex:0;white-space:nowrap" onclick="nfcInto(document.getElementById('issue-uid'))"><i class="fa-solid fa-wifi"></i></button>
                </div>
            </div>
            <div><label class="lbl">{{ __('Titulaire') }}</label><input class="inp" name="holder_name" value="{{ old('holder_name') }}" placeholder="{{ __('Nom (optionnel)') }}"></div>
            <div><label class="lbl">{{ __('Téléphone') }}</label><input class="inp" name="holder_phone" value="{{ old('holder_phone') }}" placeholder="+509 ..."></div>
            <div>
                <label class="lbl">{{ __('Devise') }}</label>
                <select class="sel inp" name="currency">
                    @foreach($currencies as $code => $label)
                        <option value="{{ $code }}" @selected(old('currency','HTG')===$code)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div><label class="lbl">{{ __('PIN (4-6 chiffres)') }} *</label><input class="inp" name="pin" inputmode="numeric" maxlength="6" pattern="\d{4,6}" value="{{ old('pin') }}" placeholder="{{ __('Obligatoire — sécurise la carte') }}" required></div>
            <div><label class="lbl">{{ __('Recharge initiale') }}</label><input class="inp" name="initial_amount" type="number" step="0.01" min="0" value="{{ old('initial_amount') }}" placeholder="0.00"></div>
        </div>
        <button class="btn btn-p" type="submit" style="margin-top:14px"><i class="fa-solid fa-plus"></i> {{ __('Activer la carte') }}</button>
    </form>

    <div style="margin-top:16px;text-align:center">
        <a class="btn btn-o" href="{{ route('tagtoa.cards.index') }}"><i class="fa-solid fa-list"></i> {{ __('Voir mes cartes') }}</a>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ route('tagtoa.asset', 'html5-qrcode.min.js') }}" defer></script>
<script src="{{ route('tagtoa.asset', 'tagtoa-scanner.js') }}" defer></script>
<script>
function nfcInto(input){
    if(!('NDEFReader' in window)){alert(@js(__('NFC non supporté ici. Saisissez l\'UID manuellement.')));return;}
    try{var r=new NDEFReader();r.scan().then(function(){r.onreading=function(e){if(e.serialNumber){input.value=e.serialNumber;}};}).catch(function(){alert(@js(__('Lecture NFC refusée.')));});}catch(err){}
}

window.addEventListener('load', function () {
    var STAND_TITRES = {
        menu:  @js(__('Smart Stand — Menu')),
        pay:   @js(__('Smart Stand — Paiement')),
        links: @js(__('Smart Stand — Liens'))
    };
    var URL_SCAN = "{{ route('tagtoa.stand.activate.scan') }}",
        JETON    = document.querySelector('meta[name=csrf-token]').content,
        liste    = document.getElementById('liste'),
        vide     = document.getElementById('vide'),
        compteur = document.getElementById('compteur'),
        prochain = document.getElementById('prochain'),
        prefixe  = document.getElementById('prefixe'),
        formStand = document.getElementById('form-stand'),
        formCard  = document.getElementById('form-card'),
        titreStand = document.getElementById('stand-titre'),
        faits    = 0,
        enCours  = false,
        moduleActuel = null;

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

        el.querySelector('b').textContent = id;
        el.querySelector('div div').textContent = r.message || '';
        el.querySelector('span').textContent = lb;

        liste.insertBefore(el, liste.firstChild);
        vide.style.display = 'none';

        if (ok) { faits++; compteur.textContent = faits; }
        if (r.next_label) { prochain.textContent = r.next_label; }
    }

    function envoyer(charge) {
        if (enCours || !charge) return;
        enCours = true;

        fetch(URL_SCAN, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': JETON, 'Accept': 'application/json' },
            // La cible (menu/pay/links) vient du TYPE choisi en haut d'écran —
            // avant cet écran, ce champ n'était jamais envoyé et chaque stand
            // retombait silencieusement sur « menu », quel que soit le produit
            // réellement déballé.
            body: JSON.stringify({ payload: charge, label_prefix: prefixe.value, target_module: moduleActuel })
        })
        .then(function (r) { return r.json(); })
        .then(ligne)
        .catch(function () {
            ligne({ result: 'erreur', message: @js(__('Connexion perdue. Réessayez ce stand.')) });
        })
        .then(function () { enCours = false; });
    }

    document.getElementById('valider-code').addEventListener('click', function () {
        var champ = document.getElementById('code-manuel'), val = champ.value.trim();
        if (!val) return;
        envoyer(val);
        champ.value = '';
        champ.focus();
    });
    document.getElementById('code-manuel').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { document.getElementById('valider-code').click(); }
    });

    if (window.TagtoaScanner) {
        TagtoaScanner.listenWedge(envoyer);
        document.getElementById('ouvrir').addEventListener('click', function () {
            TagtoaScanner.open({
                onCode: envoyer,
                once:   false,
                title:  @js(__('Visez le code gratté')),
                hint:   @js(__('Le petit code sous le panneau argenté, au dos du stand.')),
                submit: @js(__('Activer'))
            });
        });
    } else {
        document.getElementById('ouvrir').disabled = true;
    }

    // ---------------- Choix du type ----------------
    document.querySelectorAll('#type-picker .type-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('#type-picker .type-card').forEach(function (c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            card.querySelector('input').checked = true;

            var valeur = card.querySelector('input').value; // "stand:menu" | "stand:pay" | "stand:links" | "card"
            if (valeur === 'card') {
                moduleActuel = null;
                formCard.hidden = false;
                formStand.hidden = true;
            } else {
                moduleActuel = valeur.split(':')[1];
                titreStand.textContent = STAND_TITRES[moduleActuel] || '';
                formStand.hidden = false;
                formCard.hidden = true;
            }
        });
    });
});
</script>
@endpush
