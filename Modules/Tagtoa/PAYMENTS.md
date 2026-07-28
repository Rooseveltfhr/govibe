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
