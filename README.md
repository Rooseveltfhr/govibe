# TCHEKELA — by GOVIBE

Plateforme collaborative haïtienne gratuite pour **déclarer, retrouver, vérifier et gérer** ses documents.

Site statique (HTML/CSS/JS, sans backend requis). Les données de démo sont persistées dans le `localStorage` du navigateur. Des points d'intégration sont prévus pour brancher de vraies API (paiements, IA) côté serveur.

## Pages

| Fichier | Rôle |
|---|---|
| `index.html` | Accueil : déclarer / rechercher un document, OCR, stories, impact, dons |
| `don.html` | **Donation** : MonCash, NatCash, PayPal, Zelle, Crypto (USDT/BTC/ETH), Unibank, Sogebank + preuve de paiement + reçu imprimable |
| `verifier-carte.html` | **Vérification ONI** : statut du dossier + aperçu de la carte téléversée par les bénévoles |
| `partenaires.html` | **Partenaires** : inscription (point de collecte, financier, média, technique) + mur de logos approuvés |
| `benevolat.html` | **Bénévolat** : inscription + génération d'un **badge** personnalisé (canvas) téléchargeable/partageable |
| `documents.html` | **Documents numériques** : portefeuille chiffré local, QR de partage, sauvegarde |
| `admin.html` | **Dashboard admin** : valider les dons, approuver les partenaires, voir les bénévoles, téléverser les cartes ONI |

## Composants partagés (`assets/`)

- `tchekela.css` — système de design partagé (couleurs, typographies, composants)
- `tchekela.js` — topbar/footer, toasts, stockage, et **assistant IA « Tchecko »** (flottant sur toutes les pages)
- `payments-config.js` — comptes de paiement + points d'intégration API (Binance Pay / CoinPayments / PayPal)

## Configuration

- **Paiements** : éditez `assets/payments-config.js` (numéros MonCash/NatCash, adresses crypto, comptes bancaires, lien PayPal). Pour automatiser, branchez les API côté serveur (ne jamais exposer une clé secrète dans le navigateur).
- **Assistant IA** : par défaut, réponses locales basées sur une base de connaissances. Pour un vrai modèle, définissez `window.TCHEKELA_AI_BACKEND(message)` retournant une `Promise<string>`.
- **Admin** : mot de passe de démo `tchekela2026` (à remplacer par une authentification serveur en production).

## Lancer en local

```bash
python3 -m http.server 8000
# puis ouvrir http://localhost:8000
```

_powered by GOVIBE · govibeht.com_
