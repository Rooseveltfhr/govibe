/*
 * TAGTOA — imprimer un reçu directement sur une imprimante Bluetooth BLE.
 *
 * ── LA LIMITE QU'IL FAUT DIRE AVANT TOUT ─────────────────────────────────
 *
 * « Web Bluetooth », l'API que ce fichier utilise, ne parle QU'AU BLUETOOTH
 * BASSE ÉNERGIE (BLE / GATT). Ce n'est pas un choix de TAGTOA : aucun
 * navigateur, sur aucun appareil, n'expose le Bluetooth CLASSIQUE (SPP) à une
 * page web — la norme l'exclut délibérément, pour la même raison qu'une page
 * web ne peut pas non plus lire un fichier au hasard sur le disque.
 *
 * Or la majorité des mini-imprimantes 58 mm vendues en Haïti et dans les
 * Caraïbes — celles à 25-40 dollars, posées à côté d'un terminal de paiement —
 * sont en Bluetooth CLASSIQUE. Ce module ne les fera JAMAIS apparaître dans la
 * fenêtre d'appairage : ce n'est pas un bug à corriger plus tard, c'est un mur.
 *
 * Pour CES imprimantes-là, le seul chemin qui marche depuis un navigateur est
 * celui qui existait déjà : `window.print()` via le reçu HTML 58 mm, envoyé au
 * système d'impression d'Android, qu'un service d'impression Bluetooth
 * installé une fois (l'imprimante en vend presque toujours un) redirige vers
 * le papier. Ce module ne remplace pas ce chemin — il en ouvre un second, pour
 * les imprimantes BLE, sans détour par la boîte de dialogue d'impression.
 *
 * ── CE QUE CE FICHIER NE FAIT PAS ────────────────────────────────────────
 *
 * Il ne calcule AUCUN montant. Chaque chiffre qu'il imprime vient déjà formaté
 * du serveur (voir TicketController::data) — un ticket qui recalculerait son
 * propre affichage finirait, un jour, par ne plus dire la même chose que le
 * reçu HTML du même client.
 *
 * ES5 volontairement : beaucoup de téléphones en circulation ne sont pas neufs.
 */
