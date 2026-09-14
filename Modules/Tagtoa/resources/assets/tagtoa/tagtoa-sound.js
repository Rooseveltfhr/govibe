/*!
 * TAGTOA — le retour sonore, partout pareil.
 *
 * Pourquoi un son, et pourquoi il est indispensable ici :
 *
 * Un caissier haïtien en pleine affluence ne REGARDE pas son écran entre deux
 * clients. Il tape au doigt, les yeux sur la file. Un article ajouté sans bruit
 * est un article dont on ne sait pas s'il est passé — alors on retape, et le
 * client paie deux fois la même bière. C'est l'erreur la plus banale d'une
 * caisse tactile, et c'est un son qui l'évite, pas un message à l'écran.
 *
 * Trois règles :
 *
 *   1. AUCUN FICHIER. Les sons sont synthétisés par Web Audio. Un .mp3, c'est
 *      une requête réseau de plus au moment précis où la connexion est
 *      mauvaise — et un silence quand elle est coupée, c'est-à-dire justement
 *      quand la caisse hors ligne doit continuer de rassurer.
 *
 *   2. DÉBLOQUÉ AU PREMIER GESTE. Les navigateurs mobiles refusent tout son
 *      avant une interaction de l'utilisateur. On démarre donc le contexte
 *      audio au premier toucher, une fois, en silence.
 *
 *   3. JAMAIS BLOQUANT. Web Audio absent, contexte refusé, appareil muet :
 *      chaque appel échoue en silence. Un son est un confort, jamais une
 *      condition pour vendre.
 *
 * ES5 volontairement : ces écrans tournent sur des téléphones Android bon
 * marché dont le navigateur n'est pas à jour.
 */
(function (global) {
    'use strict';

    var ctx = null,
        actif = true;

    function contexte() {
        if (ctx) return ctx;
        var C = global.AudioContext || global.webkitAudioContext;
        if (!C) return null;
        try { ctx = new C(); } catch (e) { return null; }
        return ctx;
    }

    /**
     * Une note. `type` reste 'sine' : une onde pure porte mieux dans le bruit
     * d'une salle qu'un carré, qui devient strident au volume où on l'entend.
     */
    function note(freq, debut, duree, volume) {
        var c = contexte();
        if (!c || !actif) return;

        try {
            var osc = c.createOscillator(),
                gain = c.createGain(),
                t = c.currentTime + debut;

            osc.type = 'sine';
            osc.frequency.setValueAtTime(freq, t);

            // Attaque nette, extinction douce : un son coupé net « clique »
            // sur les petits haut-parleurs.
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(volume, t + 0.012);
            gain.gain.exponentialRampToValueAtTime(0.0001, t + duree);

            osc.connect(gain);
            gain.connect(c.destination);
            osc.start(t);
            osc.stop(t + duree + 0.02);
        } catch (e) { /* muet : jamais bloquant */ }
    }

    var Sound = {
        /** Coupe ou rétablit le retour sonore (réglage d'un marchand). */
        muet: function (oui) { actif = !oui; },

        /**
         * AJOUT AU PANIER — le son le plus important de TAGTOA.
         *
         * Deux notes qui MONTENT, fort et court. Monter dit « c'est entré » ;
         * descendre dirait le contraire, et le caissier retaperait.
         */
        add: function () {
            note(880, 0, 0.07, 0.34);
            note(1318.5, 0.055, 0.1, 0.30);
        },

        /** Retrait : les mêmes notes, à l'envers. Personne n'a à l'apprendre. */
        remove: function () {
            note(1318.5, 0, 0.06, 0.20);
            note(880, 0.05, 0.08, 0.18);
        },

        /** Encaissement terminé : trois notes, plus longues, sans ambiguïté. */
        ok: function () {
            note(659.3, 0, 0.09, 0.30);
            note(880, 0.09, 0.09, 0.30);
            note(1318.5, 0.18, 0.2, 0.28);
        },

        /** Refus : une note grave, tenue. Jamais agréable, c'est le but. */
        error: function () {
            note(196, 0, 0.26, 0.28);
        },

        /**
         * Débloque le son. À appeler au premier geste de l'utilisateur —
         * `armer()` le fait tout seul, mais un écran peut vouloir le forcer.
         */
        reveiller: function () {
            var c = contexte();
            if (c && c.state === 'suspended') { try { c.resume(); } catch (e) {} }
        },

        /** Pose les écouteurs qui débloquent le son au premier toucher. */
        armer: function () {
            function une() {
                Sound.reveiller();
                document.removeEventListener('touchstart', une, true);
                document.removeEventListener('mousedown', une, true);
                document.removeEventListener('keydown', une, true);
            }
            document.addEventListener('touchstart', une, true);
            document.addEventListener('mousedown', une, true);
            document.addEventListener('keydown', une, true);
        }
    };

    global.TagtoaSound = Sound;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { Sound.armer(); });
    } else {
        Sound.armer();
    }
})(window);
