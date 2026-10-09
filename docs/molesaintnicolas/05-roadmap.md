# 05 — MVP, roadmap et plan de développement

Répond aux points 4, 6, 11, 14 et 15 de la première tâche.

---

## 1. MVP recommandé

### Le critère de sélection

Une fonctionnalité entre dans le MVP si elle satisfait **les trois conditions** :

1. Elle apporte de la valeur **sans dépendre d'un tiers** (ni partenaire actif,
   ni fournisseur de paiement, ni volume de trafic).
2. Elle contribue à l'acquisition SEO, qui est le moteur du projet.
3. Son absence rendrait le site non crédible auprès d'un premier visiteur.

Tout le reste attend. C'est ce filtre qui fait passer 28 domaines fonctionnels
à 8.

### Périmètre du MVP

| # | Module | Contenu | Statut |
|---|---|---|---|
| 1 | **Socle** | Module `Mole`, routage par domaine, design system, médias sur S3, authentification, rôles | Inclus |
| 2 | **CMS** | Administration complète : CRUD, éditeur, médiathèque, workflow de publication et de vérification | Inclus |
| 3 | **Territoire** | Département → arrondissement → commune → section, pages dynamiques | Inclus |
| 4 | **Histoire** | Timeline, périodes, événements, personnages, sources | Inclus |
| 5 | **Sites** | Sites historiques et patrimoniaux, catégories, fiches, carte | Inclus |
| 6 | **Annuaire** | Hôtels, restaurants, activités — fiches + contact WhatsApp direct | Inclus |
| 7 | **Contenu** | Blog/actualités, événements, galeries | Inclus |
| 8 | **Découverte** | Accueil, recherche globale, carte interactive, SEO complet | Inclus |
| — | Demande de réservation | | **Phase 4** |
| — | Espace partenaire | | **Phase 5** |
| — | Avis, multilingue, paiement, IA | | **Phase 6** |

### Condition de lancement — contenu, pas code

Le site **ne doit pas être mis en ligne** avant d'atteindre ce socle éditorial.
Un lancement en dessous de ce seuil grille l'effet de première impression, qui ne
se rejoue pas :

| Élément | Minimum |
|---|---|
| Sections communales documentées | 5 |
| Sites historiques avec photos | 10 |
| Établissements référencés | 15 |
| Articles publiés | 10 |
| Photographies de qualité | 100 |
| Pages institutionnelles | 4 (à propos, contact, mentions, opportunités) |

> **C'est le vrai jalon du projet.** Le code du MVP est atteignable en 8 à 10
> semaines. Le contenu peut prendre plus longtemps — et c'est pour cela que le
> CMS est livré en deuxième position, avant toute page publique riche : la
> production éditoriale démarre pendant que le développement continue.

---

## 2. Arborescence complète des pages

### Public

```
/                                    Accueil
├── /histoire                        Timeline interactive
│   ├── /histoire/periodes/{slug}
│   ├── /histoire/personnages/{slug}
│   └── /histoire/evenements/{slug}
├── /territoire                      Vue d'ensemble + carte
│   ├── /territoire/{commune}
│   └── /territoire/{commune}/{section}
├── /sites-historiques               Liste + filtres
│   ├── /sites-historiques/categorie/{slug}
│   └── /sites-historiques/{slug}
├── /centre-ville                    Section dédiée
│   └── /centre-ville/{slug}
├── /explorer                        Expériences
│   ├── /explorer/categorie/{slug}
│   └── /explorer/{slug}
├── /hotels                 /hotels/{slug}
├── /restaurants            /restaurants/{slug}
├── /evenements             /evenements/{slug}
├── /blog                   /blog/{slug}
│   ├── /blog/categorie/{slug}
│   └── /blog/tag/{slug}
├── /galerie                /galerie/{categorie}
├── /carte                           Carte plein écran, toutes couches
├── /recherche?q=
├── /partenaires
├── /contact  /a-propos  /mentions-legales  /opportunites
└── /r/{reference}                   Réponse partenaire à une demande [phase 4]

/sitemap.xml   /robots.txt   /feed.xml
```

