# Audit & plan — rooseveltforestal.com

## 1. Audit de l'existant (avant cette mission)

| Élément | État constaté |
|---|---|
| Stack | Site **100 % statique** : 6 pages HTML + 1 CSS + 1 JS, polices Anton/Inter auto-hébergées. Aucun backend, aucune base de données. |
| Déploiement | Manuel (ZIP → File Manager DirectAdmin → `public_html/`). Aucun auto-deploy pour ce site. |
| Pages | Accueil, À propos, Écosystème, Speaking, Media, Contact. |
| Points forts | Design noir/blanc/or cohérent, responsive, SEO de base (meta, OG, JSON-LD), rapide (≈ 0 dépendance), Calendly + réseaux intégrés. |
| Faiblesses | Header/footer **dupliqués** dans chaque page (une modif de menu = 6 fichiers) ; images chargées par JS (invisibles aux crawlers) ; aucune gestion de contenu (articles, propositions, vidéos) ; pas de partage social ; pas d'aperçu OG par contenu ; formulaire contact = `mailto:` ; aucune statistique ; navigation sans entrée « Programme / Actualités / Vidéos / Participer ». |
| Risques | (1) Un déploiement dans un sous-dossier casse images/menus (déjà arrivé) → tout est en chemins **relatifs**. (2) Les aperçus WhatsApp/Facebook **ne lisent pas le JavaScript** → les pages de contenu doivent être rendues côté serveur ou statiques. (3) Données personnelles (abonnés, soumissions citoyennes) → stockage hors de portée du web. |

## 2. Décision d'architecture

- **Pages éditoriales fixes** (accueil, à propos, campagne…) : restent en **HTML statique** → fonctionnent sur n'importe quel hébergement, ultra rapides. Header/footer centralisés dans `partials/` et synchronisés par `tools/sync-chrome.mjs`.
- **Module dynamique** (programme, propositions, articles, vidéos, espace citoyen, newsletter, stats, admin) : **PHP 7.4+ + SQLite** (fichier unique, aucune dépendance Composer ni serveur MySQL). Choix justifié : rendu serveur = vrais aperçus Open Graph et SEO ; publication programmée ; coût nul ; les hébergements DirectAdmin ont PHP + SQLite.
- **Campagne « NOU PA NAN BEL DISKOU ANKO »** : page **statique** + générateur de flyer **100 % côté navigateur** (Canvas). La photo du participant **ne quitte jamais son téléphone** (aucun upload serveur). Fonctionne même sans PHP.
- **PDF** : générés par **FPDF** (MIT, un seul fichier) → programme complet (couverture, sommaire, pagination) et fiche par proposition.
- Bibliothèques tierces : `qrcode-generator` (MIT), icônes officielles `simple-icons` (CC0), FPDF. Rien d'autre.

## 3. Plan de livraison

| Phase | Contenu | Ordre d'exécution |
|---|---|---|
| 1 | Navigation, header/footer unifiés, FR/HT/EN, images SEO-friendly, favicon, performance, accessibilité | 1 |
| 4 | Page campagne + Flyer Generator + partage + OG | 2 (priorité : indépendante du backend, fonctionne partout) |
| 2 | Programme, propositions (16 catégories), recherche, PDF | 3 |
| 3 | Actualités (10 types), vidéothèque YouTube, partage social, OG | 3 |
| 5 | Espace citoyen, newsletter, statistiques, administration sécurisée | 3 |

## 4. Principes de contenu

Le programme politique appartient à Roosevelt Forestal : **aucune proposition n'est inventée**. Les exemples fournis sont en statut *brouillon* (non publics) pour montrer la structure ; l'équipe les remplace avant publication.
