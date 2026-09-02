# TAGTOA PAY — passerelles de paiement

## Où vivent les moyens de paiement — au niveau du MARCHAND

Le marchand configure ses moyens de paiement **une seule fois** sur
`/tagtoa/pay/methods`. Ils s'appliquent à **tous** ses liens : créer un lien ne
demande plus de ressaisir un numéro de compte, et corriger un numéro le corrige
partout d'un coup.

Techniquement, ces méthodes sont portées par une page « bibliothèque »
(`tagtoa_payment_pages.is_library = true`), une par marchand, jamais listée dans
le dashboard ni servie publiquement. `MerchantMethods` est le seul point de
lecture ; la page publique, la page hébergée d'un paiement API et la soumission
de preuve passent toutes par lui, **scopé au `tenant_id` de la page** — une
méthode d'un autre marchand n'est donc jamais payable.

Reprise des données existantes : la migration
`2026_09_02_000113_consolidate_payment_methods_per_merchant` **rattache** les
méthodes existantes à la bibliothèque et **désactive** les doublons au lieu de
les supprimer — les preuves de paiement sont supprimées en cascade depuis les
méthodes, donc effacer une méthode effacerait l'historique du marchand.

### Qui saisit quoi

| Famille | Identifiants | Rôle du marchand |
|---|---|---|
| **Automatique** (MonCash, PayPal, carte, crypto) | clés API du **super-admin** uniquement (`.env`, jamais en base ni côté marchand) | activer / désactiver ce qu'il veut proposer |
| **Manuel** (Zelle, Unibank, Sogebank, BNC, USDT, NatCash…) | **ses propres** coordonnées : nom du compte, numéro, institution, QR, consignes | tout saisir, une fois |

## Types de lien

| Type | Usage | Montant |
|---|---|---|
| `invoice` | facturer un client (nom/titre, description, prix, devise) | fixe, ou laissé vide = libre |
| `donation` | recevoir un don / du soutien | typiquement libre |

Après création, le marchand arrive sur l'écran de partage : copier le lien,
l'envoyer sur WhatsApp, ouvrir la page, générer le QR.

## Architecture des passerelles
Chaque méthode de paiement a un **mode** :
- **auto** (API) : règlement en ligne automatique via une passerelle.
- **manuel** (preuve) : le client paie hors-ligne puis envoie une capture ;
  la page affiche **logo + nom de la passerelle, institution, nom du compte,
  numéro du compte et QR code**.

Classification dans `app/Support/PaymentGateway.php`.
État d'activation des passerelles auto dans `app/Support/GatewayManager.php`
(une passerelle n'est active que si TOUS ses identifiants sont définis).

| Type méthode | Mode | Driver |
|---|---|---|
| moncash | auto | moncash |
| paypal | auto | paypal |
| card | auto | stripe |
| usdt / usdc / btc / eth | auto | coinpayments |
| natcash, zelle, cashapp, unibank, sogebank, capitalbank, bnc, … | manuel | — |

> **NatCash** : aucun driver automatique n'existe à ce jour dans le module — la
> documentation API officielle de l'opérateur n'est pas en notre possession.
> NatCash fonctionne donc en **manuel** (le marchand saisit son numéro, le
> client envoie une preuve). Le jour où la documentation officielle est
> obtenue, un driver s'ajoute comme les autres et la méthode bascule seule en
> automatique, sans que le marchand ait quoi que ce soit à refaire.

## Identifiants (secrets) — à définir en .env / GitHub secrets
> NE JAMAIS committer ces valeurs. Tant qu'elles sont absentes, la méthode
> reste en mode manuel (aucun échec).

### MonCash
- `TAGTOA_MONCASH_CLIENT_ID`
- `TAGTOA_MONCASH_SECRET`
- `TAGTOA_MONCASH_MODE` (sandbox|live)

### PayPal (PayPal + cartes via PayPal)
- `TAGTOA_PAYPAL_CLIENT_ID`
- `TAGTOA_PAYPAL_SECRET`
- `TAGTOA_PAYPAL_MODE` (sandbox|live)

### Stripe (cartes)
- `TAGTOA_STRIPE_KEY`
- `TAGTOA_STRIPE_SECRET`
- `TAGTOA_STRIPE_WEBHOOK_SECRET`

### CoinPayments (USDT, USDC, BTC, ETH)
- `TAGTOA_COINPAYMENTS_MERCHANT_ID`
- `TAGTOA_COINPAYMENTS_PUBLIC_KEY`
- `TAGTOA_COINPAYMENTS_PRIVATE_KEY`
- `TAGTOA_COINPAYMENTS_IPN_SECRET`

### Authorize.Net (cartes)
- `TAGTOA_AUTHNET_LOGIN_ID`
- `TAGTOA_AUTHNET_TRANSACTION_KEY`
- `TAGTOA_AUTHNET_MODE` (sandbox|live)

## Statut d'implémentation
- ✅ Registre + classification (auto/manuel), couleur de marque, logo par méthode
- ✅ Affichage public : logo, institution, nom du compte, numéro, QR
- ✅ Config + détection d'activation (GatewayManager)
- ⏳ Drivers API réels (1 PR par passerelle, testé avec identifiants) :
  route `tagtoa.pay.checkout` + IPN/webhook + vérification de signature.

---

## API développeur (v1)

Permet à n'importe quel site tiers d'encaisser avec les méthodes de paiement
TAGTOA d'un marchand. Le marchand crée sa clé sur `/tagtoa/developer`.

- Base : `https://tagtoa.com/api/v1/tagtoa`
- Auth : `Authorization: Bearer tag_live_xxxxxxxx_…` (jamais de session/CSRF)
- Limite : 120 appels/minute/clé

| Méthode | Endpoint | Rôle |
|---|---|---|
| GET  | `/ping` | vérifier la clé |
| GET  | `/payment-methods` | méthodes que verra le client |
| POST | `/payments` | créer un paiement → `checkout_url` |
| GET  | `/payments/{reference}` | état d'un paiement |

Parcours : le site tiers crée le paiement → redirige le client vers
`checkout_url` (`/pay/i/{reference}`, page hébergée par TAGTOA, montant imposé
côté serveur) → le client paie → le marchand approuve la preuve dans son
dashboard → le paiement passe à `paid` et TAGTOA notifie `callback_url`
(POST signé HMAC-SHA256 dans l'en-tête `X-Tagtoa-Signature`).

Sécurité : `POST /payments` est idempotent sur `external_id` (un réessai ne
facture pas deux fois) ; les clés ne sont stockées qu'en SHA-256 et le jeton
complet n'est affiché qu'à la création ; l'API ne renvoie jamais les
coordonnées bancaires du marchand ni d'identifiant interne.

### Réglages plateforme par passerelle

`/tagtoa/admin/gateways` (super_admin) : activation, frais TAGTOA (% + fixe) et
saisie des identifiants API. **C'est le seul endroit où des clés de passerelle
sont saisies** : un marchand n'en fournit jamais.

Conséquence à ne pas perdre de vue : quand TAGTOA encaisse avec ses propres
clés, TAGTOA est un **agrégateur de paiement** et reverse ensuite au marchand.
N'activer une passerelle automatique en production qu'avec l'accord contractuel
correspondant (PayPal Commerce Platform / Stripe Connect / Digicel) et en
conformité BRH Circulaire 121 — voir `RISKS.md`.
