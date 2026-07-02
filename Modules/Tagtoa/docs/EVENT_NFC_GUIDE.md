# TAGTOA EVENT — Guide NFC : cartes, check-in, wallet (closed-loop)

Comment le module Événement fonctionne avec des cartes NFC, de l'encodage à
l'entrée, au paiement et au remboursement. Exemple fil rouge : **ArtiboGroup**
organise le **Festival Couleur**, 14–15 août, avec des **cartes NFC**.

---

## 1. Principes (règles demandées)

| Règle | Comment c'est garanti |
|---|---|
| **Carte réutilisable** | La carte n'a pas de valeur « en dur » dessus. On lit son **UID matériel** (numéro de série). L'UID est **mappé** à un participant/billet PAR événement (`tagtoa_ev_nfc_tags.event_id + uid_hash`). Après le festival, la même carte physique est ré-encodée pour l'événement suivant (nouvelle ligne). |
| **Recharger pour payer les marchands** | `TopUp` (recharge) crédite le wallet ; `Charge` (achat) débite le participant → crédite le stand. |
| **Pas de transfert entre utilisateurs** | Il n'existe **aucune** action « transfert ». Les seuls flux sont `top_up`, `purchase`, `refund`, `payout`. Un participant ne peut jamais envoyer son solde à un autre participant. |
| **Remboursement uniquement** | `Refund` renvoie le solde non utilisé (participant → clearing → remboursement via la méthode d'origine). |
| **Organisateur voit les entrées + notification** | Au tap d'entrée, le nom s'affiche sur le scanner ET un e-mail est envoyé à `notify_email` de l'événement. |
| **Participant notifié de son entrée** | WhatsApp (numéro du porteur) à l'entrée : « Bienvenue ! Votre entrée est confirmée. » |

> **Important** : c'est de la **valeur stockée en circuit fermé**, PAS un compte
> bancaire. Le solde ne s'utilise que chez les stands du réseau de l'événement ;
> il n'est pas retirable en cash par le participant (seulement remboursable).

---

## 2. Deux identifiants distincts sur une carte

Une même carte NFC porte, côté TAGTOA, **deux liens** :
1. **Billet** (`tagtoa_ev_tickets.code`) → sert au **check-in** (entrée).
2. **Wallet** (`tagtoa_ev_wallet_accounts`) → sert au **paiement** chez les stands.

Les deux sont reliés à la carte par son **UID** via `tagtoa_ev_nfc_tags`
(`uid_hash` = SHA-256 de l'UID ; l'UID n'est jamais stocké en clair).
L'action `EncodeParticipantCard` crée les deux d'un coup.

---

## 3. Encodage des cartes — méthode recommandée : **UID (pas d'écriture)**

On **n'écrit rien** sur la carte : on lit son UID (numéro de série) et on le
**relie** dans TAGTOA. Avantages : simple, sûr (rien à cloner/lire dessus),
carte réutilisable. Web NFC (`NDEFReader.serialNumber` sur Chrome Android)
fournit l'UID au tap ; sinon on saisit l'UID à la main.

### Procédure « point de vente » (Festival Couleur)
1. ArtiboGroup crée l'événement **Festival Couleur** (`/tagtoa/event` → Nouvel événement), dates 14–15 août.
2. Crée les **types de billets** (ex. *Pass 2 jours*, *VIP*).
3. Renseigne l'**e-mail organisateur** : `/tagtoa/event/{id}/wallet` → « Notifications organisateur ».
4. Pour **chaque carte vendue** : `/tagtoa/event/{id}/wallet` → « **Encoder une carte (entrée + wallet)** » :
   - taper/tap la carte → **UID**,
   - saisir **nom** + **téléphone WhatsApp**,
   - choisir le **type de billet**,
   - (option) **recharge initiale** (ex. 500 G),
   - « Encoder la carte » → un **billet** est émis + un **wallet** est créé et lié à la carte.
5. Remettre la carte au participant. (Recharges supplémentaires : « Recharger un wallet » avec l'UID.)

### Alternative : écriture NDEF
Écrire un token sur la carte est possible mais déconseillé pour du closed-loop
(lecture/clonage plus faciles, gestion de sécurité en plus). L'approche UID est
le standard des bracelets/cartes festival cashless.

---

## 4. Le jour J

### 4.1 Entrée (check-in) — `/tagtoa/event/{id}/scanner`
- Bouton **« Check-in NFC (tap) »** → le participant tape sa carte.
- TAGTOA résout `UID → billet`, marque l'entrée (une seule fois), et affiche le **nom** du participant + son type de billet.
- **Anti-fraude** : un billet = une entrée (ré-entrée → « Déjà entré à HH:MM »).
- **Notifications automatiques** :
  - **Organisateur** : e-mail « Participant entré : {nom} — HH:MM ».
  - **Participant** : WhatsApp « Bienvenue ! Votre entrée est confirmée. ».
- Le QR des billets marche toujours (et **hors-ligne**). Le check-in **NFC** a besoin d'une connexion (la carte ne porte que l'UID ; la résolution est côté serveur).

### 4.2 Paiement chez les stands — `/tagtoa/event/{id}/wallet/terminal`
- Le stand choisit son nom, tape la carte → voit le **solde**, saisit le montant → **Encaisser**.
- Débit **atomique** avec **verrou** (pas de double-débit), refus si solde insuffisant.
- Le participant reçoit un WhatsApp « Achat : {montant} — {stand}. Nouveau solde : … ».

### 4.3 Recharge
- Comptoir de recharge : `/tagtoa/event/{id}/wallet` → « Recharger un wallet » (UID + montant + réf paiement). *(Le top-up par API MonCash/carte arrivera avec les passerelles PAY ; pour l'instant recharge manuelle/sur preuve.)*

### 4.4 Après l'événement
- **Réconciliation** des stands + **Régler** (payout vendeur → organisateur) + **export CSV**.
- **Remboursement** du solde non utilisé des participants (action `Refund`).

---

## 5. Où c'est dans le code

| Élément | Emplacement |
|---|---|
| Résolution UID → billet | `Services/Event/CheckinService::resolveNfcCode()` |
| Check-in + notifications | `CheckinService::processScan()` + `notifyEntry()` |
| Endpoint check-in NFC | `POST /tagtoa/event/{id}/scan-nfc` |
| Encodage carte (billet+wallet) | `Actions/Event/Wallet/EncodeParticipantCard` |
| Wallet (recharge/achat/refund/payout) | `Actions/Event/Wallet/*` + `Support/Event/Ledger` |
| Terminal stand | `/tagtoa/event/{id}/wallet/terminal` |
| Notifications (email + WhatsApp) | `Services/Notifications/NotificationService` |

---

## 6. Reste à faire (bloqué côté identifiants)
- **Recharge par API de paiement réel** (MonCash/NatCash/carte) → drivers PAY (credentials).
- **Envoi WhatsApp réel** → `TAGTOA_WA_NOTIFY=true` + `TAGTOA_TWILIO_*` sur le VPS.
- **E-mail réel** → `TAGTOA_NOTIFY=true` + `MAIL_*` sur le VPS.
