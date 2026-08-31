# 04 — Architecture des réservations

Répond au point 10 de la première tâche, et développe le désaccord annoncé en
[`01-strategie.md §1.4a`](01-strategie.md#14-ce-que-je-conteste-dans-le-cahier-des-charges).

---

## 1. Le problème avec la demande initiale

Le cahier des charges (point 8) demande une recherche par dates, un filtrage par
disponibilité, et une réservation en ligne. Techniquement, c'est parfaitement
faisable — Laravel n'a aucune difficulté avec un moteur d'inventaire.

**Le problème n'est pas technique. Il est opérationnel.**

Un moteur de disponibilités ne fonctionne que si quelqu'un tient l'inventaire à
jour, tous les jours, sans exception. Concrètement, cela suppose qu'un hôtelier
de Môle-Saint-Nicolas ouvre une interface web chaque fois qu'une chambre est
prise par téléphone, par WhatsApp, ou à l'accueil. Il ne le fera pas — non par
mauvaise volonté, mais parce que son canal de vente principal reste le téléphone
et que la double saisie n'apporte rien à son quotidien.

### La conséquence si on l'ignore

```
Chambre réservée par téléphone
   └─► inventaire du site jamais mis à jour
          └─► le site affiche « disponible »
                 └─► un touriste réserve et se croit confirmé
                        └─► il arrive : pas de chambre
                               └─► confiance détruite, publiquement
```

**Un calendrier faux est pire qu'une absence de calendrier.** Sans calendrier,
le visiteur sait qu'il doit confirmer. Avec un calendrier faux, il croit être
confirmé. Le premier incident de ce type coûte plus cher en réputation que tout
le bénéfice attendu de la fonctionnalité.

### Le vrai canal du marché

Au Môle comme dans la plupart des destinations haïtiennes de province, la
réservation se fait **par WhatsApp**. C'est l'outil que tout le monde a, que
tout le monde consulte, et qui ne demande aucun apprentissage. Une architecture
qui combat ce fait échoue ; une architecture qui s'appuie dessus réussit.

---

## 2. La proposition : demande de réservation

### Principe

> La plateforme ne vend pas. **Elle qualifie une intention et la transmet au bon
> interlocuteur, sur le canal qu'il utilise déjà.**

Le visiteur remplit un formulaire court. La plateforme génère une demande
référencée, notifie l'établissement par WhatsApp et par e-mail, et suit le
statut. La confirmation reste humaine.

### Ce que le visiteur voit

```
Fiche hôtel
   [ Demander une réservation ]
        │
        ▼
   Formulaire — 6 champs
   ┌────────────────────────────────────────┐
   │ Nom              ·  Téléphone/WhatsApp │
   │ E-mail                                 │
   │ Arrivée  ──  Départ                    │
   │ Nombre de personnes                    │
   │ Message (facultatif)                   │
   └────────────────────────────────────────┘
        │
        ▼
   Confirmation immédiate et honnête :
   ┌────────────────────────────────────────┐
   │ Demande MSN-2026-00042 envoyée.        │
   │                                        │
   │ ⚠ Ceci n'est pas une réservation       │
   │   confirmée. L'établissement vous      │
   │   répond directement, généralement     │
   │   sous 24 h.                           │
   │                                        │
   │ [ Contacter directement sur WhatsApp ] │
   └────────────────────────────────────────┘
```

**Le libellé compte autant que le code.** L'interface ne doit jamais laisser
croire à une confirmation. « Demande envoyée », jamais « Réservation confirmée ».

Le bouton WhatsApp direct est délibérément placé sur l'écran de confirmation :
si le visiteur préfère court-circuiter la plateforme, il doit pouvoir le faire.
Une plateforme qui retient l'utilisateur en otage pour préserver ses statistiques
détruit sa propre proposition de valeur.

### Ce que l'établissement reçoit

**WhatsApp** (canal principal — via l'infrastructure Twilio déjà configurée dans
`.env.example`, section « WhatsApp notifications ») :

```
Nouvelle demande — molesaintnicolas.com

Réf. : MSN-2026-00042
Client : [nom]
Contact : [téléphone]
Dates : 12 → 15 mars 2026
Personnes : 2

« [message] »

Répondre : https://molesaintnicolas.com/r/MSN-2026-00042
```

**E-mail** en doublon, avec les mêmes informations.

Le lien mène à une page publique signée (URL signée Laravel, sans mot de passe,
valable 30 jours) où l'établissement clique **Confirmer** ou **Décliner**. Aucun
compte requis : demander une inscription à ce stade ferait chuter le taux de
réponse à presque zéro.

### Le suivi

| Statut | Signification |
|---|---|
| `pending` | Enregistrée, notification en file d'attente |
| `sent` | Établissement notifié |
| `confirmed` | Établissement a confirmé |
| `declined` | Établissement a décliné |
| `expired` | Aucune réponse après 72 h |
| `cancelled` | Annulée par le visiteur |

À 48 h sans réponse, une relance automatique part vers l'établissement. À 72 h,
la demande expire et **le visiteur est prévenu** avec les coordonnées directes et
des suggestions d'alternatives. Ne pas laisser un visiteur dans le silence est
plus important que de préserver l'apparence d'un bon taux de réponse.

---

## 3. Ce que cela apporte réellement

| Bénéfice | Détail |
|---|---|
| **Fonctionne dès le premier établissement** | Aucun préalable technique côté partenaire |
| **Aucun risque de sur-réservation** | La plateforme ne prétend jamais connaître la disponibilité |
| **Mesure la demande réelle** | Chaque demande est une donnée : quels établissements, quelles dates, quel volume |
| **Argument commercial** | « Vous avez reçu 23 demandes ce trimestre » est ce qui vend un abonnement premium |
| **Coût de construction faible** | Environ une semaine, contre trois à quatre pour un moteur d'inventaire |
| **Sélectionne les partenaires sérieux** | Les taux de réponse identifient qui mérite un accès inventaire en phase 6 |

Le dernier point est le plus important stratégiquement : **les données de la V1
déterminent objectivement s'il faut construire la phase 6, et pour qui.** Si,
après un an, aucun établissement ne répond aux demandes, construire un moteur de
disponibilités serait une erreur — et on le saura sur preuve, pas sur intuition.

---

## 4. Conception logicielle

### Contrat polymorphe

```php
namespace Modules\Mole\Contracts;

interface Bookable
{
    public function acceptsBookingRequests(): bool;
    public function bookingChannels(): array;       // whatsapp, email
    public function bookingFields(): array;         // champs requis selon le type
    public function notificationRecipients(): array;
}
```

Implémenté par `Hotel`, `Restaurant`, `Activity`, `Event`. Chaque type déclare
les champs pertinents — un hôtel demande arrivée/départ, un restaurant une date
et une heure, une activité une date et un nombre de participants. **Un seul
formulaire, un seul service, quatre comportements.**

### Service

```php
namespace Modules\Mole\Services;

class BookingRequestService
{
    public function create(Bookable $b, BookingRequestData $d): BookingRequest;
    public function notifyPartner(BookingRequest $r): void;   // en file d'attente
    public function confirm(BookingRequest $r, ?string $note): void;
    public function decline(BookingRequest $r, ?string $reason): void;
    public function expireStale(): int;                       // tâche planifiée
}
```

Les notifications passent par la file d'attente (`QUEUE_CONNECTION=database`,
déjà configuré). **Une panne WhatsApp ne doit jamais faire échouer
l'enregistrement de la demande** : on enregistre d'abord, on notifie ensuite,
avec réessais.

