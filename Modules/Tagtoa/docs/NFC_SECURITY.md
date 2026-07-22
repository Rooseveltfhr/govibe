# TAGTOA — Sécurité NFC (anti-clonage / anti-rejeu)

> **État : DORMANT** (comme les passerelles PAY avant identifiants). La logique
> de vérification est implémentée et **prouvée en test** ; l'activation exige que
> tu provisionnes les clés des puces et que tu valides le schéma contre un tag
> réel. Rien dans le parcours de check-in / porte-monnaie actuel n'est modifié
> tant que ce n'est pas activé.

## Le problème que ça résout

Aujourd'hui une puce NFC est identifiée par son **UID** seul. Un UID est **lisible
et clonable** : n'importe qui peut copier une carte TAGTOA Pay ou un bracelet
d'événement sur une puce vierge et se faire passer pour le porteur. Pour un
produit qui **manipule de l'argent** (porte-monnaie fermé, carte de paiement),
c'est un trou de fraude au cœur de la valeur.

## La solution : NTAG424 DNA (SUN / SDM)

Les puces **NTAG424 DNA** signent **chaque lecture**. L'URL générée par la puce
contient :

- l'**UID** (7 octets),
- un **compteur de lecture** (SDMReadCtr, 3 octets, incrémenté à chaque tap),
- un **CMAC tronqué** (8 octets) calculé avec une **clé secrète** stockée dans la
  puce et **non lisible**.

Un clone qui ne connaît pas la clé **ne peut pas produire un CMAC valide** →
rejeté. Le **compteur strictement croissant** empêche le **rejeu** d'une ancienne
lecture capturée. La puce devient un **jeton infalsifiable**.

## Ce qui est livré (testé, prouvé)

| Composant | Rôle | Preuve |
|---|---|---|
| `Support/Nfc/AesCmac` | AES-CMAC (RFC 4493) | Vecteurs officiels RFC 4493 (annexe D) |
| `Support/Nfc/Ntag424` | Dérivation clé de session (SV2), troncature, vérif CMAC, extraction UID/compteur, anti-rejeu | `Ntag424Test` : accepte le valide, rejette clone/UID falsifié/rejeu |

Pipeline de vérification (NXP AN12196) :

```
SV2 = 3C C3 00 01 00 80 || UID(7) || SDMReadCtr(3)          (16 octets)
SesSDMFileReadMACKey = CMAC(K_SDMFileReadMAC, SV2)
MAC attendu = tronqué_octets_impairs( CMAC(SesKey, données_MAC) )
accepté  ⇔  MAC_URL == MAC attendu  ET  compteur > dernier_compteur_vu
```

## Pour ACTIVER en production (action fondateur)

1. **Provisionner les puces** NTAG424 : programmer la clé `SDMFileReadMAC` et
   activer le miroir SDM (UID + SDMReadCtr + CMAC) dans l'URL. Outils : appli NXP
   TagWriter / TagXplorer, ou un encodeur de production.
2. **Valider le schéma** : vérifier le préfixe SV2 et la troncature contre
   l'**exemple AN12196** ou un **tag réel** (une lecture connue doit passer
   `Ntag424::verify`). C'est la seule inconnue externe ; la logique est déjà
   prouvée en interne.
3. **Stocker la clé** côté serveur (par événement / par lot de cartes), jamais en
   clair côté client. Table `tagtoa_ev_nfc_tags` porte déjà l'UID hashé — ajouter
   une réf. de clé (nullable) le moment venu.
4. **Câbler la vérification** (opt-in, non cassant) là où un UID est résolu :
   - `Services/Event/CheckinService::resolveNfcCode` (check-in porte),
   - `WalletController::resolve` (paiement porte-monnaie / recharge).
   Si aucune clé n'est configurée pour la ressource → comportement actuel
   inchangé (UID seul). Si une clé existe → exiger un SUN valide + compteur frais.

## Pourquoi ce n'est pas encore câblé

Câbler la vérification dans le check-in / le porte-monnaie touche du **code
live**. Conformément à la discipline du projet (ne pas modifier à l'aveugle un
parcours qui manipule de l'argent, sans test d'intégration ni validation
matérielle), l'intégration se fera **avec toi**, une fois une puce provisionnée
disponible pour un test bout-en-bout. La brique cryptographique, elle, est prête
et vérifiée.
