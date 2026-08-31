# 01 — Analyse stratégique

Répond aux points 1, 2, 3, 12, 13 et 14 de la première tâche.

---

## 1. Analyse stratégique du projet

### 1.1 Ce que le projet est réellement

Le cahier des charges décrit trois produits distincts sous un seul nom :

1. **Un portail de connaissance territoriale** — histoire, sections communales,
   patrimoine. Contenu éditorial, faible fréquence de mise à jour, forte valeur
   SEO et institutionnelle, monétisation indirecte.
2. **Un annuaire d'établissements** — hôtels, restaurants, guides. Données
   structurées, mise à jour continue, monétisation par abonnement/listing.
3. **Une place de marché transactionnelle** — réservation, disponibilités,
   paiement. Complexité technique élevée, monétisation par commission,
   **dépend entièrement de la densité d'offre**.

Ces trois produits n'ont ni le même rythme de construction, ni le même risque,
ni la même condition de succès. Les traiter comme un seul chantier est la
première cause d'échec de ce type de plateforme.

**L'ordre est imposé par la dépendance, pas par la préférence :** le portail
crée l'audience → l'audience rend l'annuaire attractif pour les établissements →
la densité d'établissements rend la place de marché viable. Inverser l'ordre
produit une plateforme de réservation sans réservations.

### 1.2 Le facteur limitant réel

Le code n'est pas le goulot d'étranglement. Une plateforme éditoriale + annuaire
avec CMS complet représente environ 6 à 8 semaines de développement. Ce qui prend
du temps, et ce qui détermine la valeur du produit :

| Ressource | Difficulté | Sans elle |
|---|---|---|
| Photographies de qualité (sites, plages, centre-ville, sections) | Élevée — exige un déplacement terrain | Le site est visuellement vide, donc sans crédibilité touristique |
| Histoire vérifiée et sourcée | Élevée — exige recherche et validation | Risque de diffuser des erreurs, perte de légitimité |
| Fiches établissements (accord, photos, tarifs, contacts) | Moyenne — exige du démarchage | L'annuaire est incomplet, donc inutile |
| Données territoriales officielles (sections, localités) | Moyenne — sources CNIGS/IHSI | Structure vide, promesse non tenue |

**Conséquence sur le plan de développement :** le CMS doit être livré **avant**
les pages publiques riches, pour que la production de contenu et le
développement avancent en parallèle plutôt qu'en série. C'est le principe
directeur de la roadmap.

### 1.3 Positionnement concurrentiel

Il n'existe pas de référence numérique établie sur Môle-Saint-Nicolas. Le
paysage actuel est fait de pages Facebook dispersées, d'articles de presse
ponctuels et de mentions dans des portails touristiques nationaux génériques.

Cela signifie deux choses :

- **L'opportunité SEO est réelle et prenable.** Sur des requêtes comme
  « Môle-Saint-Nicolas histoire », « que visiter Môle-Saint-Nicolas », « hôtel
  Môle-Saint-Nicolas », la concurrence est faible. Un contenu structuré,
  correctement balisé et régulièrement enrichi peut occuper la première position
  en quelques mois. **C'est le principal actif à construire.**
- **Il n'y a pas de demande captée à récupérer.** Le trafic n'existe pas encore,
  il faut le créer. D'où l'importance de traiter le SEO et le contenu comme le
  produit lui-même, pas comme une couche d'optimisation ajoutée à la fin.

### 1.4 Ce que je conteste dans le cahier des charges

Conformément à la règle 26 (« Challenge mes choix lorsqu'une meilleure solution
existe ») :

