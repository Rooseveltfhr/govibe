# KLASYO.ORG — Blueprint Produit, UX & Architecture Technique

> **Version 1.0 — 14 juillet 2026**
> Basé sur l'audit RÉEL de l'installation (inspections SSH v1→v4 via le canal `klasyo-ops`) :
> LMSZAI build 31 (Laravel 9, 139 tables) sur `/platform`, eSchool SaaS v1.9.3 (Laravel 10, 45 tables) sur `/school`.
> **Contraintes non négociables : conserver SaaS, Multi-organisation, Multi-instructeurs, Affiliate System.**

---

## 0. Résumé exécutif

KLASYO.ORG devient l'écosystème éducatif d'Haïti et des Caraïbes, en deux produits sur un même compte :

| Produit | URL | Rôle | Script de base |
|---|---|---|---|
| **KLASYO Aprann** (platform) | `klasyo.org/platform` | Marketplace de formations et cours en ligne (vente, abonnements, affiliation) | LMSZAI b31 |
| **KLASYO Lekòl** (school) | `klasyo.org/school` | Gestion d'établissements scolaires (SaaS multi-écoles) | eSchool SaaS 1.9.3 |
| **KLASYO Connect** | transversal | 1 seul compte, SSO entre les deux, onboarding guidé | à construire (custom) |

Stratégie en 3 phases : **MVP (6-8 semaines)** — stabiliser, rebrander, simplifier, encaisser (MonCash + Stripe) ; **PRO (3-4 mois)** — dashboards premium, présentiel/hybride, certificats QR, IA de base, SSO complet ; **ENTERPRISE (6-9 mois)** — IA complète, marketplace, paiements échelonnés, analytics avancé, PWA offline.

---

## 1. Vision & positionnement

- **Marché primaire** : Haïti (mobile-first, connexions instables, HTG + MonCash/NatCash, français/créole).
- **Marché secondaire** : diaspora + Caraïbes (USD, Stripe/PayPal/Zelle, anglais/espagnol).
- **Différenciateurs** : paiements locaux réels, offline-first, créole natif, formations présentiel+hybride avec QR de présence, certificats vérifiables publiquement, prix en HTG.
- **Références UX** : Coursera (catalogue/parcours), Udemy (fiche cours/player), Skillshare (communauté), Kajabi/Thinkific/Teachable (dashboards créateurs) — avec l'identité KLASYO (voir §11).

---

## 2. État des lieux technique (audit du 14/07/2026)

### 2.1 Ce qui existe

