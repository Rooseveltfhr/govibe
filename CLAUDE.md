# CLAUDE.md — TAGTOA (GOVIBE Innovation Hub)

> Instructions permanentes pour Claude Code sur ce dépôt. Ce fichier est lu automatiquement à chaque session.

## 1. Contexte du projet

**TAGTOA** est une plateforme SaaS NFC/QR multi-tenant développée en **Laravel/PHP + MySQL**, architecture **PWA offline-first**.

**Modules actifs :**
- `CONNECT` — identité, auth, gestion tenant
- `PAY` — paiements, intégration MAGOCASH (ledger double-entrée)
- `MENU` — catalogue produits/services par tenant
- `LINKS` — liens NFC/QR dynamiques
- `LOYALTY` — points, récompenses, cashback
- `EVENT` — billetterie et check-in événementiel
- `POS` — point de vente hors-ligne

**Contraintes non négociables :**
- Isolation stricte multi-tenant (aucune fuite de données entre tenants)
- Ledger MAGOCASH : double-entrée obligatoire, aucune écriture directe sur les soldes
- Conformité BRH Circulaire 121 sur toute logique financière
- Offline-first : toute feature PAY/LOYALTY/POS doit gérer la synchronisation et la résolution de conflits

---

## 2. Workflow obligatoire (ne jamais sauter d'étape)

1. Comprendre l'objectif exact du module demandé
2. Poser des questions si l'information est incomplète
3. Proposer l'architecture technique avant de coder
4. Identifier les risques (sécurité, scalabilité, régression)
5. Attendre validation de Roosevelt avant d'implémenter
6. Développer **un seul module à la fois**
7. Tester (voir section 4)
8. Produire un rapport de complétion (voir section 6)

Ne jamais construire plusieurs modules simultanément. Ne jamais livrer du code "placeholder" quand une implémentation réelle est possible.

---

## 3. Détection automatique de bugs et échecs (QA)

### 3.1 Revue de code obligatoire avant merge
```bash
# Revue générale — correction, sécurité, performance, style
/review

# Revue de sécurité obligatoire sur PAY, LOYALTY, EVENT (données financières/sensibles)
/security-review
```
**Règle :** aucun merge sur `PAY`, `LOYALTY` ou toute route touchant MAGOCASH sans `/security-review` passé sans alerte critique.

### 3.2 Hooks recommandés (`.claude/hooks/`)
- **`pre-commit`** : bloque le commit si la couverture de tests descend sous **80%**
- **`post-dependency-change`** : lance `composer audit` / `npm audit` automatiquement quand `composer.json` ou `package.json` change ; bloque si vulnérabilité critique détectée
- **`pre-merge-ledger`** : refuse toute modification du module `PAY` qui écrit directement sur une table de solde au lieu de passer par le service de ledger double-entrée

### 3.3 Revue automatisée en CI/CD (GitHub Actions)
```bash
claude -p "Analyse ce diff. Signale les bugs, failles de sécurité, et violations des conventions TAGTOA (isolation tenant, ledger double-entrée)." 
```
À exécuter sur chaque pull request. Refuser le merge automatiquement si Claude signale une violation d'isolation tenant ou une écriture non sécurisée sur le ledger.

### 3.4 Debug à partir des erreurs production
Quand une erreur ou un stack trace est fourni : remonter la trace, localiser la cause racine, proposer un correctif **et** un test de régression qui aurait dû l'attraper.

---

## 4. Tests — exigences minimales

- Tests unitaires sur tout service métier (surtout `LedgerService`, `TenantScope`, `SyncResolver`)
- Tests d'intégration sur chaque endpoint API par module
- Tests offline/sync : simuler perte de connexion + résolution de conflit avant validation
- Tests de charge avant toute mise en production d'un module `PAY`/`POS`
- Vérifier les cas limites : tenant inexistant, solde négatif, double soumission, NFC lu deux fois

---

## 5. Standards de code

- SOLID, architecture modulaire, composants réutilisables
- Aucune régression : toute modification doit être testée contre les modules existants avant merge
- Code documenté, prêt pour la production — jamais de prototype présenté comme final
- Optimisation base de données obligatoire sur toute requête touchant plusieurs tenants

---

## 6. Rapport de complétion (fin de chaque module)

Chaque module livré doit inclure :
1. Résumé de ce qui a été implémenté
2. Résultats de `/review` et `/security-review`
3. Couverture de tests obtenue
4. Risques restants ou dette technique identifiée
5. Recommandation : prêt pour production / nécessite itération

---

## 7. Notes stratégiques

TAGTOA est le véhicule commercial principal de l'écosystème GOVIBE. Toute décision technique doit être évaluée avec une perspective de **scalabilité à 5–10 ans** (des centaines à des millions d'utilisateurs), pas seulement pour le besoin immédiat.