**a. La réservation en ligne complète est prématurée.**
Le cahier demande recherche par dates, disponibilités, filtres par équipements,
réservation. Cela suppose que les hôtels tiennent un inventaire de chambres à
jour dans le système. Dans les faits, un petit hôtel de province haïtienne gère
ses réservations par téléphone et WhatsApp, avec un cahier papier. Construire un
moteur de disponibilités qu'aucun établissement n'alimentera produit un
calendrier faux — pire qu'une absence de calendrier, parce qu'il détruit la
confiance dès la première réservation refusée.
→ **Proposition : demande de réservation avec confirmation humaine.** Voir
[`04-booking.md`](04-booking.md).

**b. Les avis utilisateurs sont un piège en V1.**
Un système d'avis sans volume de trafic ne produit pas de signal : il produit du
spam, des faux avis d'établissements concurrents et une charge de modération
quotidienne. Il crée aussi une exposition juridique (diffamation) sans
contrepartie.
→ **Proposition : reporter en phase 6.** Prévoir la table dès maintenant.

**c. « Multilingue » ne doit pas être construit en V1, mais ne doit pas être
oublié non plus.**
Ajouter Kreyòl et anglais triple immédiatement la charge de production
éditoriale — pour un contenu qui n'existe pas encore et qui va beaucoup changer.
En revanche, ajouter la dimension linguistique après coup impose une migration
douloureuse de toutes les tables de contenu.
→ **Proposition : colonne `locale` et contrainte d'unicité `(slug, locale)` dès
la première migration, interface publique et CMS en français uniquement.** Le
coût aujourd'hui est de quelques heures ; le coût plus tard est de plusieurs
semaines.

**d. La cible « investisseurs » et « chercheurs » ne justifie pas de
fonctionnalités dédiées.**
Ce sont des visiteurs à faible volume qui consomment le même contenu que les
autres. Leur dédier des sections en V1 disperse l'effort. Une page « Opportunités
économiques » bien écrite suffit.

**e. Attention au design « fortement visuel » sur un réseau haïtien.**
Le cahier demande de grandes images, des animations et des micro-interactions,
puis « excellent score Core Web Vitals ». Ces deux exigences sont en tension sur
une connexion mobile 3G facturée à la donnée. L'arbitrage doit être explicite.
→ **Proposition : budget de performance contraignant** — page d'accueil
< 500 Ko en poids total transféré, LCP < 2,5 s sur 3G simulée. Ce budget est
vérifié en CI et bloque la mise en production. Les images font des variantes
AVIF/WebP responsives, les animations sont en CSS pur et respectent
`prefers-reduced-motion`.

---

## 2. Proposition de valeur

### 2.1 Formulation

> **molesaintnicolas.com est la mémoire et la vitrine numérique de
> Môle-Saint-Nicolas : le seul endroit où son histoire, son territoire et ses
> acteurs économiques sont documentés, structurés et accessibles.**

### 2.2 Par audience

| Audience | Problème actuel | Ce que la plateforme apporte |
|---|---|---|
| **Touriste / voyageur** | Aucune information fiable, ne sait pas quoi voir ni où dormir | Itinéraires, sites documentés, hébergements avec contact direct |
| **Diaspora** | Lien distendu, pas d'image récente du territoire | Contenu visuel et historique, actualités, fierté d'origine partageable |
| **Habitant** | Pas de canal de valorisation locale | Visibilité pour son commerce, information locale, représentation de sa section |
| **Chercheur / étudiant** | Sources dispersées, souvent hors ligne | Corpus historique et territorial structuré et citable |
| **Investisseur / entreprise** | Pas de données sur le territoire | Portrait économique, opportunités, contacts institutionnels |
| **Établissement local** | Invisible en ligne, dépend de Facebook | Fiche professionnelle, référencement, demandes de réservation |

### 2.3 Ce qui rend la position défendable

Une plateforme touristique générique peut être copiée. Ce qui ne se copie pas
facilement :

- **Le corpus territorial** — la description structurée de chaque section
  communale, construite avec les acteurs locaux, est un actif cumulatif.