| Élément | Constat |
|---|---|
| Landing racine | `index.html` statique, déjà brandée KLASYO (bleu #1d4ed8, vert #16a34a, Sora + DM Sans) — sert de source du design system |
| PLATFORM | LMSZAI b31 fonctionne (`/platform/index.php` → 200). 7 thèmes frontend (`frontend`…`frontend-theme-7`), settings en DB (`settings.option_key/option_value`) |
| SCHOOL | **EN PANNE** : timeout sur toutes les URLs. Logs : `No application encryption key has been specified` + `Your configuration files are not serializable` → APP_KEY manquante + cache config corrompu |
| Comptes | 1 seul user dans chaque app (installations fraîches) — le moment idéal pour restructurer |
| Inscription | `Auth::routes(['register' => false])` — **l'inscription publique est désactivée** sur platform |
| Contenu | 100 % démo LMSZAI (app_name=LMSZAI, logos démo, textes anglais lorem) |

### 2.2 Problèmes critiques (P0 — avant tout le reste)

1. **Zips de code source dans public_html** : `platform/klasyo.zip` (147 Mo), `school/school.zip` (568 Mo), `source_code.zip`, zips d'install/update → à déplacer hors web (`~/backups_klasyo/`). ~770 Mo de code + configs potentiellement téléchargeables.
2. **school/.env : `APP_DEBUG=true`** en production → fuite de secrets à la moindre erreur.
3. **platform/.env : `APP_URL="webetud.com"`, `APP_ENV=local`** → URLs générées cassées, comportements de dev en prod.
4. **Réparer school** : générer APP_KEY (`php artisan key:generate`), `config:clear`, corriger la connexion DB au runtime, `APP_DEBUG=false`.
5. **403 externe** : le firewall (Imunify360) bloque les IPs datacenter ; vérifier que les vrais navigateurs passent, et whitelister les IPs de monitoring.
6. Fichier parasite `platform/index.html` (titre « webetud.com ») → supprimer.

*Priorité P0 · Difficulté faible · 1-2 jours (déjà outillé via le workflow klasyo-ops).*

---

## 3. Architecture cible de l'écosystème

```
                    klasyo.org (landing brandée)
                              │
              ┌───────────────┼────────────────┐
              ▼               ▼                ▼
     /platform (Aprann)   /school (Lekòl)   /verify/{code}
     LMSZAI Laravel 9     eSchool Laravel 10  (certificats publics)
              │               │
              └──── KLASYO Connect (SSO) ────┘
                    compte maître : platform
                    jeton HMAC signé, 60 s
                    auto-provisioning par email
```

### 3.1 KLASYO Connect — 1 compte pour tout (réponse à la demande SSO)

- **Compte maître** : `users` de platform (email unique). L'inscription se fait UNE fois, côté platform, via l'onboarding guidé (§6).
- **Pont SSO** (addon, sans toucher au cœur des scripts) :
  - platform : `GET /klasyo-sso/authorize?redirect=…` (auth requise) → génère `token = base64(payload).HMAC_SHA256(payload, KLASYO_SSO_SECRET)` avec `{email, name, exp: now+60s, nonce}`.
  - school : `GET /klasyo-sso/callback?token=…` → vérifie signature + expiration + nonce anti-rejeu → `User::firstOrCreate(email)` (mot de passe aléatoire, `school_id` par défaut « KLASYO », rôle élève) → `Auth::login()`.
  - Bouton « Se connecter avec KLASYO » sur l'écran de login de school ; lien inverse possible.
- **Secret partagé** `KLASYO_SSO_SECRET` dans les deux `.env` (généré sur le VPS, jamais dans le repo/logs).
- **Livré comme addon** : 1 contrôleur + 1 fichier de routes + 1 ServiceProvider par app → survit aux mises à jour des scripts achetés.
- Évolution Enterprise : promouvoir Connect en vrai serveur OAuth2 (Laravel Passport) quand un 3e produit arrivera.

*Priorité P1 · Difficulté moyenne · 4-6 jours (mapping école/rôle par défaut à confirmer).*

---

## 4. Analyse module par module (LMSZAI /platform)

Légende : **P0** critique · **P1** MVP · **P2** Pro · **P3** Enterprise. Difficulté : ⚙ faible / ⚙⚙ moyenne / ⚙⚙⚙ élevée. Temps = dev·jours estimés.

### Vue d'ensemble

| # | Module | Verdict | Priorité | Diff. | Temps |
|---|---|---|---|---|---|
| 1 | Auth & Onboarding | **Refondre** (inscription désactivée !) | P0 | ⚙⚙ | 6 |
| 2 | Catalogue & recherche | Conserver + simplifier | P1 | ⚙⚙ | 8 |
| 3 | Fiche cours & player | Conserver + moderniser | P1 | ⚙⚙⚙ | 12 |
| 4 | Quiz / Devoirs / Examens | Conserver + IA | P2 | ⚙⚙ | 8 |
| 5 | Certificats | Refondre (QR + vérif publique) | P1 | ⚙⚙ | 6 |
| 6 | Live class (Agora/Zoom/GMeet) | Conserver tel quel | P2 | ⚙ | 2 |
| 7 | SCORM | Conserver (niche B2B) | P3 | ⚙ | 1 |
| 8 | Forum / Communauté | Simplifier (Q&R par cours) | P2 | ⚙⚙ | 5 |
| 9 | Blog / Pages / FAQ | Conserver (SEO) | P2 | ⚙ | 3 |
| 10 | Consultation 1:1 | Conserver + calendrier | P2 | ⚙⚙ | 5 |
| 11 | Bundles | Conserver | P2 | ⚙ | 2 |
| 12 | **Subscription SaaS** | **CONSERVER (obligatoire)** + clarifier | P1 | ⚙⚙ | 5 |
| 13 | **Organization (multi-org)** | **CONSERVER (obligatoire)** + simplifier UX | P1 | ⚙⚙ | 6 |
| 14 | **Multi-instructeurs** | **CONSERVER (obligatoire)** + dashboard simple | P1 | ⚙⚙ | 8 |
| 15 | **Affiliate System** | **CONSERVER (obligatoire)** + moderniser | P1 | ⚙⚙ | 5 |
| 16 | Wallet & recharge | Conserver (clé pour Haïti) | P1 | ⚙⚙ | 4 |
| 17 | Paiements | **Refondre** (architecture §7) | P0-P1 | ⚙⚙⚙ | 20 |
| 18 | Demandes de retrait | Conserver + traçabilité | P1 | ⚙ | 3 |
| 19 | Messages / Chat | Simplifier | P2 | ⚙⚙ | 4 |
| 20 | Notifications | Refondre (Email+SMS+WhatsApp §9) | P1 | ⚙⚙ | 6 |
| 21 | Admin settings / i18n / devises | Conserver + créole | P1 | ⚙ | 4 |
| 22 | Avis & notes | Conserver + modération | P2 | ⚙ | 2 |

### 4.1 Auth & Onboarding
- **Utilité** : porte d'entrée de tout l'écosystème.
- **État** : login Laravel classique, **register désactivé**, pas de social login actif, pas de guidage.
- **Faiblesses** : impossible de s'inscrire ; formulaires bruts Bootstrap ; aucun choix de profil ; pas de vérification téléphone (essentiel en Haïti où l'email est faible).
- **Améliorations** : réactiver l'inscription via le **wizard KLASYO Connect** (§6) ; login par email OU téléphone ; OTP WhatsApp/SMS en option ; « Se souvenir de moi » par défaut ; réinitialisation par WhatsApp.
- **Maquette** : écran unique centré, logo KLASYO, 2 champs max par étape, gros boutons (44 px), gradient héro `--grad-main` en fond.

