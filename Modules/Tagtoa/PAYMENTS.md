# TAGTOA PAY — passerelles de paiement

## Architecture
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
**mode de credentials** par passerelle —
`merchant` (le marchand branche ses propres identifiants, l'argent va direct
chez lui) ou `platform` (TAGTOA encaisse en agrégateur : n'activer qu'avec les
accords PayPal Commerce Platform / Stripe Connect / Digicel).
