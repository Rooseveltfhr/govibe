# ADR-001 — Monolithe modulaire Laravel (pas de micro-services au départ)

**Statut** : accepté (Phase 0) · **Date** : 2026-07-20

## Contexte

GOVIBE AI doit servir à terme des millions de requêtes API/mois, mais démarre avec
une petite équipe et un budget d'infrastructure limité (réalité haïtienne : coût
d'hébergement et d'exploitation = contrainte de premier ordre).

## Décision

Construire un **monolithe modulaire Laravel 13** (`nwidart/laravel-modules`, un
module par domaine : Core, AIProvider, AIRouter, AIServices, Agents, Billing,
ApiPlatform, Usage, Analytics, AdminPanel, UserPanel, HaitiPack, Marketplace),
avec une règle de dépendance stricte descendante et une communication remontante
par events uniquement.

## Conséquences

- Une seule base de code, une seule CI, un seul déploiement → vélocité maximale.
- Les frontières de modules rendent l'extraction future mécanique. Candidat n°1 :
  le chemin chaud `ApiPlatform + AIRouter + AIProvider` (« Gateway »), stateless.
- La logique métier pure (négociation de langue, scoring du routeur, ledger…)
  vit dans des classes sans dépendance framework, testables en unitaire strict.
- Docker sépare déjà les rôles (web / worker / scheduler) au niveau des processus,
  pas du code — le scaling horizontal reste possible sans micro-services.

## Alternatives rejetées

- **Micro-services d'emblée** : coût opérationnel (réseau, observabilité, n
  déploiements) injustifié avant un trafic soutenu ; ralentit une petite équipe.
- **Serverless** : latence de démarrage et lock-in cloud incompatibles avec le
  streaming SSE et l'hébergement économique visé.