### Protection contre les abus

Une route publique qui écrit en base et déclenche des notifications sortantes est
une cible. Mesures :

- limitation à **5 demandes par heure et par IP**, et 3 par adresse e-mail ;
- champ piège invisible (honeypot) + délai minimal de soumission ;
- validation stricte : `check_out > check_in`, dates dans le futur, séjour
  plafonné à 30 nuits, `party_size` entre 1 et 50 ;
- normalisation et validation du format des numéros de téléphone ;
- adresse IP et user-agent journalisés ;
- **aucune donnée saisie n'est réinjectée dans le message WhatsApp sans
  échappement** — le message du visiteur est du texte non fiable ;
- un établissement peut être désactivé (`accepts_booking_requests = false`) sans
  supprimer sa fiche.

### Recherche et filtres en V1

Les filtres demandés au point 8 du cahier restent disponibles, **à l'exception
de la disponibilité** :

| Filtre | V1 | Remarque |
|---|---|---|
| Localisation (commune, section) | ✅ | |
| Fourchette de prix | ✅ | Indicative |
| Nombre d'étoiles | ✅ | |
| Équipements | ✅ | JSON, filtré en SQL |
| Type de cuisine | ✅ | Restaurants |
| Catégorie d'activité | ✅ | |
| Nombre de personnes | ✅ | Comparé à la capacité déclarée |
| **Dates de disponibilité** | ❌ | Reporté en phase 6 |

