/*
 * TAGTOA — le scanner de codes, une fois pour toutes.
 *
 * Trois écrans en ont besoin : la caisse (vendre), la fiche article (attribuer
 * un code) et le stock (recevoir une livraison). Recopier une caméra dans
 * chacun garantissait trois comportements légèrement différents et trois
 * endroits à corriger.
 *
 * Trois façons de lire, dans cet ordre, parce qu'une boutique haïtienne ne
 * choisit pas son matériel :
 *
 *   1. BarcodeDetector — natif sur Android/Chrome, rien à télécharger ;
 *   2. html5-qrcode — auto-hébergé, pour iOS/Safari et les navigateurs anciens ;
 *   3. la saisie au clavier — toujours disponible, et c'est aussi ce que fait
 *      une douchette USB ou Bluetooth : elle tape les chiffres puis Entrée.
 *
 * Ce fichier ne décide JAMAIS de rien. Il rend une chaîne de caractères. Le
 * serveur seul dit à quel article elle correspond, dans quel commerce, et à
 * quel prix — une décision prise dans le navigateur serait une décision que
 * n'importe qui peut réécrire.
 *
 * ES5 volontairement : beaucoup de téléphones en circulation ne sont pas neufs.
 */
(function (window, document) {
    'use strict';

    var CSS_ID = 'tagtoa-scanner-css';
    var OVERLAY_ID = 'tagtoa-scanner';

    /* Deux lectures du même code à moins de ce délai = la même lecture.
       Une caméra lit plusieurs fois par seconde : sans ce garde-fou, poser un
       article devant l'objectif l'ajouterait dix fois au panier. */
    var MEME_CODE_MS = 1500;

    var etat = {
        ouvert: false,
        arreter: null,      // fonction de fermeture du lecteur en cours
        dernierCode: '',
        dernierAt: 0,
        rappel: null,
        piste: null         // MediaStreamTrack, pour la torche
    };

    /* ------------------------------------------------------------------
       Feedback : on ne regarde pas l'écran quand on scanne.
       ------------------------------------------------------------------ */

    var audio;
    function bip(type) {
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            var o = audio.createOscillator(), g = audio.createGain();
            o.connect(g); g.connect(audio.destination);
            o.type = 'sine';
            o.frequency.value = (type === 'error') ? 200 : 880;
            g.gain.value = 0.12;
            o.start();
            setTimeout(function () { o.stop(); }, type === 'error' ? 240 : 110);
        } catch (e) { /* pas de son : le reste marche quand même */ }
        try {
            if (navigator.vibrate) navigator.vibrate(type === 'error' ? [60, 50, 60] : 40);
        } catch (e) {}
    }

    /* ------------------------------------------------------------------
       Le code lu.
       ------------------------------------------------------------------ */

    function nettoyer(code) {
        return String(code || '').toUpperCase().replace(/[^A-Z0-9\-]/g, '').substring(0, 48);
    }

    /** Filtre les relectures. Renvoie false si le code vient d'être traité. */
    function accepter(code) {
        var maintenant = Date.now();
        if (code === etat.dernierCode && (maintenant - etat.dernierAt) < MEME_CODE_MS) {
            return false;
        }
        etat.dernierCode = code;
        etat.dernierAt = maintenant;
        return true;
    }

    function livrer(code) {
        code = nettoyer(code);
        if (code.length < 4 || !accepter(code)) return;

        bip('ok');
        if (typeof etat.rappel === 'function') etat.rappel(code);
    }

    /* ------------------------------------------------------------------
       Lecteur 1 — BarcodeDetector natif.
       ------------------------------------------------------------------ */

    function demarrerNatif(video) {
        if (!('BarcodeDetector' in window)) return null;

        var detecteur;
        try {
            detecteur = new window.BarcodeDetector({
                formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128', 'code_39', 'itf', 'qr_code']
            });
        } catch (e) {
            return null; // formats refusés par ce navigateur
        }

        var vivant = true;
        function boucle() {
            if (!vivant) return;
            detecteur.detect(video).then(function (codes) {
                if (codes && codes.length) livrer(codes[0].rawValue);
            }).catch(function () { /* image illisible : on réessaie */ })
              .then(function () { if (vivant) setTimeout(boucle, 120); });
        }
        boucle();

        return function () { vivant = false; };
    }

    /* ------------------------------------------------------------------
       Lecteur 2 — html5-qrcode auto-hébergé (iOS, navigateurs anciens).
       ------------------------------------------------------------------ */

    function demarrerBibliotheque(conteneur) {
        if (!window.Html5Qrcode) return null;

        var lecteur = new window.Html5Qrcode(conteneur.id);
        lecteur.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 260, height: 160 } },
            function (texte) { livrer(texte); },
            function () { /* rien lu sur cette image */ }
        ).catch(function () { modeManuel(); });

        return function () {
            try { lecteur.stop().then(function () { lecteur.clear(); }); } catch (e) {}
        };
    }

    /* ------------------------------------------------------------------
       Lecteur 3 — la douchette USB / Bluetooth.

       Elle se présente comme un clavier : une rafale de caractères puis
       Entrée. On la reconnaît à la VITESSE de frappe, pas à un réglage — le
       commerçant branche sa douchette et elle marche, sans rien configurer.

       Actif même écran fermé : c'est tout l'intérêt d'une douchette au
       comptoir, on ne veut pas ouvrir une caméra avant chaque article.
       ------------------------------------------------------------------ */

    var DOUCHETTE_MS = 35;      // au-delà, c'est un humain qui tape
    var DOUCHETTE_MIN = 6;      // trop court : c'est une saisie normale

    function ecouterDouchette(rappel) {
        var tampon = '', dernier = 0;

        document.addEventListener('keydown', function (e) {
            var cible = e.target || {};
            var nom = (cible.tagName || '').toUpperCase();
            // Dans un champ de saisie, on laisse la personne écrire.
            if (nom === 'INPUT' || nom === 'TEXTAREA' || nom === 'SELECT' || cible.isContentEditable) {
                return;
            }

            var maintenant = Date.now();
            if (maintenant - dernier > DOUCHETTE_MS) tampon = '';
            dernier = maintenant;

            if (e.key === 'Enter') {
                if (tampon.length >= DOUCHETTE_MIN) {
                    var code = nettoyer(tampon);
                    tampon = '';
                    if (code.length >= 4 && accepter(code)) { bip('ok'); rappel(code); }
                }
                tampon = '';
                return;
            }

            if (e.key && e.key.length === 1) tampon += e.key;
        });
    }

    /* ------------------------------------------------------------------
       L'écran.
       ------------------------------------------------------------------ */

    function styles() {
        if (document.getElementById(CSS_ID)) return;
        var s = document.createElement('style');
        s.id = CSS_ID;
        s.textContent = [
            '#' + OVERLAY_ID + '{position:fixed;inset:0;z-index:9999;background:#0A0A0A;color:#fff;',
            'display:flex;flex-direction:column;font-family:system-ui,sans-serif}',
            '#' + OVERLAY_ID + ' .sc-top{display:flex;align-items:center;gap:12px;padding:14px 16px}',
            '#' + OVERLAY_ID + ' .sc-top h3{font-size:16px;font-weight:600;flex:1;margin:0}',
            '#' + OVERLAY_ID + ' .sc-btn{background:rgba(255,255,255,.16);color:#fff;border:0;border-radius:10px;',
            'padding:10px 14px;font-size:14px;cursor:pointer}',
            '#' + OVERLAY_ID + ' .sc-view{flex:1;position:relative;overflow:hidden;background:#000;min-height:200px}',
            '#' + OVERLAY_ID + ' .sc-view video{width:100%;height:100%;object-fit:cover}',
            /* La visée : on cadre un code-barres, pas un carré. */
            '#' + OVERLAY_ID + ' .sc-aim{position:absolute;left:8%;right:8%;top:50%;transform:translateY(-50%);',
            'height:132px;border:2px solid rgba(44,184,9,.9);border-radius:14px;box-shadow:0 0 0 9999px rgba(0,0,0,.45)}',
            '#' + OVERLAY_ID + ' .sc-foot{padding:14px 16px 22px;background:#141414}',
            '#' + OVERLAY_ID + ' .sc-hint{font-size:13px;opacity:.75;margin:0 0 10px}',
            /* LE RETOUR DE LECTURE, DANS L'ÉCRAN DU SCANNER.
               La caméra occupe tout l'écran : ce qui se passe DERRIÈRE elle
               est invisible. Sans cette ligne, un article ajouté au panier ne
               se manifestait que par un bip — et le caissier concluait que
               « ça a fait le son, puis plus rien ». */
            '#' + OVERLAY_ID + ' .sc-msg{font:700 15px/1.35 system-ui,sans-serif;margin:0 0 10px;',
            'padding:11px 13px;border-radius:11px;background:rgba(44,184,9,.18);color:#8ef07a;display:none}',
            '#' + OVERLAY_ID + ' .sc-msg.on{display:block}',
            '#' + OVERLAY_ID + ' .sc-msg.err{background:rgba(224,71,62,.22);color:#ffb3ae}',
            '#' + OVERLAY_ID + ' .sc-manual{display:flex;gap:8px}',
            '#' + OVERLAY_ID + ' .sc-manual input{flex:1;padding:13px;border-radius:10px;border:0;font-size:17px;',
            'letter-spacing:.06em;background:#fff;color:#0A0A0A}',
            '#' + OVERLAY_ID + ' .sc-manual button{background:#2cb809;color:#fff;border:0;border-radius:10px;',
            'padding:13px 18px;font-size:15px;font-weight:600;cursor:pointer}',
            '#' + OVERLAY_ID + ' .sc-flash{position:absolute;inset:0;background:#2cb809;opacity:0;pointer-events:none;',
            'transition:opacity .18s}',
            '#' + OVERLAY_ID + ' .sc-flash.on{opacity:.45}'
        ].join('');
        document.head.appendChild(s);
    }

    function construire(options) {
        styles();

        var o = document.createElement('div');
        o.id = OVERLAY_ID;
        o.setAttribute('role', 'dialog');
        o.setAttribute('aria-modal', 'true');
        o.innerHTML =
            '<div class="sc-top">' +
                '<h3></h3>' +
                '<button type="button" class="sc-btn sc-torch" hidden>&#128161;</button>' +
                '<button type="button" class="sc-btn sc-close">&#10005;</button>' +
            '</div>' +
            '<div class="sc-view" id="' + OVERLAY_ID + '-view">' +
                '<video playsinline muted autoplay></video>' +
                '<div class="sc-aim"></div>' +
                '<div class="sc-flash"></div>' +
            '</div>' +
            '<div class="sc-foot">' +
                '<p class="sc-msg" role="status" aria-live="polite"></p>' +
                '<p class="sc-hint"></p>' +
                '<form class="sc-manual">' +
                    '<input type="text" inputmode="text" autocomplete="off" autocapitalize="characters" spellcheck="false">' +
                    '<button type="submit"></button>' +
                '</form>' +
            '</div>';

        o.querySelector('h3').textContent = options.title || 'Scanner un code';
        o.querySelector('.sc-hint').textContent = options.hint ||
            'Visez le code-barres. Vous pouvez aussi le taper à la main, ou utiliser une douchette.';
        o.querySelector('.sc-manual button').textContent = options.submit || 'Valider';

        document.body.appendChild(o);
        return o;
    }

    function clignoter(overlay) {
        var f = overlay.querySelector('.sc-flash');
        if (!f) return;
        f.classList.add('on');
        setTimeout(function () { f.classList.remove('on'); }, 180);
    }

    /** Plus de caméra du tout : on garde la saisie, qui suffit à travailler. */
    function modeManuel() {
        var o = document.getElementById(OVERLAY_ID);
        if (!o) return;
        var vue = o.querySelector('.sc-view');
        if (vue) vue.style.display = 'none';
        o.querySelector('.sc-hint').textContent =
            'Caméra indisponible sur cet appareil. Tapez le code, ou utilisez une douchette.';
        var champ = o.querySelector('.sc-manual input');
        if (champ) champ.focus();
    }

    function torche(overlay, piste) {
        var bouton = overlay.querySelector('.sc-torch');
        if (!piste || !bouton) return;

        var possible = false;
        try {
            var c = piste.getCapabilities ? piste.getCapabilities() : {};
            possible = !!c.torch;
        } catch (e) {}

        if (!possible) return;

        // Une réserve mal éclairée, un code sombre : c'est ce qui fait la
        // différence entre « ça ne marche pas » et « ça marche ».
        bouton.hidden = false;
        var allumee = false;
        bouton.addEventListener('click', function () {
            allumee = !allumee;
            try { piste.applyConstraints({ advanced: [{ torch: allumee }] }); } catch (e) {}
        });
    }

    /* ------------------------------------------------------------------
       API publique.
       ------------------------------------------------------------------ */

    var Scanner = {
        /** Une caméra est-elle envisageable sur cet appareil ? */
        cameraPossible: function () {
            return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
        },

        /**
         * Ouvre le scanner.
         *
         * @param {Object} options
         *   onCode   fonction(code) — appelée à chaque lecture retenue
         *   once     true pour fermer après la première lecture
         *   title, hint, submit — libellés, traduits par l'appelant
         */
        open: function (options) {
            options = options || {};
            if (etat.ouvert) this.close();

            var overlay = construire(options);
            etat.ouvert = true;
            etat.rappel = function (code) {
                clignoter(overlay);
                if (options.onCode) options.onCode(code);
                if (options.once) Scanner.close();
            };
            // Appelé quand l'écran se ferme, quelle que soit la façon : croix,
            // Échap, `once`. Un écran qui doit se rafraîchir après une série de
            // lectures en a besoin — recharger à chaque bip couperait la caméra
            // et le marchand recommencerait tout.
            etat.surFermeture = typeof options.onClose === 'function' ? options.onClose : null;

            overlay.querySelector('.sc-close').addEventListener('click', function () { Scanner.close(); });

            // Saisie manuelle : toujours là, jamais un plan B honteux. Un code
            // abîmé se tape, et c'est souvent plus rapide que d'insister.
            overlay.querySelector('.sc-manual').addEventListener('submit', function (e) {
                e.preventDefault();
                var champ = overlay.querySelector('.sc-manual input');
                var code = nettoyer(champ.value);
                champ.value = '';
                if (code.length < 4) { bip('error'); return; }
                // Saisie volontaire : elle ne doit jamais être avalée par le
                // filtre anti-relecture.
                etat.dernierCode = '';
                livrer(code);
            });

            // Échap ferme, comme partout ailleurs.
            etat.echap = function (e) { if (e.key === 'Escape') Scanner.close(); };
            document.addEventListener('keydown', etat.echap);

            if (!this.cameraPossible()) { modeManuel(); return; }

            var video = overlay.querySelector('video');
            navigator.mediaDevices.getUserMedia({
                video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 } }
            }).then(function (flux) {
                video.srcObject = flux;
                etat.piste = flux.getVideoTracks()[0];
                torche(overlay, etat.piste);

                return video.play().catch(function () {}).then(function () {
                    var stop = demarrerNatif(video);
                    if (stop) { etat.arreter = stop; return; }

                    // Pas de détecteur natif : on repasse par la bibliothèque,
                    // qui gère elle-même sa propre caméra.
                    flux.getTracks().forEach(function (t) { t.stop(); });
                    video.srcObject = null;
                    etat.piste = null;

                    var vue = document.getElementById(OVERLAY_ID + '-view');
                    var stopLib = demarrerBibliotheque(vue);
                    if (stopLib) { etat.arreter = stopLib; return; }

                    modeManuel();
                });
            }).catch(function () {
                // Permission refusée, caméra occupée, page non sécurisée : on
                // ne bloque pas le commerçant, il tape son code.
                modeManuel();
            });
        },

        close: function () {
            var o = document.getElementById(OVERLAY_ID);

            if (etat.arreter) { try { etat.arreter(); } catch (e) {} }
            etat.arreter = null;

            // Rendre la caméra : la lampe qui reste allumée après une vente est
            // le genre de détail qui fait désinstaller une application.
            if (etat.piste) { try { etat.piste.stop(); } catch (e) {} etat.piste = null; }
            if (o) {
                var v = o.querySelector('video');
                if (v && v.srcObject) {
                    try { v.srcObject.getTracks().forEach(function (t) { t.stop(); }); } catch (e) {}
                    v.srcObject = null;
                }
                o.parentNode.removeChild(o);
            }

            if (etat.echap) { document.removeEventListener('keydown', etat.echap); etat.echap = null; }
            etat.ouvert = false;
            etat.rappel = null;

            // Appelé EN DERNIER, une seule fois : le rappel peut recharger la
            // page, et tout ce qui suivrait ne s'exécuterait jamais — la caméra
            // resterait allumée derrière.
            var fin = etat.surFermeture;
            etat.surFermeture = null;
            if (fin) { try { fin(); } catch (e) {} }
        },

        /** Écoute une douchette USB/Bluetooth, écran fermé compris. */
        listenWedge: function (rappel) {
            if (typeof rappel === 'function') ecouterDouchette(rappel);
        },

        /**
         * Dit ce qui vient de se passer, DANS l'écran du scanner.
         *
         * Indispensable : la caméra couvre la page, donc tout ce que l'écran
         * appelant affiche derrière elle est invisible. Un article ajouté au
         * panier sans cette ligne ne se manifeste que par un bip, et le
         * caissier conclut que le scanner ne fait rien.
         */
        say: function (texte, erreur) {
            var o = document.getElementById(OVERLAY_ID);
            if (!o) return;
            var m = o.querySelector('.sc-msg');
            if (!m) return;
            m.textContent = texte || '';
            m.className = 'sc-msg' + (texte ? ' on' : '') + (erreur ? ' err' : '');
        },

        /** Le scanner est-il ouvert ? L'appelant décide selon la réponse. */
        isOpen: function () { return !!etat.ouvert; },

        /** Signale un code refusé par le serveur (article introuvable). */
        reject: function () { bip('error'); },

        /** Efface le filtre anti-relecture — pour rescanner le même article. */
        forget: function () { etat.dernierCode = ''; etat.dernierAt = 0; }
    };

    window.TagtoaScanner = Scanner;
})(window, document);
