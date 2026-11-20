# rooseveltforestal.com

Site personnel, éditorial et participatif de Roosevelt Forestal.

- **Pages statiques** (accueil, à propos, écosystème, conférences, contact, **campagne « NOU PA NAN BEL DISKOU ANKO »**) : HTML/CSS/JS sans dépendance. Header/footer/head/scripts sont centralisés dans `partials/` et injectés par `node tools/sync-chrome.mjs`.
- **Module dynamique** (PHP + SQLite) : programme, propositions, actualités, vidéothèque YouTube, recherche, PDF (FPDF), espace citoyen, newsletter, statistiques anonymes, administration `/admin`. Code dans `app/`, point d'entrée `app.php`.
- **Générateur de flyer** : `assets/js/flyer.js` (Canvas, 100 % côté navigateur — la photo n'est jamais envoyée).

Docs : `docs/AUDIT-ET-PLAN.md` · `docs/DEPLOIEMENT.md`
Développement local : `php -S 127.0.0.1:8099 -t branding branding/tools/dev-router.php`
Livraison : `bash branding/tools/build-release.sh` → ZIP prêt pour l'hébergement.
Configuration (liens sociaux, Calendly) : bloc `RF` en haut de `assets/js/main.js`.