### Administration (`/admin`)

```
/admin
├── /                                Tableau de bord
├── /territoire/{departements|arrondissements|communes|sections|localites}
├── /histoire/{periodes|evenements|personnages}
├── /sites            /sites/categories
├── /hotels           /restaurants        /activites
├── /evenements
├── /articles         /articles/categories    /articles/tags
├── /pages
├── /medias                          Médiathèque, dossiers, téléversement
├── /galeries
├── /demandes                        Demandes de réservation [phase 4]
├── /soumissions                     Contributions du public
├── /partenaires
├── /utilisateurs     /roles
├── /redirections
├── /audit
└── /parametres
```

### Structure de la page d'accueil

Reprend le point 2A du cahier des charges, ordonné par valeur décroissante pour
le visiteur :

1. **Hero** — image ou vidéo plein écran, slogan, accroche, 3 appels à l'action
   principaux (Découvrir · Que visiter · Où séjourner)
2. **Pourquoi visiter Môle-Saint-Nicolas** — 4 arguments illustrés
3. **L'histoire en bref** — 3 jalons + lien vers la timeline
4. **Explorer le territoire** — carte + accès aux sections communales
5. **Sites incontournables** — 6 fiches mises en avant
6. **Le centre-ville** — bloc immersif
7. **Où séjourner** — 4 hôtels
8. **Où manger** — 4 restaurants
9. **Activités et expériences** — 6 fiches
10. **Prochains événements** — 3 événements
11. **Dernières actualités** — 3 articles
12. **Galerie** — mosaïque + lien
13. **Appel à l'action final** — préparer sa visite / contact

**Contrainte de performance :** seuls le hero et le bloc 2 sont dans le HTML
initial critique. Les blocs 5 et suivants chargent leurs images en différé.
Budget de la page : **< 500 Ko** transférés.

---

## 3. Roadmap par phases

Les durées sont exprimées en semaines de développement, hors production
éditoriale, et supposent un développeur à temps plein.

### Phase 1 — Fondations · 2 semaines

