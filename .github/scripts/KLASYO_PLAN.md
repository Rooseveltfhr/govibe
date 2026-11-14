# KLASYO.ORG — Plan de travail (platform + school)

> Canal d'exécution : workflow `.github/workflows/klasyo-ops.yml` (SSH vers le VPS
> avec les secrets du deploy TAGTOA). Les logs Actions sont PUBLICS : aucun secret
> ne doit jamais y apparaître.

## État des lieux (inspection 2026-07-14)

| App | Chemin | Script | Framework | DB |
|---|---|---|---|---|
| Landing | `public_html/index.html` | statique « KLASYO — La Plateforme Éducative du Futur en Haïti » | — | — |
| PLATFORM | `public_html/platform/` | **LMSZAI** build 31 (Zainik Themes) — vente de cours/formations | Laravel 9 / PHP 8.0 | 139 tables, `users` (1) |
| SCHOOL | `public_html/school/` | **eSchool SaaS v1.9.3** — gestion d'établissements scolaires (multi-écoles, `school_id`) | Laravel 10 / PHP 8.1 | 45 tables, `users` (1) |

### Problèmes détectés (en attente de décision utilisateur)
1. **Zips de code source téléchargeables dans public_html** : `platform/klasyo.zip` (147 MB),
   `school/school.zip` (568 MB), `school/source_code.zip`, zips d'installation/update.
   → À déplacer hors du web (ex. `~/backups_klasyo/`).
2. `school/.env` : `APP_DEBUG=true` en production (fuite de secrets sur erreur).
3. `platform/.env` : `APP_URL="webetud.com"` (mauvais domaine), `APP_ENV=local`.
4. `https://klasyo.org` renvoie 403 depuis l'extérieur (à confirmer navigateur / firewall).

## Chantier A — SSO « KLASYO Connect » (1 compte pour les 2 apps)

Décision : platform (LMSZAI) = compte maître. Pont SSO signé (HMAC, jeton 60 s) :
- platform : route `GET /klasyo-sso/authorize?redirect=...` (auth requise) → génère
  `token = base64(payload) + HMAC_SHA256(payload, KLASYO_SSO_SECRET)` avec
  `{email, name, exp}` → redirige vers school.
- school : route `GET /klasyo-sso/callback?token=...` → vérifie signature + exp →
  `User::firstOrCreate` par email (mot de passe aléatoire, rôle/école par défaut —
  **mapping à confirmer par l'utilisateur**) → `Auth::login` → redirect dashboard.
- Bouton « Se connecter avec KLASYO » sur l'écran de login school.
- Secret partagé `KLASYO_SSO_SECRET` ajouté aux deux `.env` (généré sur le VPS,
  jamais dans les logs).
- Fichiers déposés en **addon** (nouveau ServiceProvider + routes dédiées) pour ne
  pas casser les mises à jour des scripts achetés.

## Chantier B — Frontend platform (LMSZAI) aux couleurs KLASYO

Approche : thème par-dessus (override), pas de fork lourd du vendor :
1. Extraire la marque de la landing racine (couleurs, polices) — inspection v3.
2. `public/frontend/css/klasyo-theme.css` : variables CSS + overrides (boutons,
   cartes cours, header/footer, typographie, espacement).
3. Ajuster les vues Blade clés (header/footer/home) au minimum nécessaire ;
   logo + textes via les settings LMSZAI en DB quand possible.
4. Simplification UX : nav allégée, hero clair, parcours « voir cours → acheter »
   en moins de clics.
5. Vérifier après chaque déploiement : page publique OK (HTTP 200 + smoke).

## Règles de sécurité du canal klasyo-ops
- Lecture seule par défaut ; toute écriture = script dédié, revu, idempotent.
- Jamais de `cat .env`, de credentials ni de dumps DB dans la sortie.
- Backup avant modification (`cp -a fichier fichier.bak-YYYYMMDD`).