(function (window) {
    'use strict';

    /**
     * Services BLE connus des imprimantes ESC/POS bon marché.
     *
     * Il n'existe PAS de service Bluetooth « imprimante » standard, contrairement
     * à un clavier ou un casque audio : chaque fabricant a choisi le sien. Cette
     * liste couvre les familles les plus répandues sur les modèles génériques
     * 58 mm (souvent revendus sous des dizaines de marques différentes). Une
     * imprimante absente de cette liste ne sera pas trouvée — ce n'est pas une
     * erreur, c'est l'état réel du marché du Bluetooth bon marché.
     */
    var SERVICES_CONNUS = [
        '000018f0-0000-1000-8000-00805f9b34fb', // famille la plus répandue (générique 58mm)
        '49535343-fe7d-4ae5-8fa9-9fafd205e455', // variante courante (module BLE HM-10-like)
        '0000ff00-0000-1000-8000-00805f9b34fb'  // autre variante générique
    ];

    /* Une seule connexion à la fois : imprimer deux reçus en parallèle sur le
       même appareil produirait un ticket entrelacé, illisible. */
    var etat = { device: null, caracteristique: null };

    /** Cette page peut-elle ENVISAGER le Bluetooth ? */
    function disponible() {
        return !!(window.navigator && navigator.bluetooth);
    }

    /* ------------------------------------------------------------------
       ESC/POS — mise en page, en octets.

       PURE, testable sans Bluetooth : donné le même JSON, elle produit
       toujours les mêmes octets. C'est ce qui permet de vérifier la mise en
       page (centrage, largeur de ligne, coupe du papier) sans imprimante sous
       la main.
       ------------------------------------------------------------------ */

    var ESC = '\x1B', GS = '\x1D';
    var INIT = ESC + '@';
    var CENTRE = ESC + 'a' + '\x01', GAUCHE = ESC + 'a' + '\x00';
    var GRAS_ON = ESC + 'E' + '\x01', GRAS_OFF = ESC + 'E' + '\x00';
    var COUPE = GS + 'V' + '\x01';

    /** Largeur d'un rouleau 58 mm en police normale : ~32 caractères. */
    var COLONNES = 32;

    function repeter(car, n) {
        var s = '';
        for (var i = 0; i < n; i++) s += car;
        return s;
    }

    /** Coupe un texte en lignes d'au plus `n` caractères, sans couper un mot en deux si possible. */
    function envelopper(texte, n) {
        texte = String(texte || '');
        if (texte.length <= n) return [texte];

        var mots = texte.split(' '), lignes = [], courante = '';
        for (var i = 0; i < mots.length; i++) {
            var essai = courante ? courante + ' ' + mots[i] : mots[i];
            if (essai.length > n && courante) {
                lignes.push(courante);
                courante = mots[i];
            } else {
                courante = essai;
            }
        }
        if (courante) lignes.push(courante);
        return lignes;
    }

    /** Une ligne « libellé … montant », alignée sur les deux bords. */
    function ligneMontant(libelle, montant) {
        libelle = String(libelle || ''); montant = String(montant || '');
        var espace = COLONNES - libelle.length - montant.length;
        return espace > 0 ? libelle + repeter(' ', espace) + montant : (libelle + ' ' + montant).substring(0, COLONNES);
    }

    /**
     * Construit le ticket complet en ESC/POS, à partir du JSON du serveur.
     *
     * @param {Object} d  la réponse de /pos/receipt/{reference}/data
     * @return {string}   une chaîne d'octets (0-255), prête pour l'écriture
     */
    function construire(d) {
        var l = [];

        l.push(INIT, CENTRE, GRAS_ON);
        var nom = envelopper(d.business && d.business.name, COLONNES);
        for (var i = 0; i < nom.length; i++) l.push(nom[i] + '\n');
        l.push(GRAS_OFF);

        if (d.business && d.business.address) {
            var adr = envelopper(d.business.address, COLONNES);
            for (i = 0; i < adr.length; i++) l.push(adr[i] + '\n');
        }
        if (d.business && d.business.phone) l.push(d.business.phone + '\n');
        if (d.business && d.business.tax_number) l.push('NIF ' + d.business.tax_number + '\n');

        l.push(GAUCHE, repeter('-', COLONNES) + '\n');
        l.push((d.sold_at || '') + '\n');
        l.push((d.reference || '') + '\n');
        if (d.staff) l.push('Servi par ' + d.staff + '\n');
        l.push(repeter('-', COLONNES) + '\n');

        var items = d.items || [];
        for (i = 0; i < items.length; i++) {
            var it = items[i];
            var nomArticle = envelopper(it.name, COLONNES);
            for (var j = 0; j < nomArticle.length; j++) l.push(nomArticle[j] + '\n');
            l.push(ligneMontant(it.qty + ' x ' + it.price, it.line_total) + '\n');
        }

        l.push(repeter('-', COLONNES) + '\n');
        l.push(ligneMontant('Sous-total', d.subtotal) + '\n');
        if (d.discount) l.push(ligneMontant('Remise', '-' + d.discount) + '\n');
        if (d.tax_total) l.push(ligneMontant(d.tax_label || 'Taxe', d.tax_total) + '\n');
        l.push(repeter('-', COLONNES) + '\n');
        l.push(GRAS_ON, ligneMontant('TOTAL', d.total) + '\n', GRAS_OFF);

        var pays = d.payments || [];
        if (pays.length) {
            l.push(repeter('-', COLONNES) + '\n');
            for (i = 0; i < pays.length; i++) {
                l.push(ligneMontant(pays[i].label, pays[i].amount) + '\n');
            }
        }

        l.push(repeter('-', COLONNES) + '\n', CENTRE);
        var pied = envelopper(d.footer, COLONNES);
        for (i = 0; i < pied.length; i++) l.push(pied[i] + '\n');

        // Marge de coupe : sans elle, le rouleau tranche dans le pied de page.
        l.push('\n\n\n', COUPE);

        return l.join('');
    }

    /** Chaîne d'octets → Uint8Array : Web Bluetooth n'écrit que des octets. */
    function octets(chaine) {
        var tab = new Uint8Array(chaine.length);
        for (var i = 0; i < chaine.length; i++) tab[i] = chaine.charCodeAt(i) & 0xFF;
        return tab;
    }

    /**
     * Écrit par PETITS MORCEAUX, avec une pause entre chacun.
     *
     * Le défaut le plus fréquent des imprimantes BLE bon marché : leur puce
     * accepte un paquet d'écriture de 20 octets, pas plus — au-delà, l'écriture
     * échoue en silence et la moitié du ticket manque. Aucune norme ne le
     * garantit ; c'est un fait du matériel, appris à l'usage.
     */
    function ecrireParMorceaux(caracteristique, donnees, taille, resolve, reject) {
        var pos = 0;
        function suite() {
            if (pos >= donnees.length) { resolve(); return; }
            var morceau = donnees.subarray(pos, pos + taille);
            caracteristique.writeValue(morceau).then(function () {
                pos += taille;
                setTimeout(suite, 20);
            }).catch(reject);
        }
        suite();
    }

    /** Trouve, parmi les services connus, une caractéristique inscriptible. */
    function trouverCaracteristique(serveur) {
        var essais = SERVICES_CONNUS.slice();

        function essayer() {
            if (!essais.length) return Promise.reject(new Error('aucun service connu trouvé'));
            var uuid = essais.shift();
            return serveur.getPrimaryService(uuid)
                .then(function (service) { return service.getCharacteristics(); })
                .then(function (cars) {
                    for (var i = 0; i < cars.length; i++) {
                        if (cars[i].properties.write || cars[i].properties.writeWithoutResponse) {
                            return cars[i];
                        }
                    }
                    return Promise.reject(new Error('service sans caractéristique inscriptible'));
                })
                .catch(essayer);
        }

        return essayer();
    }

    var BTPrinter = {
        available: disponible,

        /** Construit le ticket ESC/POS — exposé pour être vérifié sans imprimante. */
        build: construire,

        /** Un appareil est-il déjà appairé, prêt à recevoir sans redemander ? */
        connected: function () {
            return !!(etat.device && etat.device.gatt && etat.device.gatt.connected);
        },

        /**
         * Demande à l'utilisateur de choisir son imprimante Bluetooth.
         *
         * DOIT être appelé depuis un geste utilisateur direct (un clic) : le
         * navigateur refuse d'ouvrir ce sélecteur autrement, pour empêcher
         * qu'une page appaire un appareil à l'insu de la personne.
         */
        choisir: function () {
            if (!disponible()) return Promise.reject(new Error('indisponible'));

            return navigator.bluetooth.requestDevice({
                filters: [{ services: SERVICES_CONNUS }]
            }).then(function (device) {
                etat.device = device;
                etat.caracteristique = null;
                return device;
            });
        },

        /**
         * Imprime le reçu à l'adresse `url` (le point JSON du serveur).
         *
         * @return {Promise} résolue quand le ticket est écrit, jamais avant.
         */
        imprimer: function (url) {
            if (!disponible()) return Promise.reject(new Error('indisponible'));
            if (!etat.device) return Promise.reject(new Error('aucune imprimante choisie'));

            return fetch(url)
                .then(function (r) {
                    if (!r.ok) throw new Error('reçu introuvable');
                    return r.json();
                })
                .then(function (donnees) {
                    var ticket = octets(construire(donnees));

                    var connecte = etat.device.gatt.connected
                        ? Promise.resolve(etat.device.gatt)
                        : etat.device.gatt.connect();

                    return connecte.then(function (serveur) {
                        if (etat.caracteristique) return etat.caracteristique;
                        return trouverCaracteristique(serveur).then(function (c) {
                            etat.caracteristique = c;
                            return c;
                        });
                    }).then(function (caracteristique) {
                        return new Promise(function (resolve, reject) {
                            ecrireParMorceaux(caracteristique, ticket, 20, resolve, reject);
                        });
                    });
                });
        },

        /** Oublie l'imprimante choisie — pour en changer sans recharger la page. */
        oublier: function () {
            if (etat.device && etat.device.gatt && etat.device.gatt.connected) {
                try { etat.device.gatt.disconnect(); } catch (e) {}
            }
            etat.device = null;
            etat.caracteristique = null;
        }
    };

    window.TagtoaBTPrinter = BTPrinter;
})(window);
