# ADR-002 — Frontière avec ElevenLabs : la voix chez eux, le jugement chez nous

**Statut** : accepté · **Date** : 2026-09-06 · **Décidé par** : Roosevelt Forestal (option C)

## Contexte

ElevenLabs propose deux choses très différentes sous une même marque :

1. **La voix** — synthèse (texte → audio) et transcription (audio → texte). Ce sont
   des fonctions sans mémoire : on envoie, on reçoit.
2. **Les agents conversationnels (ConvAI)** — un agent *hébergé chez eux*, qui porte
   la consigne, la mémoire, le LLM, le tour de parole et les outils. On lui parle,
   il répond ; nous ne voyons que les deux extrémités.

La demande initiale était que la plateforme s'appuie sur ElevenLabs comme fournisseur
opérationnel, « il donne tout sauf les interfaces ». Prise au pied de la lettre, cette
phrase désigne l'option 2 — et déplace chez un tiers exactement ce qui distingue
LOUVIA d'un formulaire de contact :

- la **politique de confirmation** (une commande est confirmée avant d'être posée) ;
- la **mémoire** de conversation et sa durée de vie ;
- le **routage multi-fournisseur** (coût, latence, qualité par langue — le créole
  n'est pas servi également par tous les modèles) ;
- les **règles métier haïtiennes** (gourde ↔ dollar haïtien, accompagnements,
  adresse avec repère) ;
- la **maîtrise du coût** : un agent hébergé se facture au temps de parole, pas au
  jeton, et le routeur ne peut plus arbitrer.

Trois options ont été posées :

| | Cerveau | Gain | Perte |
|---|---|---|---|
| A | LOUVIA | toutes nos règles, le routeur, le coût maîtrisé | il faut bâtir l'appel téléphonique |
| B | ElevenLabs ConvAI | téléphone plus vite, moins de code | confirmation, mémoire, choix du modèle, coût |
| C | mixte | les deux | plus de code à tenir |

## Décision

**Option C.** La frontière passe entre *parler* et *juger* :

**Chez ElevenLabs** — tout ce qui touche le son :
- synthèse vocale (`/v1/text-to-speech/{voice_id}`) ;
- transcription (`/v1/speech-to-text`, Scribe) ;
- à terme, l'agent ConvAI hébergé **pour le canal téléphonique uniquement**, où la
  latence du tour de parole est le problème dominant.

**Chez LOUVIA** — tout ce qui engage l'entreprise :
- la définition de l'agent (consigne, connaissance métier) ;
- `ConfirmationPolicy` : agir / confirmer / passer à un humain ;
- la mémoire de conversation et son écrêtage ;
- le routeur multi-fournisseur pour le texte ;
- les règles monétaires et sectorielles ;
- toutes les interfaces, la commande, les intégrations.

**Règle de couture** : la définition d'agent chez nous est la **source unique**. Un
agent ConvAI n'en est qu'une *projection* : créé et mis à jour depuis elle, jamais
édité du côté ElevenLabs. Deux consignes qui divergent, c'est un agent qui dit deux
prix différents selon qu'on l'appelle ou qu'on lui écrit.

## Conséquences

- Le mode « Appel » du web est **déjà** l'option C : le micro passe par Scribe, notre
  runtime répond, ElevenLabs prononce. Rien à refaire.
- Le mode chat n'appelle pas la synthèse : on ne brûle pas du crédit voix pour un
  message écrit.
- Un agent ConvAI hébergé ne peut pas faire respecter `ConfirmationPolicy` de
  l'intérieur. Tant que ses outils ne sont pas branchés sur nos webhooks, un agent
  téléphonique reste **limité aux outils de lecture** — il renseigne, il ne commande
  pas. C'est une contrainte de sécurité, pas une étape de confort.
- Le coût se lit à deux endroits (jetons chez le fournisseur de texte, minutes chez
  ElevenLabs). Le module Usage devra additionner les deux avant toute facturation.
- Changer de fournisseur de voix reste mécanique : `SupportsSpeech` est un contrat,
  ElevenLabs en est une implémentation.

## Ce qui reste bloqué

Le schéma exact de `POST /v1/convai/agents/create` n'a pas pu être vérifié :
`elevenlabs.io` est bloqué depuis l'environnement de développement, et aucune clé
ElevenLabs n'est encore configurée. Écrire une charge utile imbriquée devinée serait
précisément l'implémentation factice que la spécification produit interdit (§36).

Pour débloquer, il faut **l'un des deux** :

1. le corps de requête documenté (copié depuis la page `agents/create`) ;
2. la clé dans le secret `LOUVIA_ELEVENLABS_KEY`, pour appeler l'API et lire sa
   réponse — y compris ses erreurs, qui décrivent les champs attendus.

D'ici là, le connecteur ElevenLabs livré couvre la voix (synthèse + transcription),
et lui seul.

## Alternatives rejetées

- **Option B pure** — tout chez ElevenLabs. Rejetée : elle transforme LOUVIA en
  habillage. Le jour où un agent annonce un prix inventé, nous n'aurions ni le
  moyen de l'empêcher, ni celui de l'expliquer au marchand.
- **Option A pure** — tout chez nous, y compris le tour de parole téléphonique.
  Rejetée pour l'instant : la latence d'un appel voix bâti à la main est un projet
  à part entière, et ce n'est pas là que se joue la valeur pour un restaurant.
