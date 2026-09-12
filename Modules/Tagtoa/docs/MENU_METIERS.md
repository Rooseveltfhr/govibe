# TAGTOA MENU — un formulaire par métier

## Le problème

Le module MENU sert un restaurant, un hôtel, un bar, un club, un lounge et un
café avec le **même** formulaire. Or un hôtel ne vend pas des plats : il vend
des chambres, avec une capacité, des lits, une vue, des équipements et un prix
**par nuit**. Saisir une chambre dans un formulaire pensé pour un plat oblige le
marchand à tout écrire dans la description — et le client ne peut plus comparer.

## La solution

Le moteur ne change pas (catégories → articles → options). Ce qui change selon
le type d'établissement :

| | Restaurant | Hôtel | Bar / Club / Lounge | Café |
|---|---|---|---|---|
| **On dit** | Plat | Chambre | Boisson / Article | Article |
| **Le prix veut dire** | prix du plat | **prix par nuit** | prix | prix |
| **Champs propres** | temps de préparation, portion, piment, régime, allergènes | type de chambre, capacité, lits, superficie, vue, équipements, petit-déjeuner | format, volume, degré d'alcool, service (+ personnes incluses et « inclus » en club) | taille, service, temps de préparation, régime |
| **Catégories proposées** | Entrées, Plats, Grillades… | Chambres, Suites, Services… | Bières, Rhums, Cocktails… | Cafés, Thés, Jus… |

Les catégories sont **proposées**, jamais imposées : un clic les ajoute, le
marchand reste libre de créer les siennes.

## Où c'est écrit

- `app/Support/Menu/BusinessProfile.php` — **toute** la connaissance métier :
  vocabulaire, catégories proposées, champs. Classe pure, sans Laravel, donc
  testable sans base de données (`tests/Unit/BusinessProfileTest.php`).
- Colonne `tagtoa_menu_items.specs` (JSON). Une colonne par champ et par métier
  ferait grossir la table sans fin et imposerait une migration à chaque nouveau
  type d'établissement.
- Elle s'appelle `specs` et **non** `attributes` : Eloquent utilise déjà
  `$attributes` en interne, et un accesseur écrit plus tard y lirait le tableau
  interne du modèle au lieu de la colonne.

## Ajouter un métier

1. Ajouter le type dans `Menu::TYPES` (libellé + icône).
2. Ajouter son profil dans `BusinessProfile::PROFILES`.

Aucune migration, aucune modification du formulaire ni du contrôleur. Un test
échoue automatiquement si un type est proposé au marchand sans profil
(`MenuBusinessTypeTest::test_every_type_offered_in_the_dropdown_has_a_profile`).

## Sécurité

Ce qui arrive du formulaire est indexé par clé et passe par
`BusinessProfile::sanitize()` **avant** toute écriture :

- une clé non déclarée pour ce type est écartée (y compris un champ d'un autre
  métier) ;
- un choix hors catalogue est refusé ;
- un nombre est borné à son domaine (une capacité de chambre ne peut pas valoir
  999) ;
- rien à stocker ⇒ `null`, jamais un objet vide.

Même discipline que pour les moyens de paiement : le JavaScript n'est qu'un
confort d'affichage, le serveur revalide tout contre le même profil.

Ces champs **décrivent** l'article. Ils n'entrent dans aucun calcul de prix :
le montant facturé reste la colonne `price`, éventuellement ajustée par les
options (voir `ItemOptionPricing`).

## Changer de type après coup

Un marchand qui passe d'« Hôtel » à « Restaurant » garde ses anciennes valeurs
en base, mais elles ne s'affichent plus : `BusinessProfile::display()` ignore ce
qui n'est pas déclaré pour le type courant, plutôt que de montrer au client des
données qui n'ont plus de sens. Rien n'est effacé — repasser en « Hôtel » les
fait réapparaître.