### 4.2 Catalogue & recherche
- **Utilité** : découverte des cours — le « magasin ».
- **Forces** : filtres LMSZAI riches (catégorie, niveau, prix, langue), 7 thèmes disponibles.
- **Faiblesses** : trop d'éléments par écran (sliders multiples, achievements démo, 3 carrousels) ; cartes cours chargées ; recherche non tolérante aux fautes ; images démo lourdes.
- **Améliorations** : page d'accueil = héro + recherche + 8 cours vedettes + catégories + preuve sociale, rien d'autre ; recherche instantanée (Laravel Scout + Meilisearch en P2, LIKE optimisé en P1) ; cartes épurées : image 16:9, titre 2 lignes, instructeur, note ★, prix HTG/USD ; badges « Nouveau / Populaire / Gratuit ».
- **Maquette** : grille 1 col (mobile) → 2 (tablette) → 4 (desktop), skeleton loaders, scroll infini.

### 4.3 Fiche cours & player
- **Utilité** : conversion (fiche) + rétention (player).
- **Forces** : player LMSZAI complet (vidéo, sections, pièces jointes, progression, notes).
- **Faiblesses** : fiche cours très longue et désordonnée ; player chargé (sidebar dense) ; pas de reprise « Continuer là où j'étais » mise en avant ; vidéos servies localement (bande passante VPS).
- **Améliorations** : fiche = sticky card d'achat (prix, CTA, garanties, paiement local) + curriculum accordéon + avis ; player plein écran mobile avec vitesse ×0.75-×2, qualité adaptative, marquage auto « terminé » ; téléchargement audio des leçons (offline Haïti, P2) ; hébergement vidéo Bunny Stream/Cloudflare Stream (§12).
- **Maquette player** : barre de progression du cours en haut, vidéo, onglets « Aperçu / Notes / Q&R / Fichiers », bouton flottant « Leçon suivante ».

### 4.4 Quiz / Devoirs / Examens
- Conserver le moteur LMSZAI ; ajouter : banque de questions réutilisable, minuterie visible, correction immédiate optionnelle, tentatives configurables, anti-triche léger (mélange questions/réponses), **génération par IA** (§10).

### 4.5 Certificats → voir §9.1 (QR + vérification publique + badges).

### 4.6–4.11 (Live, SCORM, Forum, Blog, Consultation, Bundles)
- **Live** : conserver Agora intégré ; documenter Zoom/GMeet ; P2.
- **Forum** : remplacer le forum global par des **Q&R par leçon** (comme Udemy) — moins de modération, plus de valeur ; conserver la table, changer l'UX.
- **Consultation 1:1** : pépite pour coachs haïtiens — brancher un vrai calendrier de disponibilités + rappels WhatsApp.
- **Bundles** : conserver, utile pour « parcours métier ».

### 4.12 Subscription SaaS (OBLIGATOIRE — ne jamais supprimer)
- **Utilité** : revenus récurrents ; les organisations/écoles paient un forfait pour vendre sur KLASYO.
- **Améliorations** : 3 plans clairs (Gratis / Pro / Biznis) avec tableau comparatif simple ; facturation HTG et USD ; relance automatique J-7/J-3/J0 par WhatsApp+email ; période de grâce 7 jours ; page « Mon forfait » avec jauge d'utilisation (modèle déjà éprouvé sur TAGTOA `PlanService`/`EnforcesPlan`).
- *P1 · ⚙⚙ · 5 j.*