- **L'autorité SEO** — acquise lentement, difficile à déloger une fois établie.
- **La relation avec les établissements** — le premier acteur à agréger l'offre
  locale devient l'interlocuteur par défaut.
- **La légitimité institutionnelle** — un partenariat avec la mairie ou une
  association patrimoniale rend le site officiel de fait.

---

## 3. Personas utilisateurs

### P1 — Nadège, 34 ans, diaspora (Montréal)

Née au Môle, partie à 12 ans. Consulte depuis son téléphone, souvent le soir.
Cherche des images du pays, de l'histoire à raconter à ses enfants, et prépare un
voyage familial pour dans deux ans.
**Attentes :** photos, récits, partage social facile.
**Ce qui la fait partir :** site lent, contenu daté, aucune image.
**Comportement :** mobile 95 %, sessions longues, forte propension au partage.

### P2 — Marc, 41 ans, touriste indépendant (Port-au-Prince)

Weekend prolongé. A entendu parler des plages du Nord-Ouest. Veut savoir
comment y aller, où dormir, combien ça coûte, et si c'est faisable.
**Attentes :** informations pratiques concrètes — accès routier, prix, contact
direct par WhatsApp.
**Ce qui le fait partir :** pas de prix, pas de numéro, formulaire de contact
sans réponse.
**Comportement :** mobile, session courte et décisionnelle, va appeler.

### P3 — Frantz, 52 ans, propriétaire d'un hôtel de 8 chambres

Gère par WhatsApp et un cahier. Pas de site web, une page Facebook peu suivie.
Veut plus de clients mais n'a ni le temps ni les compétences pour gérer un back-
office complexe.
**Attentes :** être trouvé, recevoir des demandes sur WhatsApp, modifier ses
photos et ses tarifs sans formation.
**Ce qui le fait partir :** interface d'administration compliquée, obligation de
tenir un calendrier à jour.
**Conséquence produit directe :** l'espace partenaire doit être d'une simplicité
extrême, et la réception des demandes doit passer par WhatsApp.

### P4 — Rose-Laure, 23 ans, étudiante en histoire (Cap-Haïtien)

Prépare un mémoire sur le Nord-Ouest. Cherche des sources structurées et
citables.
**Attentes :** dates, événements, personnages, sources indiquées, dates de mise
à jour visibles.
**Ce qui la fait partir :** contenu non sourcé, impossible à citer.
**Conséquence produit directe :** justifie le système de statut de vérification
du contenu et l'affichage des sources.

### P5 — Roosevelt / l'administrateur éditorial

Publie, corrige, ajoute des établissements, modère les soumissions. Travaille
souvent depuis un mobile, parfois avec une connexion instable.
**Attentes :** CMS rapide, tolérant aux coupures, sans perte de saisie.
**Conséquence produit directe :** sauvegarde automatique des brouillons,
téléversement d'images résilient, CMS utilisable sur mobile.

---

## 4. Risques

### 4.1 Risques business

| Risque | Prob. | Impact | Mitigation |
|---|---|---|---|
| **Pénurie de contenu** — le site reste vide après le lancement technique | Élevée | Critique | Nommer un responsable contenu (D5) ; ne pas lancer sans un socle minimum : 10 sites, 5 sections, 15 établissements, 10 articles |
| **Aucun établissement ne s'inscrit** | Moyenne | Élevé | Créer les fiches nous-mêmes à partir d'informations publiques et proposer la reprise gratuite ; l'inscription n'est pas un préalable à la visibilité |
| **Trafic nul les 6 premiers mois** | Élevée | Moyen | Attendu et normal en SEO ; compenser par diffusion Facebook/WhatsApp diaspora, qui est le canal réel |
| **Monétisation trop précoce** — faire payer avant d'apporter du trafic | Moyenne | Élevé | Gratuité totale la première année, mesurer le trafic apporté, monétiser sur preuve |
| **Dépendance à une seule personne** | Élevée | Élevé | Documentation, CMS accessible à plusieurs, sauvegardes externalisées |
| **Contestation d'un contenu historique ou territorial** | Moyenne | Moyen | Statut de vérification, sources affichées, procédure de correction visible |

