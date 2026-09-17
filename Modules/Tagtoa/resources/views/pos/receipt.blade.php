{{-- TAGTOA POS — un reçu de caisse, calé sur les mini-imprimantes thermiques.

     58 mm, c'est la largeur des rouleaux vendus partout en Haïti et celle des
     terminaux « smart » que les commerces achètent réellement. Sans @page, le
     navigateur imprime en A4 : le ticket sort au milieu d'une feuille blanche,
     sur deux pages, et l'imprimante thermique le coupe net à droite sans
     prévenir. C'est ce qui arrivait.

     La zone imprimable utile d'un rouleau 58 mm fait 48 mm : les 5 mm de chaque
     côté ne reçoivent pas d'encre. Tout ce qui dépasse est perdu. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sale->reference }} — {{ $business->name ?? 'TAGTOA' }}</title>
    <style>
        :root{--bd:rgba(0,0,0,.2)}
        *{box-sizing:border-box;margin:0;padding:0}
        body{background:#efefed;font:12px/1.45 ui-monospace,'SFMono-Regular',Menlo,'Courier New',monospace;
             color:#000;padding:18px 12px}
        .recu{background:#fff;width:100%;max-width:302px;margin:0 auto;padding:14px 12px;
              box-shadow:0 4px 20px rgba(0,0,0,.1)}
        .c{text-align:center}
        .ent b{font:700 15px/1.3 var(--fh,inherit);display:block;letter-spacing:.04em;text-transform:uppercase}
        .ent div{font-size:11px;line-height:1.4}
        .logo{max-width:120px;max-height:52px;object-fit:contain;margin:0 auto 6px;display:block}
        .sep{border-top:1px dashed var(--bd);margin:8px 0}
        .l{display:flex;justify-content:space-between;gap:6px}
        .l .n{flex:1;min-width:0}
        .art{margin-bottom:3px}
        .art .nm{word-break:break-word}
        .art .dt{display:flex;justify-content:space-between;color:#333;font-size:11px}
        .tot{font-weight:700;font-size:14px}
        .mut{color:#444;font-size:10.5px}
        /* Le mot du patron. `white-space:pre-line` garde ses retours à la ligne :
           « Merci… » et « Pas de retour » sont deux phrases, pas un pavé. */
        .pied{white-space:pre-line;word-break:break-word;font-size:11px;line-height:1.45}
        .barre{display:flex;gap:8px;max-width:302px;margin:14px auto 0}
        .barre button,.barre a{flex:1;padding:11px;border:0;border-radius:10px;background:#0A0A0A;color:#fff;
             font:600 13px system-ui,sans-serif;cursor:pointer;text-align:center;text-decoration:none}
        .barre .o{background:#fff;color:#0A0A0A;border:1.5px solid var(--bd)}

        /* Bluetooth : masqué par défaut, révélé par le script UNIQUEMENT si
           `navigator.bluetooth` existe sur cet appareil. Le serveur ne peut
           pas le savoir — c'est une capacité du navigateur, pas du commerce. */
        .bt{display:none;max-width:302px;margin:14px auto 0;padding:12px 13px;border-radius:12px;
            background:#eef2ff;font:13px/1.5 system-ui,sans-serif;color:#1a2a5e}
        .bt.on{display:block}
        .bt p{margin:0 0 9px}
        .bt .rang{display:flex;gap:8px}
        .bt button{flex:1;padding:9px;border:0;border-radius:9px;background:#2743c7;color:#fff;
                   font:600 12.5px system-ui,sans-serif;cursor:pointer}
        .bt button.o{background:#fff;color:#2743c7;border:1.5px solid #2743c7}
        .bt button:disabled{opacity:.5;cursor:not-allowed}
        .bt .msg{margin-top:8px;font-size:12px;font-weight:600}
        .bt .msg.err{color:#a3231b}
        .bt .msg.ok{color:#0e5f2e}

        /* ---------------------------------------------------------------
           À L'IMPRESSION — un rouleau, pas une feuille.
           --------------------------------------------------------------- */
        @page{
            /* `auto` en hauteur : le ticket fait la longueur qu'il fait. Une
               hauteur fixe couperait la dernière ligne d'une grosse commande,
               ou cracherait du papier blanc après une petite. */
            size: 58mm auto;
            margin: 0;
        }
        @media print{
            html,body{background:#fff;width:58mm;padding:0;margin:0}
            /* 48 mm utiles : 5 mm de chaque bord ne reçoivent pas d'encre. */
            .recu{box-shadow:none;width:58mm;max-width:none;padding:0 5mm;margin:0}
            .barre{display:none}
            /* Rien ne doit se couper au milieu d'un article. */
            .art,.l{page-break-inside:avoid;break-inside:avoid}
        }
    </style>
</head>
<body>
    <div class="recu">

        {{-- EN-TÊTE : le commerce, pas la caisse.
             Un client qui revient contester présente son ticket ; il doit y
             lire chez QUI il a acheté, et comment le joindre. « Caisse 1 » ne
             lui dit rien. --}}
        <div class="c ent">
            @if($business?->logo_url)
                <img class="logo" src="{{ $business->logo_url }}" alt="">
            @endif
            <b>{{ $business->name ?? ($sale->terminal->name ?? 'TAGTOA') }}</b>
            @if($business?->address)<div>{{ $business->address }}</div>@endif
            @if($business?->phone)<div>{{ __('Tél') }} : {{ $business->phone }}</div>@endif
            @if($business?->tax_number)<div>{{ __('NIF') }} : {{ $business->tax_number }}</div>@endif
        </div>

        <div class="sep"></div>

        <div class="l mut"><span class="n">{{ optional($sale->sold_at)->format('d/m/Y H:i') }}</span>
            <span>{{ $sale->terminal->name ?? '' }}</span></div>
        <div class="l mut"><span class="n">{{ __('Reçu') }}</span><span>{{ $sale->reference }}</span></div>
        @if($sale->staff)
            <div class="l mut"><span class="n">{{ __('Servi par') }}</span><span>{{ $sale->staff->name }}</span></div>
        @endif

        <div class="sep"></div>

        {{-- TOUTES les lignes de la commande. Un reçu qui en oublie une est
             pire que pas de reçu : c'est une contestation garantie. --}}
        @foreach($sale->items as $it)
            <div class="art">
                <div class="nm">{{ $it->name }}</div>
                <div class="dt">
                    <span>{{ rtrim(rtrim(number_format($it->qty, 3, '.', ''), '0'), '.') }}
                          × {{ number_format($it->price, 2) }}</span>
                    <span>{{ number_format($it->line_total, 2) }}</span>
                </div>
            </div>
        @endforeach

        <div class="sep"></div>

        <div class="l"><span class="n">{{ __('Sous-total') }}</span>
            <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->subtotal, $sale->currency) }}</span></div>

        @if((float) $sale->discount > 0)
            <div class="l"><span class="n">{{ __('Remise') }}</span>
                <span>-{{ \Modules\Tagtoa\App\Support\Money::format($sale->discount, $sale->currency) }}</span></div>
        @endif

        {{-- La taxe est FIGÉE sur la vente : le reçu montre ce qui a été
             collecté ce jour-là, pas ce que dirait le taux d'aujourd'hui. --}}
        @if((float) $sale->tax_total > 0)
            <div class="l"><span class="n">{{ $sale->tax_label ?: __('Taxe') }}</span>
                <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->tax_total, $sale->currency) }}</span></div>
        @endif

        <div class="sep"></div>

        <div class="l tot"><span class="n">{{ __('TOTAL') }}</span>
            <span>{{ \Modules\Tagtoa\App\Support\Money::format($sale->total, $sale->currency) }}</span></div>

        @if(is_array($sale->payments) && $sale->payments !== [])
            <div class="sep"></div>
            @foreach($sale->payments as $p)
                <div class="l mut">
                    <span class="n">{{ \Modules\Tagtoa\App\Models\Pos\Sale::METHODS[$p['method'] ?? ''] ?? ($p['method'] ?? '') }}</span>
                    <span>{{ \Modules\Tagtoa\App\Support\Money::format($p['amount'] ?? 0, $sale->currency) }}</span>
                </div>
            @endforeach
        @endif

        <div class="sep"></div>

        {{-- LE MOT DU PATRON. C'est là que se règle une contestation : ce qui
             est imprimé sur le ticket que le client tient fait foi, et une
             règle annoncée après la vente ne vaut rien. --}}
        <div class="c pied">{{ $business?->receipt_footer ?: __('Merci de votre confiance !') }}</div>

        <div class="c mut" style="margin-top:6px">tagtoa.com</div>
    </div>

    <div class="barre">
        <button onclick="window.print()">{{ __('Imprimer') }}</button>
        <a class="o" href="{{ route('tagtoa.pos.tickets') }}">{{ __('Retour') }}</a>
    </div>

    {{-- IMPRESSION BLUETOOTH DIRECTE — seulement pour les imprimantes BLE.
         Le bloc reste invisible si le navigateur ne sait pas ce qu'est le
         Bluetooth Web ; le texte dit clairement à qui il ne s'adresse pas,
         plutôt que de laisser un commerçant chercher son imprimante SPP dans
         une liste où elle n'apparaîtra jamais. --}}
    <div class="bt" id="btBox">
        <p>
            <b>{{ __('Imprimante Bluetooth (BLE)') }}</b><br>
            {{ __('Marche uniquement avec les imprimantes Bluetooth récentes (BLE). Pour un modèle plus ancien (Bluetooth classique), utilisez « Imprimer » ci-dessus avec le service d\'impression installé sur votre téléphone.') }}
        </p>
        <div class="rang">
            <button class="o" id="btChoisir" type="button">{{ __('Choisir mon imprimante') }}</button>
            <button id="btImprimer" type="button" disabled>{{ __('Imprimer par Bluetooth') }}</button>
        </div>
        <p class="msg" id="btMsg" hidden></p>
    </div>

    <script src="{{ route('tagtoa.asset', 'tagtoa-bt-printer.js') }}" defer></script>
    <script>
    /* Ouvert depuis la caisse avec ?print=1 : on lance l'impression tout de
       suite. Le caissier a déjà appuyé sur « Imprimer » ; lui redemander de
       cliquer, client devant lui, c'est un geste de trop. */
    (function () {
        if (location.search.indexOf('print=1') === -1) { return; }
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 250); });
    })();

    /* Le bloc Bluetooth : révélé seulement si `navigator.bluetooth` existe.
       Un bouton affiché sur un appareil qui ne sait pas ce qu'il fait produit
       une erreur silencieuse au clic — pire qu'un bouton absent. */
    window.addEventListener('load', function () {
        if (!window.TagtoaBTPrinter || !TagtoaBTPrinter.available()) return;

        var box = document.getElementById('btBox');
        var msg = document.getElementById('btMsg');
        var boutonChoisir = document.getElementById('btChoisir');
        var boutonImprimer = document.getElementById('btImprimer');
        var url = "{{ route('tagtoa.pos.receipt.data', $sale->reference) }}";

        box.classList.add('on');

        function dire(texte, type) {
            msg.textContent = texte;
            msg.className = 'msg' + (type ? ' ' + type : '');
            msg.hidden = !texte;
        }

        boutonChoisir.addEventListener('click', function () {
            dire('');
            TagtoaBTPrinter.choisir().then(function (device) {
                dire(@js(__('Imprimante choisie : ')) + (device.name || '?'), 'ok');
                boutonImprimer.disabled = false;
            }).catch(function (e) {
                // L'utilisateur a fermé la fenêtre de choix : ce n'est pas une
                // panne, on ne l'annonce pas comme telle.
                if (e && e.name === 'NotFoundError') return;
                dire(@js(__('Aucune imprimante Bluetooth trouvée à proximité.')), 'err');
            });
        });

        boutonImprimer.addEventListener('click', function () {
            boutonImprimer.disabled = true;
            dire(@js(__('Impression…')));
            TagtoaBTPrinter.imprimer(url).then(function () {
                dire(@js(__('Ticket envoyé.')), 'ok');
                boutonImprimer.disabled = false;
            }).catch(function () {
                dire(@js(__('Échec de l\'impression. Réessayez, ou utilisez « Imprimer ».')), 'err');
                boutonImprimer.disabled = false;
            });
        });
    });
    </script>
</body>
</html>
