# TAGTOA EVENT — Extension : Réservation + Accès NFC/QR + Wallet closed-loop

> **Statut : ÉTAPES 1–2 IMPLÉMENTÉES — en attente de REVUE DU LEDGER avant les Actions argent.**
> Conforme à la demande : schéma DB d'abord, implémentation par étapes,
> tests par étape, **aucun déploiement prod sans revue de la logique de ledger**.
>
> - ✅ Étape 1 — migrations + modèles (`tagtoa_ev_nfc_tags`, `_wallet_accounts`,
>   `_wallet_txns`, `_wallet_entries`) + modèles Eloquent.
> - ✅ Étape 2 — `Support\Event\Ledger` (logique pure double-entry) + `LedgerTest`
>   (10 tests Unit : signes, équilibre, fonds suffisants, idempotence d'exception).
> - ⏸️ **STOP — revue du ledger requise** (gate #4) avant les classes Action argent
>   (TopUp/Charge/Refund/Payout). Non mergé, non déployé.

---

## 0. Ce qui EXISTE déjà (on étend, on ne duplique pas)

| Table existante | Rôle | Couvre quoi du cahier des charges |
|---|---|---|
| `tagtoa_ev_events` | Événement (tenant_id, titre, alias, lieu, dates, devise, is_free, is_published, pay_page_id) | §1 modèle Event |
| `tagtoa_ev_ticket_types` | Types de billets (prix, quantity, sold, is_active) | §1 TicketType + quota |
| `tagtoa_ev_orders` | Achat/réservation (reference, buyer_*, total, payment_method, status, paid_at) | §1 Reservation (niveau commande) |
| `tagtoa_ev_tickets` | Billet individuel (`code` unique = QR, holder, status, checked_in, checked_in_at) | §1 Ticket + identifiant unique + QR |
| `tagtoa_ev_checkins` | Journal de scan (direction, method `qr`/`nfc`, gate, `client_uuid`, unique[ticket_id,client_uuid]) | §2 accès, offline idempotent |

Services existants : `TicketService`, `CheckinService`. Contrôleurs : `Public`, `Dashboard`, `Checkin` (scanner).

**Conclusion :** §1 (réservation/billetterie) et §2 (contrôle d'accès QR + offline idempotent)
sont **déjà en place**. L'extension se concentre sur :
- le **mapping NFC** (UID tag → billet/wallet),
- le **wallet closed-loop en double-entry**,
- les **notifications** (extension du `NotificationService` existant).

`Reservation` du cahier des charges = `tagtoa_ev_orders` + `tagtoa_ev_tickets` existants
(pas de nouvelle table « reservations » : on réutilise). À valider.

---

## 1. Règles d'architecture imposées par le projet (CLAUDE.md)

- **Tables** : jamais modifier l'existant. Uniquement de nouvelles tables `tagtoa_*`
  (ici `tagtoa_ev_*`). Liens vers les tables existantes via FK dans les NOUVELLES tables
  (on ne touche pas `tagtoa_ev_tickets`).
- **Multi-tenant** : colonne `tenant_id` (string, nullable, index) sur chaque nouvelle
  table, dérivée du serveur via `Support\Tenant::id()` — cohérent avec tout le module.
  *(Divergence assumée vs « stancl global scopes » : le module TAGTOA utilise déjà le
  scoping par colonne `tenant_id`, on reste cohérent.)*
- **Argent** : stocké en **entiers (unités mineures)** + `currency`. Jamais de float.
  HTG = 0 décimale, USD = 2 décimales (table `config('tagtoa.currencies')` donne `decimals`).
- **Logique métier** en classes Action mono-responsabilité (pas dans les contrôleurs),
  conforme au cahier des charges. Cœur calculable extrait en logique pure (testable CI).

---

## 2. Nouvelles tables (plan de migrations)

### 2.1 `tagtoa_ev_nfc_tags` — mapping tag physique/virtuel → billet/wallet
```
id                bigint PK
tenant_id         string  nullable index
event_id          FK tagtoa_ev_events  cascadeOnDelete
uid_hash          string(64) unique     -- SHA-256 de l'UID NFC (jamais l'UID en clair)
uid_enc           text nullable         -- UID chiffré (Crypt::encryptString) si réémission nécessaire
label             string nullable       -- "Bracelet #128"
kind              string(12) default 'card'   -- card | wristband | virtual
ticket_id         FK tagtoa_ev_tickets nullable nullOnDelete
status            string(12) default 'active' -- active | lost | disabled
assigned_at       timestamp nullable
timestamps
index (event_id, status)
```
- L'UID NFC n'est **jamais** stocké en clair : on indexe `uid_hash` (lookup O(1)) et on
  garde `uid_enc` chiffré seulement si on doit ré-afficher/ré-imprimer.
- Le wallet est atteint via `ticket_id` → ou directement via `wallet_account.nfc_tag_id`
  (voir 2.2) selon le mode. MVP : 1 tag ↔ 1 wallet_account d'event.

### 2.2 `tagtoa_ev_wallet_accounts` — comptes de valeur (closed-loop)
```
id                bigint PK
tenant_id         string nullable index
event_id          FK tagtoa_ev_events nullable  -- null = compte système global
nfc_tag_id        FK tagtoa_ev_nfc_tags nullable nullOnDelete
ticket_id         FK tagtoa_ev_tickets nullable nullOnDelete
type              string(16) index   -- participant | vendor | organizer | gateway_clearing | house
owner_label       string nullable    -- nom participant / nom du stand
currency          string(8) default 'HTG'
balance_minor     bigint default 0   -- SOLDE CACHÉ (dérivable du ledger ; vérité = ledger)
status            string(12) default 'active'  -- active | frozen | closed
timestamps
index (event_id, type)
unique (nfc_tag_id)   -- 1 tag = 1 compte participant
```
- `balance_minor` est un **cache** ; la **source de vérité** est la somme des écritures
  du ledger (2.4). Une commande de réconciliation recalcule et vérifie l'égalité.
- Comptes « système » par event créés à l'ouverture : `gateway_clearing` (entrées
  d'argent), `house` (écarts/arrondis), `organizer` (payout). Permet le double-entry.

### 2.3 `tagtoa_ev_wallet_txns` — en-tête de transaction
```
id                bigint PK
tenant_id         string nullable index
event_id          FK tagtoa_ev_events nullable index
type              string(16) index   -- top_up | purchase | refund | payout | adjustment
reference         string(40) unique  -- UUID public
idempotency_key   string(80) nullable unique   -- anti-doublon réseau (top_up/purchase)
amount_minor      bigint             -- montant net de la transaction (>0)
currency          string(8) default 'HTG'
status            string(12) default 'posted'  -- posted | voided
source_account_id FK tagtoa_ev_wallet_accounts nullable
dest_account_id   FK tagtoa_ev_wallet_accounts nullable
payment_ref       string nullable    -- réf provider (MonCash/NatCash/carte) pour top_up
meta              json nullable
created_by        unsignedBigInteger nullable  -- user agent/organisateur
created_at        timestamp
index (event_id, type, created_at)
```

### 2.4 `tagtoa_ev_wallet_entries` — écritures (LEDGER double-entry, immuable)
```
id              bigint PK
txn_id          FK tagtoa_ev_wallet_txns cascadeOnDelete
account_id      FK tagtoa_ev_wallet_accounts
direction       string(6)   -- debit | credit
amount_minor    bigint      -- toujours > 0
balance_after   bigint      -- solde du compte APRÈS cette écriture (piste d'audit)
created_at      timestamp
index (account_id, id)
index (txn_id)
```
- **Invariant fondamental** : pour chaque `txn_id`, `Σ(debits) == Σ(credits)`.
- **Immuable** : aucune update/delete. Une correction = transaction inverse (reversing).
- `balance_after` fige l'historique (audit) et permet de recalculer/vérifier.

### Schéma des flux (double-entry)
| Flux | Débit (compte qui « perd ») | Crédit (compte qui « reçoit ») |
|---|---|---|
| `top_up` (recharge) | `gateway_clearing` | `participant` |
| `purchase` (achat stand) | `participant` | `vendor` |
| `refund` (remboursement solde) | `participant` | `gateway_clearing` |
| `payout_to_organizer` (règlement stands) | `vendor` | `organizer` |
| `adjustment` (correction) | selon cas | selon cas (+ `house`) |

> Convention comptable : un compte participant **crédité** voit son solde monter
> (passif côté plateforme = valeur due au porteur). On documente la convention de signe
> dans le service et on la teste. **C'est ce point qui doit être revu avant prod (gate #4).**

---

## 3. Garanties argent (concurrence, atomicité, idempotence)

- **Atomicité** : chaque transaction wallet = 1 `DB::transaction()` qui écrit l'en-tête
  + les 2 écritures + met à jour `balance_minor` des comptes touchés.
- **Verrou pessimiste** : `WalletAccount::whereKey($id)->lockForUpdate()` sur le compte
  participant AVANT vérification de solde et débit → empêche le double-débit si deux
  scans simultanés (2 terminaux). Vérif `balance >= amount` faite SOUS le lock.
- **Idempotence** : `idempotency_key` unique. Rejouer la même requête renvoie la
  transaction existante au lieu d'en créer une seconde (pattern déjà utilisé :
  `client_uuid` menu/booking/pos).
- **Solde insuffisant** : exception métier `InsufficientFundsException` → 422, aucune écriture.

---

## 4. Classes Action (logique métier hors contrôleur)

```
Actions/Event/Wallet/
  OpenEventWalletAccounts   -- crée comptes système (clearing/house/organizer) d'un event
  IssueNfcTag               -- enregistre un tag (hash UID) et le lie à un ticket/compte
  ResolveNfcTag             -- UID -> WalletAccount (lookup par uid_hash)
  TopUpWallet               -- top_up : clearing -> participant (idempotent)
  ChargeWallet              -- purchase : participant -> vendor (lock + solde)
  RefundWallet              -- refund : participant -> clearing
  PayoutToOrganizer         -- vendor -> organizer
Support/Event/
  Ledger (PUR, testable CI) -- compose les paires débit/crédit, valide Σdébits==Σcrédits,
                               applique convention de signe, calcule balance_after
```
`Ledger` = **logique pure** (comme `Money`, `StockService`) → testable en CI Unit sans Laravel.

---

## 5. Sécurité (§4 du cahier des charges)

- **Auth dashboard/scanner** : on réutilise le groupe Biztap existant
  (`auth + valid.user + role + multi_tenant`). Rôles ciblés :
  `organizer`/`admin` (gestion + payout), `scanner-agent` (scan + charge wallet).
  *(Divergence vs Sanctum : à valider. Si une app mobile native distincte est requise,
  on ajoutera une couche Sanctum tokens. Sinon la PWA web utilise la session existante.)*
- **Rate limiting** : `throttle` strict sur `top_up` et `charge` (ex. 30/min/agent).
- **Audit immuable** : chaque transaction wallet → `AuditService::log()` (déjà construit)
  + `wallet_entries` immuables avec `balance_after`.
- **NFC** : UID jamais en clair (SHA-256 `uid_hash` + `Crypt` optionnel). Tag perdu →
  `status=lost` bloque le compte.
- **Aucune donnée carte** stockée : top_up passe par les providers existants (PAY).

---

## 6. Notifications (§5) — extension du NotificationService existant

- Réutilise `Services/Notifications/NotificationService` (déjà : email tolérant, opt-in).
- Ajout d'un **Job + queue** `SendEventNotification` (découplé) : confirmations
  réservation, top-up réussi, accès accordé/refusé.
- Canaux : email (existant) → **SMS** (Twilio, multi-pays dont Haïti) → **push PWA**.
- Templates multilingues via i18n existant (FR/HT/EN/ES).

---

## 7. Tests (§6) — réalité CI de ce repo

- **CI actuel** = `phpunit --testsuite Unit`, **sans Laravel** (bootstrap manuel,
  classes pures uniquement). Donc :
  - `Support\Event\Ledger` (équilibre, signes, balance_after, solde insuffisant,
    idempotence-décision) → **tests Unit purs en CI** (cible ≥85% sur le cœur ledger).
  - Les **tests Feature** (top_up/charge/refund + **concurrence/race conditions** avec
    `lockForUpdate`, transactions DB réelles) nécessitent l'app Laravel Biztap →
    fournis dans `tests/Feature/` et **exécutés dans Biztap** (documenté), car ils ne
    tournent pas dans la CI module isolée.
- Chaque étape livrée avec ses tests avant de passer à la suivante (demande #3).

---

## 8. Ordre d'implémentation (par étapes, avec revue)

1. **Migrations + modèles** (5 tables 2.1–2.4) — *cette étape produit le schéma à valider.*
2. **Ledger pur + tests Unit** (cœur double-entry) — **revue obligatoire avant la suite.**
3. **Actions wallet** (OpenAccounts, TopUp, Charge, Refund, Payout) + tests Feature.
4. **NFC** : IssueNfcTag / ResolveNfcTag + intégration scanner (tap → wallet).
5. **Dashboard** : recharge, écran vendeur (tap+montant), réconciliation/export.
6. **Notifications** (Job + queue, email d'abord ; SMS/push selon credentials).
7. **Revue finale ledger** → puis seulement, mise en prod.

---

## 9. Bloqueurs nécessitant une action / décision (avant ou pendant)

| Sujet | Décision / action requise |
|---|---|
| **Top-up réel** (MonCash/NatCash/carte) | Drivers de paiement API = **bloqués** (besoin credentials). Le ledger sera construit avec un top-up « manuel/preuve » + interface gateway ; le top-up live se branche quand les drivers existent. |
| **SMS Twilio** | Compte + credentials Twilio requis (bloqué). Email d'abord. |
| **Wallet : scope** | MVP = **par event** (proposé). V2 = portefeuille TAGTOA permanent. À confirmer. |
| **Auth** | Réutiliser l'auth web Biztap (proposé) **ou** ajouter Sanctum pour app mobile ? À confirmer. |
| **Convention de signe du ledger** | À **revoir explicitement** (gate #4) avant prod. |
| **Remboursement fin d'event** | Manuel (proposé) ou automatique vers méthode d'origine ? À confirmer. |

---

## 10. Décisions demandées pour démarrer le code
1. Réutiliser `tagtoa_ev_orders/tickets` comme « Reservation » (pas de table dédiée) ? **[proposé : oui]**
2. Wallet **scope par event** en MVP ? **[proposé : oui]**
3. Auth = **session web Biztap existante** (pas Sanctum pour l'instant) ? **[proposé : oui]**
4. Montants en **entiers (unités mineures) + currency** ? **[proposé : oui]**
5. Je démarre par **étape 1 (migrations) puis étape 2 (Ledger pur + tests)** et je
   m'arrête pour **revue du ledger** avant les Actions argent ? **[proposé : oui]**
