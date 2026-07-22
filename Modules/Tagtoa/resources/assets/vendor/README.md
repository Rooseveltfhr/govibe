# Assets tiers auto-hébergés (souverains)

Ces fichiers sont servis depuis notre propre origine via `AssetController`
(route publique `tagtoa.asset`) au lieu d'un CDN. Objectif : pas de point de
défaillance externe pour les fonctionnalités critiques (scan de billets à
l'entrée d'un événement, où le wifi du lieu peut être faible/absent), pas de
fuite vers un tiers, chargement plus rapide (même origine, cache immuable).

| Fichier | Source | Version |
|---|---|---|
| `html5-qrcode.min.js` | npm `html5-qrcode` | 2.3.8 |

Mise à jour : `npm pack html5-qrcode@<version>`, extraire `package/html5-qrcode.min.js`,
remplacer le fichier, mettre à jour la version ci-dessus.
