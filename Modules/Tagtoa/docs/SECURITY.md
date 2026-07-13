# TAGTOA — Sécurité (audit & risques connus)

> Registre vivant des risques de sécurité. Mettre à jour à chaque décision.

## Modèle de menace — carte NFC closed-loop

La carte physique (TAGTOA Pay / wallet événement) est identifiée par l'**UID**
de la puce NFC (stocké haché SHA-256 dans `tagtoa_ev_nfc_tags.uid_hash`).

### 🔴 RISQUE ACCEPTÉ (v1) — clonage d'UID

Les UID de la plupart des puces NFC (NTAG213/215/216, MIFARE Classic) sont
**lisibles par n'importe quel téléphone et copiables** sur une puce vierge.
Un attaquant qui approche la carte d'une victime peut cloner l'UID et
dépenser son solde.

**Pourquoi c'est acceptable en v1 (risque borné) :**
- Le débit (`charge`) n'a lieu que sur un **terminal vendeur authentifié**
  (l'organisateur doit être connecté) — pas d'endpoint public de débit.
- La perte maximale = le **solde chargé sur la carte** (closed-loop, pas de
  lien bancaire). Recommandation produit : plafonner la recharge par carte.
- Toutes les transactions sont **tracées** (ledger + audit) → détection.

**Feuille de route pour lever le risque (v2, production à grande échelle) :**
1. **NTAG424 DNA** : UID dynamique + signature CMAC (SUN) à chaque tap —
   cryptographiquement **non clonable**. Vérifier le CMAC côté serveur dans
   `ResolveNfcTag` avant tout débit.
2. **PIN de dépense** au-dessus d'un seuil configurable par événement.
3. Plafond de solde par carte + alertes sur vélocité anormale.

## Protections en place (vérifiées)

- **Ledger double-entrée** atomique : `lockForUpdate`, vérification de fonds
  SOUS le verrou, `amount > 0`, devise identique source/dest, `source ≠ dest`.
- **Idempotence** avec contrainte DB `UNIQUE` sur `idempotency_key` (+ gestion
  de la course `QueryException`). Écritures **immuables**.
- **Isolation multi-tenant** : chaque action dashboard passe par un helper
  `own*()` filtrant sur `tenant_id` (pas d'IDOR cross-tenant constaté).
- **Rate-limiting** sur toutes les écritures publiques (`throttle:20,1`) et le
  terminal staff (`throttle:120,1`) + rate-limit dédié sur le login PIN.
- **Plafonds** `max` sur les montants wallet (recharge/achat).
- **Uploads** de preuves : `image` + `mimes` + `max:5120`, nom de fichier
  aléatoire.
- **Aucune XSS** : les `{!! !!}` sont des SVG serveur ou `nl2br(e())`.

## À faire (backlog sécurité)

- [ ] Déplacer les preuves de paiement du disque `public` vers un disque privé
      + route de service authentifiée (aujourd'hui : URL non devinable mais
      publique si fuitée).
- [ ] Ops VPS : `APP_DEBUG=false`, `APP_ENV=production`, **rotation de
      `DB_PASSWORD`** (exposé lors de la restructuration), vérifier que
      `tagtoa.com/.env` renvoie 404.
- [ ] Migration NTAG424 DNA (voir feuille de route ci-dessus).