L'interface indique clairement, sur les résultats, que la disponibilité se
confirme auprès de l'établissement. L'honnêteté de l'interface est une décision
produit, pas une limitation subie.

---

## 5. Préparation des paiements

Aucune implémentation de paiement en V1 — les credentials MonCash/NatCash ne sont
pas disponibles et la conformité BRH ne s'improvise pas. L'architecture est
néanmoins préparée.

### Colonnes déjà présentes

`mole_booking_requests` porte `payment_status`, `payment_provider`,
`payment_reference`, `estimated_total` et `currency`, **inutilisées en V1**. La
phase 6 n'exigera donc aucune migration sur cette table.

### Interface prévue

```php
namespace Modules\Mole\Contracts;

interface PaymentGateway
{
    public function initiate(Payable $p, Money $amount): PaymentIntent;
    public function verify(string $reference): PaymentStatus;
    public function refund(string $reference, ?Money $amount): RefundResult;
    public function handleWebhook(Request $request): WebhookResult;
}
```

Implémentations futures : `MonCashGateway`, `NatCashGateway`, `StripeGateway`,
`PayPalGateway`. Le dépôt contient déjà des travaux exploitables
(`Modules/Tagtoa/app/Support/Gateways`, `govibepay`) : ils devront être audités
avant réutilisation, pas repris tels quels.

### Contraintes non négociables — rappel de `CLAUDE.md`

Le fichier `CLAUDE.md` du dépôt impose, pour toute logique financière :

1. **Ledger à double entrée obligatoire.** Aucune écriture directe sur un solde.
   Tout mouvement passe par le service de ledger.
2. **Conformité à la Circulaire 121 de la BRH.**
3. **`/security-review` sans alerte critique** avant tout merge touchant une
   route financière.
4. **Idempotence** sur les webhooks de paiement : un webhook rejoué ne doit
   jamais créditer deux fois.
5. **Gestion hors ligne et résolution de conflits** si un point de vente est
   impliqué.

Ces contraintes ne sont pas des formalités : elles conditionnent la faisabilité
de la phase 6 et doivent être planifiées comme un chantier à part entière, avec
son propre audit, pas comme une fonctionnalité ajoutée à la plateforme
touristique.

---

## 6. Chemin vers la phase 6

La réservation transactionnelle se construit **quand les données le justifient**,
pas à une date décidée d'avance. Critères de déclenchement :

| Critère | Seuil indicatif |
|---|---|
| Demandes de réservation par mois | > 50 |
| Établissements répondant sous 24 h | ≥ 5 |
| Taux de confirmation | > 60 % |
| Partenaires demandant explicitement l'inventaire | ≥ 3 |

Si ces seuils ne sont pas atteints après douze mois, la conclusion n'est pas
« il faut construire le moteur », c'est **« la demande n'est pas là »** — et
l'effort doit aller ailleurs. Cette discipline est ce qui distingue une roadmap
d'une liste de souhaits.

Séquence de construction, le moment venu :

1. `mole_room_availability` (`room_id`, `date`, `available_count`, `price`)
2. Interface partenaire de tenue de calendrier — simple, mobile
3. Blocage de dates et verrouillage transactionnel anti-survente
4. Paiement (acompte d'abord, jamais le montant total au départ)
5. Politique d'annulation et remboursement
6. Éventuelle synchronisation iCal avec Booking.com / Airbnb

---

**Suite :** [`05-roadmap.md`](05-roadmap.md)
