# molesaintnicolas.com — Dossier d'architecture

> **Statut : PROPOSITION — en attente de validation de Roosevelt.**
> Aucun code applicatif n'a été écrit. Ce dossier répond à la « PREMIÈRE TÂCHE »
> du cahier des charges : analyse, architecture, MVP, roadmap. Le développement
> ne commence qu'après arbitrage des décisions listées ci-dessous.

## Documents

| Fichier | Contenu |
|---|---|
| [`01-strategie.md`](01-strategie.md) | Analyse stratégique, proposition de valeur, personas, monétisation, risques |
| [`02-architecture-technique.md`](02-architecture-technique.md) | Stack, architecture applicative, CMS, médias, recherche, SEO, i18n, déploiement |
| [`03-base-de-donnees.md`](03-base-de-donnees.md) | Schéma relationnel complet, modèles, relations |
| [`04-booking.md`](04-booking.md) | Architecture des réservations et préparation des paiements |
| [`05-roadmap.md`](05-roadmap.md) | MVP, arborescence des pages, phases, plan module par module |

## Résumé exécutif

**Le constat.** Le cahier des charges décrit 28 domaines fonctionnels. Construits
intégralement, c'est 12 à 18 mois de travail. Ce n'est pas le bon plan, et pas
parce que l'ambition est mauvaise — elle est juste. C'est parce que le facteur
limitant de ce projet n'est pas le code : **c'est le contenu et l'offre locale**.
Une plateforme de réservation sans hôtels connectés est une coquille vide ; un
portail patrimonial sans photographies ni histoire vérifiée n'a aucune valeur de
référence. Le code peut être écrit en quelques semaines. Photographier le Fort,
documenter les sections communales, obtenir l'accord des hôteliers : c'est ça, le
chemin critique.

**La recommandation.** Un MVP resserré sur ce qui crée immédiatement de la
valeur et de la crédibilité — **le territoire, l'histoire, les sites, et un
annuaire d'établissements avec mise en relation WhatsApp** — piloté par un CMS
complet dès le premier jour. La réservation transactionnelle (inventaire,
disponibilités, paiement) est **reportée**, mais l'architecture la prépare.

**Les trois décisions structurantes** soumises à validation :

1. **Monorepo Laravel plutôt que stack séparée.** Construire
   molesaintnicolas.com comme un module du dépôt `govibe` existant, servi par
   `Route::domain()`. Réutilise l'authentification, les rôles, le pipeline de
   déploiement et la compétence de l'équipe. *Alternative écartée :
   Next.js/headless — meilleure sur le papier, plus coûteuse en réalité.*
   → détail : [`02`](02-architecture-technique.md#1-décision-structurante--monorepo-laravel)

2. **Réservation = demande de réservation, pas moteur de disponibilité.**
   En V1, le visiteur envoie une demande, l'établissement confirme par
   WhatsApp/e-mail. Pas de calendrier d'inventaire, pas de paiement.
   *C'est le canal réel du marché haïtien, et ça évite de construire un OTA
   avant d'avoir de l'offre.*
   → détail : [`04`](04-booking.md)

3. **Stockage objet + CDN dès la V1.** Le disque local du VPS est
   disqualifiant pour un site à forte densité photographique, et sans
   sauvegarde. Cloudflare R2 ou Backblaze B2 derrière Cloudflare, ~5 $/mois.
   *C'est le seul poste d'infrastructure sur lequel il ne faut pas économiser.*
   → détail : [`02`](02-architecture-technique.md#8-stockage-des-médias)

## Décisions en attente d'arbitrage

Le développement démarre dès que ces points sont tranchés. Ils sont classés par
impact sur l'architecture — les trois premiers sont bloquants.

| # | Décision | Recommandation | Bloquant |
|---|---|---|---|
| D1 | Monorepo `govibe` ou dépôt séparé ? | Monorepo, module `Mole` | ✅ |
| D2 | Réservation transactionnelle en V1 ? | Non — demande de réservation | ✅ |
| D3 | Périmètre territorial : commune du Môle seule, ou arrondissement entier (4 communes) ? | Modèle générique, contenu initial sur la commune | ✅ |
| D4 | Budget infrastructure mensuel accepté ? | ~15–25 $/mois (stockage + CDN + e-mail transactionnel) | ⬜ |
| D5 | Qui produit le contenu (photos, histoire, fiches) ? | À nommer — c'est le chemin critique | ⬜ |
| D6 | Kreyòl et anglais : V1 ou plus tard ? | Plus tard, schéma préparé dès maintenant | ⬜ |
| D7 | Avis utilisateurs en V1 ? | Non — charge de modération sans volume | ⬜ |

## Ce que ce dossier ne fait pas

Conformément à la règle 27 du cahier des charges, **aucun fait historique,
statistique de population, nom de localité ou information officielle n'est
inventé dans ce dossier**. Les exemples territoriaux (« Marouge ») sont repris
tels quels du cahier des charges et servent d'illustration de structure, pas
d'affirmation. Les pistes de contenu historique évoquées en
[`01`](01-strategie.md#5-le-capital-narratif--à-vérifier-avant-publication) sont
signalées comme **à vérifier auprès de sources primaires** avant toute
publication.
