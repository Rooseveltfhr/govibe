# TAGTOA — Pré-mortem & plan de neutralisation des risques

> Exercice : « Nous sommes 3 mois après la livraison, TAGTOA a été un échec total.
> Pourquoi ? » — puis on conçoit le livrable pour neutraliser ces causes **dès le départ**.
> Spécifique à TAGTOA (paiements/POS/fidélité multi-tenant, marché Haïti/3G, greffé
> sur le SaaS vcard Biztap en production).

---

## Les 5 causes d'échec les plus probables (par gravité)

### 🔴 1. Intégrité financière compromise (CATASTROPHIQUE)
TAGTOA manipule de l'argent réel (HTG/USD) : POS, recharges fidélité, commissions.
Un seul bug — double comptage en sync offline, prix falsifié, solde négatif, course
sur le solde, commission fausse — détruit la confiance des marchands et coûte de
l'argent réel. **C'est le cœur de la valeur ; ici une erreur = perte directe.**

**Neutralisé par :**
- Transactions DB + `lockForUpdate()` sur soldes (Loyalty `topUp/redeem`, POS sale). ✅
- **Idempotence** par `client_uuid` (POS sale, Event check-in) → un rejeu offline ne
  duplique pas. ✅
- **Prix imposé côté serveur** pour les produits du catalogue POS (anti-tampering du
  prix client) — corrigé dans `PosService::recordSale`. ✅
- Montants en `decimal` (casts), jamais stockés en float ; commission bornée au brut. ✅
- Historique vérifiable : `balance_after`/`points_after`, journal des commissions. ✅
- Tests automatisés des flux argent (`tests/Unit` Luhn/commission en CI ;
  `tests/Feature/MoneyLogicTest` idempotence + insuffisance de fonds à exécuter dans Biztap). ✅
- **À faire avant lancement :** exécuter les Feature tests dans Biztap CI ; ajouter un
  rapprochement quotidien (somme ventes vs encaissements).

### 🔴 2. Casser le site Biztap en production lors du déploiement (TRÈS ÉLEVÉ)
TAGTOA se greffe sur tagtoa.com qui sert **déjà des clients vcard payants**. Un
déploiement raté met TOUT le site en 500 (déjà arrivé : module activé avant que
l'autoload soit prêt). Auto-déployer sans garde-fous = casser la prod en boucle.

**Neutralisé par :**
- **Autoload déterministe** : PSR-4 du module enregistré explicitement (pas de
  dépendance au timing de merge-plugin). ✅
- **Procédure de déploiement sûre** (voir `DEPLOY.md` §Safe deploy) : sauvegarde →
  `php artisan down` (maintenance) → déployer → `migrate --force` → **smoke test** →
  `php artisan up` ; **rollback automatique** si une étape échoue.
- Module **désactivable en 1 commande** sans toucher la DB (`modules_statuses.json`),
  rollback des migrations isolé (`--path=Modules/Tagtoa/Database/migrations`). ✅
- CI exécute lint + tests **avant** tout merge. ✅
- **À faire :** idéalement un sous-domaine **staging** (ex. `staging.tagtoa.com`) pour
  tester avant la prod.

### 🔴 3. Fuite de données / faille de sécurité multi-tenant (CATASTROPHIQUE si arrive)
App d'argent + multi-tenant + PII (preuves de paiement, téléphones). Une IDOR/XSS qui
expose les données d'un marchand à un autre = désastre légal et réputationnel.

**Neutralisé par :**
- Revue de sécurité effectuée + correctifs : **XSS** (page Pay), **IDOR cross-tenant**
  (stock POS, `vcard_id`/`pay_page_id`) — voir `SECURITY.md`. ✅
- Tout le dashboard scopé par tenant (`Tenant::id()`), middleware
  `auth+valid.user+role:admin+multi_tenant`. ✅
- Uploads validés (image/mimes/taille), CSRF actif, jetons publics non devinables. ✅
- **À faire :** confirmer le global scope tenant de `App\Models\Vcard` ; HTTPS forcé ;
  pen-test léger avant lancement public.

### 🟠 4. Décalage « marche en théorie » vs réalité Biztap (ÉLEVÉ)
Le code était lint-clean mais n'avait jamais **tourné** contre le vrai `Vcard`, les
helpers, le schéma réel. Des hypothèses fausses (relation `user()`, scoping, middleware)
= fonctionnalités silencieusement cassées une fois en prod.

**Neutralisé par :**
- Vérification de compatibilité contre le vrai `composer.json` + `routes/web.php`
  (`COMPATIBILITY.md`). ✅
- Déploiement en cours **sur le vrai Biztap** pour valider l'exécution réelle.
- **Checklist smoke-test** post-déploiement (chaque module ouvert et testé) dans `DEPLOY.md`.
- Fallbacks défensifs (`Tenant` helper tolérant, `vcard->user` sinon `->email`,
  QR fallback). ✅

### 🟠 5. Échec d'adoption / exploitation (ÉLEVÉ)
Même si ça marche : si les marchands haïtiens (3G lent) trouvent ça confus, si le
paiement reste 100 % manuel (preuve à valider à la main, ne passe pas à l'échelle),
s'il n'y a ni monitoring, ni sauvegardes, ni formation, ni doc → personne n'utilise,
la démo DEVEXPO rate, et le « bus factor » (une seule personne) bloque tout.

**Neutralisé par :**
- Dashboard **propre et simple**, pages publiques **standalone optimisées 3G**
  (vanilla JS, lazy, QR SVG). ✅
- Seeder de démo DEVEXPO prêt. ✅
- Documentation complète (`INSTALL`, `DEPLOY`, `COMPATIBILITY`, `SECURITY`, ce fichier)
  → réduit le bus factor. ✅
- **À faire (phase 2) :** intégration **MonCash API** (paiement automatique vs preuve
  manuelle) ; monitoring/erreurs (Sentry/Ignition + log-viewer déjà présent) ;
  sauvegardes DB automatiques (DirectAdmin) ; mini-guide marchand.

---

## Synthèse — état des garde-fous
| Risque | Gravité | Couverture actuelle |
|--------|---------|---------------------|
| 1. Intégrité financière | 🔴 | Élevée (tx, idempotence, prix serveur, tests) — reste : reconciliation + CI Feature |
| 2. Casser la prod | 🔴 | Élevée (autoload déterministe, deploy sûr, rollback) — reste : staging |
| 3. Sécurité/multi-tenant | 🔴 | Élevée (revue + fixes) — reste : confirmer Vcard scope, pen-test |
| 4. Décalage prod | 🟠 | En cours (déploiement réel + smoke-tests) |
| 5. Adoption/exploitation | 🟠 | Moyenne (UX+docs faits) — reste : MonCash API, monitoring, formation |

**Règle d'or :** ne jamais auto-déployer vers la prod sans sauvegarde + maintenance +
smoke-test + rollback. La vitesse ne vaut rien si elle casse l'argent ou la confiance.
