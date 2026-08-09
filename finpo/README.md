# FINPO 2026 — Plateforme événementielle

**Forum & Expo National des Institutions Publiques, Privées et Organisations**
Organisé par **GOVIBE Innovation Hub** · finpo.ht

> Connecter les institutions. Construire des partenariats. Accélérer le développement d'Haïti.

Plateforme complète de gestion d'événement (inspirée de Web Summit / GITEX / VivaTech) :
site public premium multilingue + billetterie + back-office complet.

---

## Stack

- **Laravel 12** · PHP 8.4 · MySQL (SQLite en dev)
- Bootstrap 5.3 (auto-hébergé), JavaScript vanilla, Livewire installé (prêt pour les
  modules temps réel), PWA (manifest + service worker)
- QR codes générés localement (`bacon/bacon-qr-code`, SVG inline)
- Mobile-first, dark mode par défaut + mode clair, WCAG (skip-link, focus visible,
  grandes polices, aria-labels)

## Fonctionnalités

### Site public
- Accueil : hero animé (mots tournants Forum/Expo/Networking/Tech/Innovation),
  compte à rebours, compteurs animés, CTA (inscription, sponsor, partenaire, exposant,
  brochure, édition précédente)
- À propos (mission, vision, objectifs, FAQ, comité, histoire), Forum, Networking, Awards
- Programme interactif : filtres jour/salle/thématique/format, export ICS + Google
  Calendar par session, version imprimable (PDF)
- Intervenants : cartes + filtres par catégorie + fiches détaillées (bio, réseaux, sessions)
- Expo : annuaire exposants (recherche + filtre secteur), fiches riches (logo, bannière,
  produits, services, vidéo, brochure, réseaux, QR de partage, stand)
- Exposants : plan des stands par zone (disponible/réservé/vendu), tarifs, réservation en ligne
- Partenaires (par catégorie) & Sponsors (7 niveaux, tableau comparatif des avantages) +
  formulaires de candidature avec workflow d'approbation
- Actualités (blog), Galerie (par édition), Espace médias, Contact (formulaire + carte
  Google Maps + WhatsApp), newsletter
- **Billetterie** : catégories multiples (Étudiant 1 000 HTG, Professionnel 5 000 HTG,
  Institution, VIP, ONG, Startup, Presse/Volontaire gratuits…), codes promo (vérification
  AJAX), prix imposé côté serveur, quotas, périodes de vente
- **Billet électronique** : numéro unique `FINPO26-XXXXXX`, QR code, version imprimable,
  ajout au calendrier, e-mail de confirmation automatique
- **Badge** professionnel : couleur par catégorie, QR, initiales, contact d'urgence, impression
- **Certificats** de participation : génération admin, numéro unique, vérification publique
  par QR/numéro (`/verification`)
- SEO : meta, Open Graph, Schema.org (Event), canonical · i18n : FR (défaut) / EN / HT

### Back-office (`/admin`)
- Tableau de bord : inscriptions, revenus (encaissé/en attente), check-ins, graphique
  14 jours, remplissage par catégorie, alertes candidatures en attente
- **Catégories de billets : CRUD complet — l'admin ajoute des catégories, change les
  prix, quotas, couleurs, avantages et périodes de vente**
- Inscriptions : recherche/filtres, fiche détail, marquer payé, annuler/restaurer,
  check-in manuel, génération de certificat, **export CSV/Excel**
- **Check-in** : scanner QR caméra (html5-qrcode), saisie manuelle, recherche
  participant, alerte « déjà enregistré », statistiques temps réel, journal des passages
- Codes promo, Intervenants, Programme (sessions + salles + intervenants multiples),
  Partenaires / Sponsors / Exposants (workflow pending → approved/rejected),
  Stands (zones, prix, statuts), Actualités, Galerie, Messages de contact
- Rôles : `admin` / `staff` (middleware), protection CSRF, honeypots anti-spam,
  rate limiting sur tous les formulaires publics

## Démarrage rapide

```bash
cd finpo
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed     # données démo complètes + admin
php artisan serve
```

- Site : http://localhost:8000
- Admin : http://localhost:8000/admin — `admin@finpo.ht` / `finpo2026`
  (changez `FINPO_ADMIN_PASSWORD` en production !)

En production : configurer MySQL + SMTP dans `.env` (voir `.env.example`),
`APP_DEBUG=false`, puis `php artisan config:cache && php artisan route:cache`.

## Tests

```bash
php artisan test   # 9 tests : flux d'inscription, coupons, check-in, contrôle d'accès
```

## Configuration événement

Tout le paramétrage éditorial vit dans `config/finpo.php` : dates, lieu, contacts,
réseaux sociaux, niveaux de sponsoring, catégories de participants (couleurs de badge),
types de sessions, moyens de paiement.

## Paiements

Les inscriptions enregistrent le mode choisi (MonCash, NatCash, Visa/MasterCard,
PayPal, virement, espèces, gratuit) avec statut `pending` → l'admin confirme
l'encaissement (« Marquer payé »). L'intégration API temps réel des passerelles
(MonCash API, Stripe, PayPal) se branche dans `RegistrationController@store`
et `Payment` — la structure (table `payments`, référence transaction) est prête.