- Module `Mole` : autoload PSR-4, `MoleServiceProvider`, routage par domaine
- Configuration `config/mole.php`, variables d'environnement
- Design system Tailwind : jetons de couleur, typographie, échelle d'espacement,
  composants de base (bouton, carte, badge, fil d'Ariane)
- Layouts Blade public et administration
- Authentification `mole_admin` + rôles spatie + `Policy`
- `MediaService` + stockage S3 + variantes AVIF/WebP en file d'attente
- Traits transversaux : `HasSeo`, `HasSlug`, `HasStatus`, `HasVerification`,
  `Auditable`, `Searchable`
- Migrations : `mole_users`, `mole_media`, `mole_media_folders`,
  `mole_mediables`, `mole_audit_logs`, `mole_redirects`, `mole_search_index`
- Squelette d'accueil (structure sans contenu riche)
- CI : Pint, PHPUnit, `composer audit`, Lighthouse

**Livrable vérifiable :** `molesaintnicolas.com` répond, `/admin` authentifie,
une image téléversée est servie depuis le CDN en quatre variantes, et
`govibeht.com` n'a subi aucune régression.

### Phase 2 — CMS · 2 semaines

- Tableau de bord : compteurs, activité récente
- Générateur de CRUD administrable (composants Livewire réutilisables :
  tableau, filtres, formulaire, actions groupées)
- Éditeur de contenu riche TipTap → JSON, assainissement serveur
- Médiathèque : téléversement multiple, dossiers, recherche, alt et crédit
  obligatoires, sélecteur réutilisable
- Workflow `brouillon → en revue → publié` + axe de vérification
- Gestion des utilisateurs, rôles, permissions
- Journal d'audit consultable
- Sauvegarde automatique des brouillons, téléversement reprenable

**Livrable vérifiable :** un éditeur non technique crée, illustre et publie un
article depuis un téléphone, sur une connexion instable, sans perdre sa saisie.

> **À partir d'ici, la production éditoriale démarre en parallèle.** C'est
> l'objectif de la séquence.

### Phase 3 — Territoire et histoire · 2 semaines

- Migrations de la hiérarchie territoriale
- CRUD des cinq niveaux avec import GeoJSON
- Pages publiques : `/territoire`, commune, section communale
- Migrations et CRUD histoire : périodes, événements, personnages, sources
- Timeline interactive `/histoire`
- Sites historiques : catégories, CRUD, `/sites-historiques`, fiche détaillée
- Carte Leaflet réutilisable (couches : sections, sites)
- Sitemap et JSON-LD pour ces types

**Livrable vérifiable :** créer une section communale depuis le CMS produit une
page publique complète, cartographiée, indexée, sans intervention sur le code.
C'est l'exigence explicite du point 4 du cahier des charges.

### Phase 4 — Tourisme et mise en relation · 2 semaines

- Hôtels, restaurants, activités : migrations, CRUD, listes filtrées, fiches
- Centre-ville : points d'intérêt et parcours
- Contact WhatsApp direct (lien `wa.me` pré-rempli)
- Demandes de réservation : formulaire polymorphe, notification WhatsApp et
  e-mail, page de réponse partenaire signée, relances, expiration
- Suivi des demandes dans l'administration
- Limitation de débit et protection anti-abus

**Livrable vérifiable :** une demande envoyée depuis une fiche hôtel arrive sur
le WhatsApp de l'établissement, qui peut la confirmer en un clic sans compte.

### Phase 5 — Contenu et découverte · 1,5 semaine

- Blog/actualités : CRUD, catégories, tags, listes, fiche, articles liés
- Événements : CRUD, calendrier, récurrence, fiche
- Galeries : CRUD, mosaïque, visionneuse, catégories
- Recherche globale groupée par catégorie
- Carte interactive plein écran, toutes couches
- Accueil complet
- Flux RSS
- Soumissions de contenu par le public + modération

**Livrable vérifiable :** rechercher « Marouge » ou « fort » remonte des
résultats groupés par catégorie, en moins de 300 ms.

### Phase 6 — Finition et mise en production · 1,5 semaine

- Audit SEO complet : JSON-LD sur tous les types, sitemap sectionné,
  Open Graph, canoniques
- Optimisation des performances jusqu'au respect du budget
- Accessibilité : contrastes, navigation clavier, ARIA, alternatives textuelles
- Pages d'erreur 404 et 500 soignées et utiles
- Analytics
- Sauvegardes automatisées **et restauration testée**
- Documentation d'exploitation et guide du CMS
- `/review` et `/security-review` conformément à `CLAUDE.md` §3.1
- Tests de charge sur les pages publiques
- Mise en production

**Total MVP : environ 11 semaines de développement.**

### Au-delà du MVP

| Phase | Contenu | Condition de déclenchement |
|---|---|---|
| **7** | Espace partenaire autonome | ≥ 10 partenaires actifs demandeurs |
| **8** | Multilingue (Kreyòl, anglais) | Contenu français stabilisé |
| **9** | Avis utilisateurs modérés | Trafic mensuel significatif |
| **10** | Réservation transactionnelle + paiement | Seuils de [`04-booking.md §6`](04-booking.md#6-chemin-vers-la-phase-6) atteints |
| **11** | Meilisearch, recommandations, place de marché | Volume de contenu et de trafic |

---

## 4. Plan de développement module par module

Conformément à la règle 26 du cahier des charges et au workflow obligatoire de
`CLAUDE.md` §2, chaque module suit **exactement** cette séquence, et un seul
module est en cours à la fois :

```
1. Rappel de l'objectif et du périmètre exact du module
2. Liste des fichiers créés ou modifiés, avant écriture
3. Migrations et modèles
4. Services et logique métier
5. Contrôleurs et validation (FormRequest)
6. Vues et composants
7. Tests — unitaires (services) + fonctionnels (routes)
8. Vérification : Pint, PHPUnit, budget de performance
9. Instructions de test manuel pour Roosevelt
10. Rapport de complétion (CLAUDE.md §6)
11. ARRÊT — attente de validation avant le module suivant
```

### Rapport de complétion — modèle imposé

Conformément à `CLAUDE.md` §6, chaque module livré est accompagné de :

1. Résumé de ce qui a été implémenté
2. Résultats de `/review` et `/security-review`
3. Couverture de tests obtenue
4. Risques restants et dette technique identifiée
5. Recommandation : prêt pour production / nécessite itération

### Exigences de test par module

Reprises de `CLAUDE.md` §4, adaptées au contexte non financier de cette
plateforme :

| Type | Portée |
|---|---|
| Unitaires | Tout service métier : `MediaService`, `SearchService`, `SeoService`, `BookingRequestService`, `ContentStatusService` |
| Fonctionnels | Chaque route publique et chaque route d'administration |
| Autorisation | Chaque rôle contre chaque route protégée — un `EDITOR` ne publie pas, un `PARTNER` ne voit que ses fiches |
| Non-régression | Les routes publiques existantes de `govibeht.com` répondent toujours |
| Isolation | Aucun import d'un espace de noms `App\` dans `Modules/Mole/` — test automatisé |
| Performance | Nombre de requêtes SQL par page publique < 20 |
| Cas limites | Slug dupliqué, commune inexistante, contenu non publié accessible par URL directe, image corrompue, double soumission de demande |
| Sécurité | XSS dans le contenu soumis, CSRF, limitation de débit, téléversement de fichier malveillant |

Le seuil de couverture de 80 % défini dans `CLAUDE.md` §3.2 s'applique.

---

## 5. Ce qui doit être décidé avant la première ligne de code

Rappel des décisions listées dans le [`README`](README.md#décisions-en-attente-darbitrage).
**Les trois premières sont bloquantes** : sans elles, le module ne peut pas être
initialisé correctement.

| # | Décision | Recommandation | Impact si reporté |
|---|---|---|---|
| **D1** | Monorepo ou dépôt séparé | Monorepo, module `Mole` | Bloque la phase 1 |
| **D2** | Réservation transactionnelle en V1 | Non — demande de réservation | Change la phase 4 et le schéma |
| **D3** | Périmètre : commune ou arrondissement | Modèle générique, contenu sur la commune | Change le contenu, pas la structure |
| D4 | Budget infrastructure | ~15–25 $/mois | Bloque le stockage objet |
| D5 | Responsable du contenu | À nommer | **Détermine la date de lancement réelle** |
| D6 | Multilingue en V1 | Non, schéma préparé | Coûteux si tranché plus tard |
| D7 | Avis en V1 | Non | Faible impact |

### Question ouverte pour Roosevelt

Une seule, mais elle conditionne tout le reste :

> **Qui produit le contenu, et selon quel calendrier ?**

Le code du MVP est prévisible : environ onze semaines. Le contenu ne l'est pas,
et c'est lui qui détermine la date à laquelle la plateforme devient réellement
utile. Si la réponse est « personne pour l'instant », alors la priorité n'est
pas de développer plus vite — c'est de résoudre cette question d'abord.

---

## Prochaine étape

Ce dossier constitue la réponse complète à la « PREMIÈRE TÂCHE » du cahier des
charges. **Aucun code applicatif n'a été écrit**, conformément à l'instruction
« NE CODE PAS ENCORE ».

Après validation des décisions D1 à D3, le développement démarre par la
**phase 1 — Fondations**, module par module, avec arrêt et rapport de complétion
après chacun.