### 4.2 Risques techniques

| Risque | Prob. | Impact | Mitigation |
|---|---|---|---|
| **Perte des médias** — `FILESYSTEM_DISK=local`, aucune sauvegarde constatée dans le dépôt | Élevée | **Critique** | Stockage objet S3-compatible dès la V1 + sauvegarde base quotidienne hors serveur. **Point non négociable.** |
| **Serveur unique** — le VPS actuel est un point de défaillance unique | Moyenne | Élevé | Sauvegardes restaurables et testées ; CDN Cloudflare qui absorbe le trafic statique |
| **Performance sur réseau mobile haïtien** | Élevée | Élevé | Budget de performance en CI, images AVIF/WebP responsives, chargement différé |
| **Recherche globale sans moteur dédié** (pas de Redis ni Meilisearch) | Moyenne | Moyen | MySQL FULLTEXT en V1, suffisant jusqu'à ~50 000 documents ; Meilisearch en phase 6 |
| **Couplage avec l'ERP GOVIBE existant** — une régression sur le site touristique casse govibeht.com | Moyenne | Élevé | Isolation stricte par module, tables préfixées `mole_`, tests de non-régression sur les routes existantes |
| **Fuite de données entre le site public et l'ERP** | Faible | Critique | Gardes d'authentification séparées (`mole_admin` vs `erp`), aucun partage de session |
| **Dérive de périmètre** — le cahier décrit 28 domaines | **Élevée** | Élevé | MVP figé et contractualisé, un module à la fois (règle 26) |

### 4.3 Le risque principal, nommé explicitement

> **Le risque numéro un de ce projet n'est pas technique. C'est de livrer une
> plateforme techniquement excellente et vide.**

Toutes les décisions de la roadmap découlent de ce constat : livrer le CMS tôt,
réduire le périmètre fonctionnel, et consacrer l'effort disponible à
l'acquisition de contenu réel.

---

## 5. Le capital narratif — à vérifier avant publication

Môle-Saint-Nicolas dispose d'un patrimoine historique d'importance nationale et
internationale, ce qui constitue le principal levier d'audience du projet.
**Aucun élément ci-dessous ne doit être publié sans vérification auprès de
sources primaires ou d'un historien local.** Ce sont des pistes de recherche, pas
du contenu validé.

| Piste | Statut | À faire |
|---|---|---|
| Site du premier débarquement de Christophe Colomb en Haïti (décembre 1492) | À vérifier | Sourcer avec le journal de bord et l'historiographie haïtienne |
| Position navale stratégique du « Môle », convoitée par les puissances européennes puis les États-Unis | À vérifier | Documenter les périodes et les sources |
| Tentative américaine d'obtenir une base navale au Môle (fin XIX<sup>e</sup>), et rôle de Frederick Douglass comme ministre des États-Unis en Haïti | À vérifier | Sources diplomatiques ; épisode à fort potentiel éditorial |
| Fortifications et patrimoine bâti | À vérifier | Inventaire terrain + photographies |
| Toponymie et histoire des sections communales | À documenter | Recherche locale, témoignages oraux |

Le système de statut de vérification demandé au point 27 du cahier des charges
répond exactement à ce besoin : `brouillon → à vérifier → vérifié`, avec sources
et date de vérification affichées publiquement. **C'est une bonne intuition du
cahier des charges, et elle est conservée telle quelle.**

---

## 6. Opportunités de monétisation

Classées par ordre de faisabilité réelle, pas par potentiel théorique.

### Horizon 1 — année 1 (revenus faibles à nuls, construction de l'actif)

