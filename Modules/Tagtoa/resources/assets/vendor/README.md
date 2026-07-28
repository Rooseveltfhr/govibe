# Assets tiers auto-hébergés (souverains)

Ces fichiers sont servis depuis notre propre origine via `AssetController`
(route publique `tagtoa.asset`) au lieu d'un CDN. Objectif : pas de point de
défaillance externe pour les fonctionnalités critiques (scan de billets à
l'entrée d'un événement, où le wifi du lieu peut être faible/absent), pas de
fuite vers un tiers, chargement plus rapide (même origine, cache immuable).

| Fichier | Source | Version |
|---|---|---|
| `html5-qrcode.min.js` | npm `html5-qrcode` | 2.3.8 |
| `fontawesome-6.5.1.css` + `fa-*.woff2` | npm `@fortawesome/fontawesome-free` | 6.5.1 |

Mise à jour html5-qrcode : `npm pack html5-qrcode@<version>`, extraire
`package/html5-qrcode.min.js`, remplacer le fichier, mettre à jour la version.

Mise à jour Font Awesome : `npm pack @fortawesome/fontawesome-free@<version>`,
copier `package/css/all.min.css` (→ `fontawesome-<version>.css`) + les webfonts
`package/webfonts/fa-*.woff2`. Réécrire dans le CSS les `url(../webfonts/X.woff2)`
en `url(/tagtoa-asset/X.woff2)` (woff2 uniquement), puis déclarer chaque fichier
dans la liste blanche `AssetController::ASSETS`.