### 4.13 Multi-organisation (OBLIGATOIRE)
- **Utilité** : une école/entreprise vend sous sa marque, gère ses instructeurs, partage les revenus.
- **Faiblesses UX** : le rôle « organization » de LMSZAI est un dashboard admin allégé, confus.
- **Améliorations** : onboarding org dédié (logo, page vitrine `platform/org/{slug}`, invitation d'instructeurs par lien) ; répartition des revenus visible (org ↔ instructeur ↔ plateforme) ; **pont naturel vers school** : « Votre organisation est une école ? Activez KLASYO Lekòl » (cross-sell SSO).
- *P1 · ⚙⚙ · 6 j.*

### 4.14 Multi-instructeurs (OBLIGATOIRE) → dashboard refondu en §5.2.

### 4.15 Affiliate System (OBLIGATOIRE)
- **Utilité** : croissance virale à coût maîtrisé — crucial en Haïti où la recommandation domine.
- **État** : LMSZAI gère `is_affiliator`, commissions, retraits.
- **Améliorations** : lien + QR d'affiliation par cours ; page publique « Devenez ambassadeur KLASYO » ; tableau de bord affilié simple (clics, ventes, gains, bouton retrait MonCash) ; commissions à 2 niveaux optionnelles ; paiement des commissions via le même moteur de retraits que les instructeurs ; partage direct WhatsApp (« Pataje sou WhatsApp »).
- *P1 · ⚙⚙ · 5 j.*

### 4.16 Wallet
- Conserver — c'est l'astuce anti-friction : l'utilisateur recharge une fois (dépôt MonCash validé) puis achète en 1 clic. Ajouter : historique clair, recharge par code revendeur (P3, réseau de points de vente physiques).

---

## 5. Refonte des dashboards

Principes communs : sidebar fixe (desktop) / bottom-nav 5 icônes (mobile) ; cartes KPI uniformes ; design system §11 ; **chaque dashboard doit être utilisable sur un téléphone à 25 USD**.

### 5.1 Dashboard ADMIN (premium, niveau international)

```
┌────────────────────────────────────────────────────────────┐
│ ⌂ KLASYO Admin      [Recherche globale…]        🔔  👤     │
├──────────┬─────────────────────────────────────────────────┤
│ Accueil  │  Revenus (mois)   Inscriptions   Nouv. étudiants│
│ Ventes   │  ┌─────────┐      ┌─────────┐    ┌─────────┐    │
│ Cours    │  │ 245 000 │      │  1 240  │    │   318   │    │
│ Étudiants│  │ HTG ▲12%│      │  ▲ 8%   │    │  ▲ 15%  │    │
│ Instruct.│  └─────────┘      └─────────┘    └─────────┘    │
│ Organis. │  Nouv. instructeurs   Cours actifs   Paiements  │
│ Paiements│  ───────────────────────────────────────────────│
│ Retraits │  📈 Revenus 30 jours (graphique aires, HTG/USD) │
│ Affiliés │  📊 Top 5 cours │ 🍩 Répartition par passerelle │
│ Marketing│  ───────────────────────────────────────────────│
│ Rapports │  ⏳ À traiter : 4 retraits · 2 preuves MonCash  │
│ Support  │     3 cours en attente d'approbation            │
│ Paramètres│                                                │
└──────────┴─────────────────────────────────────────────────┘
```

- **KPI (8 tuiles)** : Revenus (mois, avec devise commutables HTG/USD), Inscriptions, Nouveaux étudiants, Nouveaux instructeurs, Paiements reçus, Cours actifs, Taux de complétion, MRR abonnements.
- **Graphiques** : revenus 30 j (aire), inscriptions 30 j (barres), répartition passerelles (donut), top cours (barres horizontales). Chart.js suffit (léger).
- **File « À traiter »** : retraits en attente, preuves de paiement manuelles à valider, cours à approuver, tickets support — le vrai travail quotidien de l'admin haïtien, en premier.
- **Sections** : Ventes/Paiements (journal + filtres + export CSV), Retraits (workflow approuver→payer→prouver), Marketing (coupons, bannières, emailing), Rapports (revenus par période/instructeur/org, export), Support (tickets simples), Paramètres regroupés en 6 onglets max (au lieu des ~30 pages LMSZAI).
- *P1 · ⚙⚙⚙ · 15 j.*

### 5.2 Dashboard INSTRUCTEUR (simplicité radicale)

Menu EXACT (11 entrées, demande client) :
**Dashboard · Mes cours · Créer un cours · Leçons · Quiz · Étudiants · Revenus · Demandes de retrait · Messages · Avis · Paramètres**

- **Accueil** : 4 tuiles — Étudiants totaux, Revenus du mois, Note moyenne ★, Cours publiés — puis graphique « inscriptions 14 jours », « progression moyenne de mes étudiants », top 3 cours populaires, derniers avis à répondre.
- **Créer un cours** : wizard 4 étapes (Infos → Curriculum → Prix → Publier), sauvegarde auto brouillon, upload vidéo par leçon avec barre de progression, **assistant IA** (« Génère-moi un plan de cours », §10).
- **Revenus** : solde disponible en gros, historique ventes, bouton « Demander un retrait » (MonCash/virement/Zelle), statut du retrait suivi comme un colis (Demandé → Approuvé → Payé).
- *P1 · ⚙⚙⚙ · 12 j.*

### 5.3 Dashboard ÉTUDIANT (le plus simple possible)

Menu : **Continuer le cours · Mes formations · Ma progression · Mes certificats · Mes paiements · Mes téléchargements · Mes favoris · Support · Profil**

- **Écran d'accueil** = 1 grande carte « **Continuer : [dernière leçon]** » avec bouton play + barre de progression, puis « Mes formations » en cartes avec % complété, puis prochains événements (sessions live / présentiel).
- **Ma progression** : anneaux de progression par cours, série de jours d'étude (streak), badges gagnés.
- **Mes certificats** : cartes avec aperçu, QR, boutons Télécharger PDF / Partager LinkedIn / WhatsApp.
- **Mes paiements** : reçus simples, statut des preuves manuelles (« En vérification »).
- Mobile : bottom-nav `⌂ Aprann · 📚 Kou · 🏆 Sètifika · 💬 Sipò · 👤 Pwofil`.
- *P1 · ⚙⚙ · 8 j.*

---

## 6. Login unifié & onboarding « étape par étape vers son besoin »

Wizard unique sur `klasyo.org` (3 écrans max, 1 question par écran) :

1. **« Kisa ou vle fè ? / Que voulez-vous faire ? »**
   - 🎓 *Aprann* (suivre des formations) → compte étudiant platform
   - 👨‍🏫 *Anseye* (vendre mes cours) → compte instructeur platform (validation admin)
   - 🏫 *Jere yon lekòl* (gérer mon établissement) → school (+ compte platform lié via SSO)
   - 🏢 *Òganizasyon* (académie/entreprise) → organisation platform
2. **Identité** : nom + (email OU téléphone) + mot de passe. OTP WhatsApp si téléphone.
3. **Personnalisation** : langue (Kreyòl/Français/English/Español → fixe aussi la devise HTG/EUR/USD/DOP), centres d'intérêt (étudiant) ou nom d'école (school).

→ Redirection directe vers le bon espace, déjà connecté partout (SSO). Un seul couple identifiant/mot de passe pour platform ET school.

*P1 · ⚙⚙ · 6 j (dépend du pont SSO §3.1).*

---

## 7. Architecture Paiements (le nerf de la guerre)

### 7.1 Design : un « Payment Hub » à drivers

Pattern éprouvé (déjà validé sur TAGTOA) : une interface unique + un driver par passerelle, un webhook par driver, un **ledger** central.

```php
interface PaymentDriver {
    public function initiate(Payment $p): RedirectResponse|array; // URL ou instructions
    public function verifyWebhook(Request $r): PaymentResult;     // IPN/webhook
    public function verifyManualProof(Payment $p): void;          // preuves manuelles
    public function currencies(): array;                          // ['HTG','USD']
    public function mode(): string;                               // 'auto' | 'manual'
}
```

- Table `klasyo_payments` (ledger) : `id, user_id, payable_type/payable_id (cours|abonnement|wallet|acompte), gateway, currency, amount_minor, fee_minor, status (pending|processing|paid|failed|refunded), proof_path, external_ref, meta JSON, idempotency_key`.
- **Montants en unités mineures (centimes)**, jamais de float.
- Chaque driver = 1 classe + 1 route webhook + 1 vue « instructions » → ajout d'une passerelle sans toucher au reste.
- Sélecteur intelligent : la devise de l'utilisateur filtre les passerelles proposées (HTG → MonCash/NatCash/banques ; USD → Stripe/PayPal/Zelle/crypto).

### 7.2 Les 9 passerelles

| Passerelle | Mode | Devises | Intégration | Priorité | Temps |
|---|---|---|---|---|---|
| **MonCash** (Digicel) | Auto (API REST OAuth) | HTG | `POST /v1/CreatePayment` → redirect → webhook retour | **P0** | 4 j |
| **Stripe** | Auto (Checkout + webhooks) | USD/EUR/+ | stripe-php, Payment Element, 3DS | **P0** | 3 j |
| **PayPal** | Auto (Orders v2 + webhooks) | USD | Smart Buttons (couvre aussi cartes) | P1 | 3 j |
| **NatCash** (Natcom) | Manuel → auto si API dispo | HTG | n° marchand + preuve (screenshot/ref) validée par admin | P1 | 2 j |
| **Zelle** | Manuel | USD | email/téléphone Zelle + preuve + rapprochement admin | P1 | 1 j |
| **Binance Pay** | Auto (merchant API) | USDT/BUSD | QR + webhook signature | P2 | 3 j |
| **CoinPayments** | Auto (IPN) | BTC/ETH/USDT | invoice + IPN HMAC | P2 | 3 j |
| **Unibank Online** | Manuel | HTG | virement + référence + preuve | P2 | 1 j |
| **Sogebank Online** | Manuel | HTG | idem | P2 | 1 j |

- **Mode manuel unifié** : une seule UX pour NatCash/Zelle/banques — instructions claires (n° de compte, montant exact, référence unique `KLA-XXXX`), upload de preuve, écran « En vérification », validation admin en 1 clic (file « À traiter » du dashboard admin), notification WhatsApp à la validation.
- **Webhooks** : vérification de signature systématique, idempotence par `external_ref`, retry queue.
- **Multi-devise** : prix pivot en USD + taux HTG administrable (mise à jour manuelle ou API) ; affichage dans la devise de la langue.
- Taxes/commissions : répartition automatique plateforme/instructeur/affilié/org au moment du `paid` (event `PaymentSucceeded`).

*Total paiements : P0-P2 · ⚙⚙⚙ · ~21 j répartis sur les phases.*

---

## 8. Formations En ligne / Présentiel / Hybride

Nouvelles tables (préfixe `klasyo_*`, zéro modification des tables LMSZAI — colonnes ajoutées uniquement nullable) :

- `klasyo_course_sessions` : `course_id, type (online|onsite|hybrid), title, starts_at, ends_at, location_name, address, gps, seats_total, seats_reserved, price_override, status`.
- `klasyo_session_registrations` : `session_id, user_id, status (registered|present|absent|cancelled), checkin_at, checkin_by`.
- **Calendrier** : vue mensuelle publique par cours + iCal export ; côté étudiant « Prochaines sessions » sur l'accueil.
- **Places** : jauge `seats_reserved/seats_total`, blocage à guichet fermé, liste d'attente (P3).
- **Liste de présence** : écran instructeur (mobile) avec la liste des inscrits, pointage tactile + **scan QR**.
- **QR de présence** : chaque inscription génère un QR signé (`session_id:user_id:HMAC`) affiché dans l'espace étudiant ; l'instructeur scanne (caméra web, lib html5-qrcode) → `checkin_at` horodaté ; anti-rejeu (un scan par session). Mode inverse possible : QR affiché en salle, scanné par l'étudiant (géo-vérifié P3).
- La présence alimente la **progression** et conditionne le **certificat** pour les formations présentielles.

*P2 · ⚙⚙ · 10 j.*

---

## 9. Nouvelles fonctionnalités

### 9.1 Certificats, badges, vérification publique
- **Certificat auto** à 100 % de complétion (ou présence validée) : PDF généré (DomPDF), gabarit KLASYO (bilingue FR/HT), n° unique `KLA-2026-XXXXXX`.
- **QR sur le certificat** → `klasyo.org/verify/{code}` : page publique (nom, cours, date, statut valide/révoqué) — anti-falsification, gratuite, indexable.
- **Badges numériques** : table `klasyo_badges` + attribution par règles (1er cours, 5 cours, streak 30 j, top quiz) ; standard Open Badges v2 (JSON-LD) pour LinkedIn (P3).
- **Carte étudiante numérique** : carte verticale mobile (photo, nom, ID, QR de vérification, validité) dans l'espace étudiant ; format wallet Apple/Google en P3 ; sert aussi de carte de présence en présentiel. Synergie directe avec le savoir-faire NFC TAGTOA (carte physique NFC en option payante).
- *P1 (certificats+QR) / P2 (badges, carte) · ⚙⚙ · 10 j.*

### 9.2 Notifications multicanal
- Service central `NotificationService` (pattern TAGTOA éprouvé) : canaux **email** (SMTP), **WhatsApp** (Twilio — déjà maîtrisé), **SMS** (Twilio SMS, fallback), préférences par utilisateur, queue Laravel, tolérant aux pannes (no-op si non configuré).
- Événements branchés : achat confirmé, preuve validée/refusée, nouveau cours d'un instructeur suivi, rappel session J-1/H-2, certificat émis, retrait payé, relance panier (P3).
- **WhatsApp intégré** : en Haïti c'est LE canal — bouton « Sipò WhatsApp » flottant sur le public, notifications transactionnelles via templates approuvés.
- *P1 · ⚙⚙ · 6 j.*

### 9.3 Analytics
- Dashboard admin (§5.1) + page Analytics dédiée : entonnoir visite→fiche→achat, cohortes de complétion, revenus par passerelle/devise/instructeur/affilié, CSV export. Événements stockés dans `klasyo_events` (léger, sans dépendance externe) ; Plausible/Matomo self-hosted en option P3.
- *P2 · ⚙⚙ · 6 j.*

### 9.4 Marketplace, coupons, paiements flexibles
- **Marketplace** : le multi-instructeurs/multi-org EST la marketplace — l'améliorer : page instructeur publique soignée, classements (« Top formateurs Haïti »), commission par catégorie, programme « KLASYO Select » (badge qualité).
- **Coupons** : `klasyo_coupons` (%, montant fixe, périmètre cours/instructeur/global, quota, expiration, 1/usager) + champ code au checkout + stats d'usage. *P1 · ⚙ · 3 j.*
- **Paiement en plusieurs fois** : `klasyo_payment_plans` (échéancier 2-4 tranches) ; accès au cours maintenu si échéances à jour, suspendu sinon ; rappels WhatsApp J-3/J0 ; auto-charge si carte (Stripe), manuel sinon. *P2 · ⚙⚙⚙ · 8 j.*
- **Acompte (présentiel)** : réservation de place à X %, solde avant J-1 ; statuts `deposit_paid → fully_paid` ; intégré aux sessions §8. *P2 · ⚙⚙ · 4 j (avec plans).*

---

## 10. Intelligence Artificielle (Claude API)

### 10.1 Architecture

- **Un service central `KlasyoAi`** (addon Laravel) qui encapsule le SDK PHP officiel Anthropic (`composer require anthropic-ai/sdk`) : gestion clé API (`.env`), retries, quotas par plan SaaS (l'IA devient un argument de vente des forfaits Pro/Biznis), journalisation des coûts par organisation.
- **Modèles** : `claude-opus-4-8` par défaut (génération de cours/quiz/examens — qualité maximale, $5/$25 par MTok) ; `claude-haiku-4-5` pour les tâches légères à fort volume (traduction UI, chatbot support, reformulations — $1/$5 par MTok). Streaming SSE pour l'assistant/chatbot (réponse progressive) ; **Batch API** (-50 %) pour les traductions massives de contenu ; **prompt caching** sur les prompts système (catalogue, contexte du cours) pour réduire les coûts.
- Sorties **structurées** (`output_config.format` JSON schema) pour tout ce qui remplit la base (quiz, plans de cours) → zéro parsing fragile.
- File d'attente Laravel pour les générations longues ; l'UI affiche « Jenerasyon an ap fèt… » avec polling.

### 10.2 Fonctionnalités par phase

| Fonction | Description | Modèle | Phase | Temps |
|---|---|---|---|---|
| **AI Course Generator** | Titre+public+objectifs → plan complet (sections, leçons, durées, descriptions) inséré en brouillon | opus-4-8 | P2 | 5 j |
| **AI Lesson Generator** | Plan de leçon → contenu texte structuré + script vidéo + ressources | opus-4-8 | P2 | 3 j |
| **AI Quiz Generator** | Contenu de leçon → QCM/V-F avec distracteurs plausibles + corrigés (JSON schema → banque de questions) | opus-4-8 | P2 | 3 j |
| **AI Assignment/Exam Generator** | Devoirs + grilles de correction ; examens équilibrés par difficulté | opus-4-8 | P2 | 3 j |
| **AI Assistant (instructeur)** | Panneau latéral dans le wizard de création : améliorer titre/description SEO, suggérer prix marché | opus-4-8 | P2 | 3 j |
| **AI Tutor (étudiant)** | Chat contextuel dans le player : répond en se basant SEULEMENT sur le contenu du cours (contexte injecté, garde-fous), explique en créole simple | opus-4-8 (haiku si volume) | P3 | 6 j |
| **AI Translator** | Traduction FR↔HT↔EN↔ES des cours/descriptions/UI en lot (Batch API) ; le créole est un différenciateur majeur | haiku-4-5 / opus pour HT | P2 | 4 j |
| **AI Content Improver** | Reformuler/raccourcir/corriger descriptions et leçons | haiku-4-5 | P2 | 2 j |
| **AI Resume Generator** | CV de compétences généré depuis les cours complétés + certificats (PDF export) | opus-4-8 | P3 | 3 j |
| **AI Chatbot (support public)** | FAQ + navigation guidée (« Kijan pou m peye ak MonCash ? »), escalade humaine WhatsApp | haiku-4-5 | P3 | 4 j |
| **AI Voice** | Lecture audio des leçons texte (TTS externe type ElevenLabs/Polly — Claude ne fait pas de TTS) ; utile offline/faible littératie | TTS tiers | P3 | 4 j |
| Sous-titres/transcription vidéo | Whisper/AssemblyAI puis résumé Claude par leçon | tiers + haiku | P3 | 4 j |

**Garde-fous** : l'IA propose, l'instructeur valide (jamais de publication auto) ; mention « Généré avec l'aide de l'IA » optionnelle ; quotas mensuels par plan ; pas de données personnelles dans les prompts.

---

## 11. UX/UI — Système de design KLASYO

### 11.1 Fondations (extraites de la landing existante — déjà en prod)

```css
:root{
  --blue:#1d4ed8; --blue-dark:#1e3a8a; --blue-mid:#2563eb; --blue-light:#3b82f6;
  --blue-pale:#eff6ff; --green:#16a34a; --green-dark:#166534; --green-light:#22c55e;
  --bg:#f8faff; --dark:#0c1524; --white:#fff;
  --grad-main:linear-gradient(135deg,#1d4ed8,#1e3a8a);
  --grad-hero:linear-gradient(135deg,#1d4ed8 0%,#0f2d6b 60%,#16a34a 100%);
  --radius:18px; --radius-lg:28px;
  --shadow-sm:0 2px 12px rgba(29,78,216,.08); --shadow-md:0 8px 40px rgba(29,78,216,.12);
  --font-display:'Sora',sans-serif; --font-body:'DM Sans',sans-serif;
}
```

- **Bleu** = confiance/action (CTA primaires), **vert** = succès/argent/validation, **rouge #ef4444** réservé aux erreurs. Contraste AA minimum (texte #374151 sur blanc).
- **Typo** : Sora (titres, chiffres KPI), DM Sans (corps). Échelle 12/14/16/20/24/32/40.
- **Composants** : boutons pleins arrondis (radius 12, hauteur 44 px), cartes blanches ombre `--shadow-sm`, inputs à gros labels, badges pilules, toasts, skeletons.
- **Implémentation sur LMSZAI sans fork** : `public/frontend/assets/css/klasyo-theme.css` chargé APRÈS `style.css`/`extra.css` dans `frontend/layouts/app.blade.php` (+ variables injectées via `dynamic-style.blade.php`) → on écrase couleurs, typos, radius, boutons, cartes. Les 6 autres thèmes LMSZAI sont désactivés (source de confusion).

### 11.2 Audit UX écran par écran (platform)

| Écran | Problèmes UX/UI constatés (démo LMSZAI) | Correctifs |
|---|---|---|
| Accueil | 8+ sections, 3 carrousels, textes lorem anglais, achievements démo | 5 sections max, contenu réel bilingue, 1 seul carrousel |
| Header/nav | 2 niveaux de menus, liens morts démo, sélecteur devise confus | 1 barre : logo, recherche, Catégories, Enseigner, langue, panier, avatar |
| Fiche cours | CTA noyé sous le fold, méta dispersées | carte d'achat sticky, hiérarchie prix→CTA→contenu |
| Checkout | multi-étapes, champs inutiles, passerelles en vrac | 1 page : récap + choix passerelle filtré par devise + bouton unique |
| Formulaires | labels flottants illisibles, erreurs en anglais | labels au-dessus, messages FR/HT, validation inline |
| Player | sidebar dense, petits liens | onglets, cibles tactiles 44 px, plein écran mobile |
| Dashboards | menus 20+ entrées, tableaux non responsives | §5 ; tableaux → cartes empilées en mobile |
| Icônes | mélange FontAwesome/Feather | un seul set (Lucide/Feather), taille 20/24 |
| Images | JPEG lourds non dimensionnés | WebP + `srcset` + lazy loading |

### 11.3 Mobile-first & offline
- Breakpoints : base = 360 px ; enrichissement progressif.
- **PWA** (pattern TAGTOA POS déjà éprouvé) : manifest + service worker — app shell cache-first, pages cours network-first, **file d'attente hors-ligne** pour progression/quiz (IndexedDB, sync au retour réseau), téléchargement des leçons texte/audio pour lecture offline (P2-P3).
- Poids cible première visite < 300 Ko CSS+JS (hors vidéo).

---

## 12. Performance, sécurité, SEO

| Domaine | Actions | Phase |
|---|---|---|
| **Laravel** | `config:cache`, `route:cache`, `view:cache` au déploiement ; OPcache ; upgrade PHP 8.0→8.2 (platform) ; queues en `database` puis Redis | P1→P2 |
| **Base de données** | index sur FK/colonnes filtrées (audit slow query log) ; éliminer les N+1 (eager loading) sur catalogue/dashboards ; pagination partout | P1 |
| **Cache applicatif** | cache 5-15 min sur accueil/catalogue/settings (`Cache::remember`) ; Redis en P2 | P1 |
| **Vidéo** | migrer vers **Bunny Stream** (ou Cloudflare Stream) : encodage adaptatif, CDN, protection des liens signés — le VPS ne doit JAMAIS servir la vidéo | P2 |
| **Assets** | WebP, minification, `defer`, purge CSS des thèmes morts ; CDN statique (BunnyCDN) | P1-P2 |
| **API/mobile** | endpoints JSON propres (LMSZAI a déjà une base API) ; rate limiting ; pagination cursor | P2 |
| **SEO** | métas dynamiques par cours (title/description/OG), sitemap.xml, schema.org `Course`/`Organization`, URLs propres, hreflang fr/ht/en/es, blog actif | P1-P2 |
| **Sécurité** | P0 §2.2 + : rotation des secrets exposés, HTTPS forcé + HSTS, headers CSP/X-Frame, 2FA admin, rate limit login/OTP, sauvegardes quotidiennes DB+uploads hors serveur (test de restauration mensuel !), audit log des actions admin (pattern TAGTOA `AuditService`), validation stricte des uploads de preuves | P0-P1 |
| **Monitoring** | uptime (UptimeRobot), erreurs (Sentry/Flare ou log alerting), smoke tests post-deploy via klasyo-ops | P1 |

---

## 13. Feuille de route

### PHASE 1 — MVP « Louvri pòt la » (6-8 semaines, ~45 j·dev)
> Objectif : plateforme sûre, brandée KLASYO, achetable en HTG et USD.

1. **Semaine 1** : P0 sécurité (§2.2) — zips hors web, .env corrigés, school réparé, APP_KEY, debug off. ✔ mesurable : school en ligne, scan sécurité propre.
2. **S1-2** : rebranding complet (logo, `settings` DB, `klasyo-theme.css`, traduction FR + HT des textes publics, purge contenu démo, 1 seul thème actif).
3. **S2-3** : Auth + onboarding wizard (§6) — inscription réactivée, choix de profil, comptes fonctionnels.
4. **S3-5** : Paiements P0 — Payment Hub + **MonCash** + **Stripe** + mode manuel unifié (NatCash/Zelle) + file de validation admin.
5. **S4-6** : dashboards simplifiés v1 (menus réduits, KPI de base) + certificats PDF avec QR + page `/verify`.
6. **S6-8** : coupons, notifications email+WhatsApp de base, SEO on-page, monitoring, formation de l'équipe. **Lancement.**

### PHASE 2 — PRO « Grandi » (3-4 mois, ~80 j·dev)
1. Dashboards premium complets (§5, graphiques, rapports, marketing).
2. SSO KLASYO Connect platform↔school + cross-sell org→école.
3. Formations présentiel/hybride + calendrier + QR présence (§8).
4. Paiements P1-P2 : PayPal, Binance Pay, CoinPayments, banques haïtiennes ; paiement en plusieurs fois + acompte.
5. IA vague 1 : Course/Lesson/Quiz/Exam Generator + Translator + Content Improver (§10).
6. Vidéo sur Bunny Stream ; Redis ; recherche Meilisearch ; PWA v1 ; analytics ; affiliation modernisée ; badges.

### PHASE 3 — ENTERPRISE « Domine » (6-9 mois, ~120 j·dev)
1. IA vague 2 : AI Tutor, Chatbot support, Resume Generator, Voice/TTS, transcription.
2. Marketplace avancée (KLASYO Select, classements, pages instructeurs premium).
3. Offline complet (leçons téléchargeables), apps mobiles (wrapper Capacitor ou Flutter sur l'API).
4. Cartes étudiantes wallet + NFC (synergie TAGTOA), Open Badges, liste d'attente, géo-checkin.
5. KLASYO Connect → OAuth2 complet ; API publique partenaires ; SLA, multi-serveur si trafic (séparation DB/web, load balancer).
6. Conformité : factures normalisées, exports comptables, RGPD-like pour la diaspora.

### Récapitulatif des estimations

| Phase | Durée calendrier | Charge dev | Focus |
|---|---|---|---|
| MVP | 6-8 semaines | ~45 j | Sécurité, marque, paiements HT, simplicité |
| PRO | 3-4 mois | ~80 j | Dashboards, SSO, présentiel, IA v1, vidéo CDN |
| ENTERPRISE | 6-9 mois | ~120 j | IA v2, marketplace, offline, NFC, échelle |

---

## Annexe A — Règles d'ingénierie (pour toute la suite du chantier)
1. **Jamais de modification destructive** des tables des scripts achetés : nouvelles tables `klasyo_*`, colonnes ajoutées nullable, addons en ServiceProviders séparés.
2. Tout déploiement passe par le canal `klasyo-ops` (backup avant écriture, aucun secret dans les logs publics).
3. Chaque phase livre des incréments testables chaque semaine ; smoke test public après chaque déploiement.
4. Montants en unités mineures ; idempotence sur tout ce qui encaisse ; audit log sur tout ce qui touche l'argent.