| Levier | Modèle | Réaliste ? |
|---|---|---|
| **Listing gratuit** | 0 € | Oui — c'est l'investissement initial |
| **Partenariat institutionnel** (mairie, ministère du Tourisme, ONG, diaspora) | Subvention ou prestation | Oui — la voie la plus crédible pour financer la phase 1 |
| **Production de contenu pour tiers** (fiches, photos, vidéos d'établissements) | Prestation ponctuelle | Oui — monétise la compétence GOVIBE existante, sans dépendre du trafic |

### Horizon 2 — année 2 (le trafic devient monétisable)

| Levier | Modèle | Condition |
|---|---|---|
| **Listing premium** (mise en avant, galerie étendue, statistiques) | Abonnement mensuel | Preuve chiffrée de trafic apporté |
| **Publicité locale** (commerces, transporteurs, événements) | Encart mensuel | Audience locale mesurable |
| **Mise en avant événementielle** | Forfait par événement | Calendrier événementiel actif |

### Horizon 3 — année 3+ (transactionnel)

| Levier | Modèle | Condition |
|---|---|---|
| **Commission sur réservation** | 8–15 % | Nécessite paiement en ligne **et** des établissements qui tiennent leur inventaire |
| **Billetterie événementielle** | Commission ou forfait | Réutilisable depuis le module EVENT de TAGTOA |
| **Place de marché d'expériences** (guides, excursions) | Commission | Nécessite des prestataires structurés |

### Synergie avec l'écosystème GOVIBE

Le dépôt contient déjà des briques réutilisables — un module `EVENT` avec
billetterie et check-in NFC, un module `POS`, et les travaux `govibepay`. À terme,
un hôtel référencé sur molesaintnicolas.com est un client naturel pour TAGTOA
(menu, paiement, fidélité). **La plateforme touristique est un canal
d'acquisition pour le produit SaaS.** C'est un argument fort en faveur du
monorepo (décision D1).

> **Avertissement.** Toute intégration de paiement passera par le `LedgerService`
> à double entrée et devra respecter la Circulaire 121 de la BRH, conformément
> aux contraintes non négociables du fichier `CLAUDE.md`. Aucune écriture directe
> sur un solde. Ce point conditionne l'horizon 3 et ne doit pas être improvisé.

---

## 7. Fonctionnalités à exclure du MVP

Exclure n'est pas abandonner : c'est ordonner. Chaque ligne indique la phase de
réintroduction.

| Fonctionnalité | Pourquoi l'exclure | Réintroduction |
|---|---|---|
| **Paiement en ligne** (MonCash, NatCash, carte, PayPal) | Credentials indisponibles, conformité BRH, aucune offre transactionnelle à ce stade | Phase 6+ |
| **Moteur de disponibilités** (calendrier, inventaire chambres) | Aucun établissement ne l'alimentera ; un calendrier faux détruit la confiance | Phase 6 |
| **Avis et notes utilisateurs** | Spam et modération sans volume ; exposition juridique | Phase 6 |
| **Espace partenaire en autonomie** | Suppose des partenaires actifs ; l'admin saisit plus vite en V1 | Phase 5 |
| **Kreyòl et anglais** | Triple la charge éditoriale sur un contenu instable | Phase 6 (schéma prêt dès V1) |
| **Application mobile native** | La PWA couvre le besoin à moindre coût | Non planifié |
| **Recommandations par IA** | Nécessite du trafic et des données comportementales | Phase 6+ |
| **Vidéo auto-hébergée** | Coût de bande passante et d'encodage disproportionné | Intégration YouTube/Vimeo en V1 |
| **Notifications push** | Sans audience récurrente, aucun usage | Phase 5+ |
| **Recherche à facettes complexe** | MySQL FULLTEXT suffit au volume prévu | Phase 6 |
| **Analytics maison** | Umami ou Plausible, auto-hébergé ou géré | V1 via outil externe |

---

**Suite :** [`02-architecture-technique.md`](02-architecture-technique.md)
