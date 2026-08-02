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
| `tagtoa-fonts.css` + `anton-400.woff2`, `nunito-400-800.woff2`, `spacegrotesk-500-700.woff2` | Google Fonts (`fonts.googleapis.com/css2`) | Anton v27, Nunito v32, Space Grotesk v22 |

Mise à jour html5-qrcode : `npm pack html5-qrcode@<version>`, extraire
`package/html5-qrcode.min.js`, remplacer le fichier, mettre à jour la version.

Mise à jour Font Awesome : `npm pack @fortawesome/fontawesome-free@<version>`,
copier `package/css/all.min.css` (→ `fontawesome-<version>.css`) + les webfonts
`package/webfonts/fa-*.woff2`. Réécrire dans le CSS les `url(../webfonts/X.woff2)`
en `url(/tagtoa-asset/X.woff2)` (woff2 uniquement), puis déclarer chaque fichier
dans la liste blanche `AssetController::ASSETS`.

Mise à jour des polices : sous-ensemble **latin** uniquement (U+0000-00FF —
couvre tous les accents fr/ht/es, donc pas besoin de latin-ext/cyrillic/vietnamese).
Nunito et Space Grotesk sont servies par Google en police VARIABLE : une requête
`css2?family=X:wght@400;500;...` renvoie le MÊME fichier .woff2 pour tous les
poids demandés (seul `font-weight` change dans le `@font-face`) — un seul
téléchargement suffit. Pour mettre à jour : requêter
`https://fonts.googleapis.com/css2?family=Anton&family=Space+Grotesk:wght@500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap`
avec un User-Agent desktop, garder uniquement les blocs `/* latin */`,
télécharger chaque `url(...)` en `.woff2`, remplacer les fichiers ici et dans
`tagtoa-fonts.css`, puis mettre à jour `AssetController::ASSETS` si les noms changent.
