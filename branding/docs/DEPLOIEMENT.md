# Déploiement de rooseveltforestal.com

## Prérequis hébergement
- **PHP 7.4 ou plus** (8.x recommandé) avec les extensions `pdo_sqlite`, `mbstring`, `fileinfo`, `gd` (activées par défaut sur DirectAdmin/cPanel).
- **Apache ou LiteSpeed** (les fichiers `.htaccess` sont fournis). Sur Nginx pur : voir « Nginx » ci-dessous.
- Les dossiers `data/` et `uploads/` doivent être **modifiables par PHP** (droits 755, ou 775 selon l'hébergeur).
- HTTPS activé (Let's Encrypt dans DirectAdmin → *SSL Certificates*).

## Mise en ligne (File Manager DirectAdmin)
1. Dans `domains/rooseveltforestal.com/public_html/`, **supprimez l'ancien contenu** du site.
2. Envoyez le ZIP `rooseveltforestal-site.zip` dans `public_html/`, puis **Extract**.
3. Vérifiez que `index.html`, `app.php`, `.htaccess` et les dossiers `assets/`, `app/`, `data/`, `partials/`, `uploads/` sont **directement dans `public_html/`** (pas dans un sous-dossier).
4. Ouvrez `https://rooseveltforestal.com/` — l'accueil s'affiche.

> Si les fichiers `.htaccess` sont invisibles : activez « Afficher les fichiers cachés » dans le File Manager.

## Première connexion à l'administration
1. Ouvrez `https://rooseveltforestal.com/admin` → page **Installation**.
2. Dans le File Manager, ouvrez `data/SETUP-CODE.txt` : copiez le code (il est créé à la première visite et supprimé après l'installation).
3. Renseignez le code, votre e-mail et un mot de passe de 10 caractères minimum.
4. Connectez-vous ensuite sur `/admin/login`.

## Premiers réglages (Admin → Réglages)
- Adresse du site, e-mail qui reçoit les notifications, biographies (page Presse + dossier PDF).
- Remplacez les **exemples en brouillon** (1 proposition, 1 article) par vos vrais contenus, puis **publiez**.

## Vérifications rapides
| Adresse | Attendu |
|---|---|
| `/nou-pa-nan-bel-diskou-anko/` | Page de campagne + générateur de flyer |
| `/programme`, `/propositions`, `/actualites`, `/videos` | Pages dynamiques (vides tant que rien n'est publié) |
| `/programme.pdf` | PDF généré (couverture + sommaire) |
| `/sitemap.xml` | Plan du site |
| `/data/` | **403/404** (jamais accessible) |

## Mises à jour futures
Remplacez les fichiers du ZIP **sauf** `data/` (base de données) et `uploads/` (images téléversées) : ils contiennent vos contenus.

## Nginx (hébergement sans Apache)
Ajoutez :
```
location / { try_files $uri $uri/ /app.php?$query_string; }
location ~ ^/(data|app|partials)/ { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```

## E-mails (newsletter, notifications)
Le site utilise la fonction `mail()` de PHP (expéditeur `mr@rooseveltforestal.com`). Créez cette boîte dans DirectAdmin et activez SPF/DKIM pour éviter les spams. Si les e-mails ne partent pas, la confirmation d'un abonné peut être faite à la main dans Admin → Newsletter.

## Sauvegarde
Téléchargez régulièrement `data/` et `uploads/` (contenus, abonnés, messages citoyens).

## Sécurité (déjà en place)
Mots de passe hachés, protection CSRF, limitation de débit (connexion, formulaires, statistiques), champs piège + délai anti-robot, validation et ré-encodage des images, échappement systématique, base SQLite au nom aléatoire dans un dossier interdit au web, aucun script exécutable dans `uploads/`.

## Si le VPS / l'hébergement PHP n'est pas disponible (plan B)
Le site est conçu en deux couches :

| Couche | Contenu | Exige PHP ? |
|---|---|---|
| **Statique** | Accueil, À propos, Écosystème, Conférences, Contact, **campagne + générateur de flyer**, 404 | **Non** — fonctionne sur n'importe quel hébergement statique (Netlify, Cloudflare Pages, GitHub Pages, stockage S3/CDN, hébergement mutualisé sans PHP) |
| **Dynamique** | Programme, propositions, actualités, vidéos, espace citoyen, newsletter, Presse & Media, PDF, admin, statistiques | **Oui** (PHP 7.4+ et SQLite) |

**Mise en ligne rapide du statique** : publiez le contenu du ZIP **sans** les dossiers `app/`, `data/`, `uploads/` et sans `app.php`. Le générateur de flyer marche à 100 % (tout se passe dans le navigateur) ; les liens vers les modules dynamiques affichent la page `404.html` (« Cette section arrive très bientôt »). Les mesures d'audience échouent silencieusement et les blocs « À la une » restent masqués.

**Retour à la version complète** : dès qu'un hébergement PHP est disponible (le VPS, ou un petit hébergement mutualisé à quelques dollars par mois), envoyez le ZIP complet — aucune modification de code n'est nécessaire.

> Important : l'aperçu WhatsApp/Facebook de la campagne dépend de `assets/img/og-campaign.jpg` et des balises de la page : ils fonctionnent en statique.
