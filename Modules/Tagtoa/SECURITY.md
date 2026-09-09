# TAGTOA — Revue de sécurité (juin 2026)

Revue ciblée du module `Modules/Tagtoa` (app multi-tenant manipulant de l'argent).
3 problèmes identifiés et **corrigés** ; reste documenté ci-dessous.

## Isolation entre commerces — par construction (septembre 2026)

L'isolation ne repose plus sur la mémoire du développeur.

**Avant.** Aucune portée automatique dans le projet. Chaque requête, dans 28
contrôleurs, devait penser à écrire `where('tenant_id', …)`. Un seul oubli
exposait les données d'un commerce à un autre — et c'est arrivé : la page
d'accueil affichait à chaque marchand les compteurs de toute la plateforme.

**Après.** Le trait `Support/BelongsToTenant` est posé sur les **27 modèles**
qui portent un commerce. Il fait trois choses :

| | |
|---|---|
| Lecture | limitée au commerce courant, sans que la requête le demande |
| Création | `tenant_id` rempli automatiquement — sauf s'il est déjà posé |
| Sortie | uniquement par un appel **explicite** à `allTenants()` |

### Ce qui n'est volontairement pas couvert

La portée ne s'applique pas quand **aucun commerce n'est en session** : page
publique visitée par un client, webhook d'une passerelle, commande console,
test. C'est délibéré — une page publique doit être lisible par un inconnu, et
elle est toujours atteinte par un **alias unique**, jamais par une liste.

La faute que ce trait empêche est celle du tableau de bord, où le commerce
**est** connu et où l'oubli d'un filtre montre les données du voisin.

### Les sorties délibérées

Trois endroits sortent de l'isolation, chacun pour une raison écrite dans le
fichier :

- `SuperAdminService` — revenu global du fondateur (lecture seule, route
  réservée `role:super_admin`) ;
- `CardCreditService` — le fondateur crédite un **autre** commerce que le sien ;
  sans cette sortie, une attribution créait une ligne en double ;
- `SuperAdmin\CardCreditController` — la liste des crédits de tous les commerces.

Un test (`TenantScopeCoverageTest`) **échoue si une quatrième sortie apparaît**
ailleurs, et échoue aussi si un nouveau modèle portant un commerce oublie le
trait. C'est ce qui rend l'isolation « par construction » plutôt que par
discipline : elle ne peut plus se perdre au prochain modèle.

### Vérifié par des tests, pas par lecture

`TenantIsolationTest` ouvre une **vraie session marchande** — sans quoi la
portée ne s'active pas et les tests passeraient pour de mauvaises raisons. Il
couvre : la requête qui a oublié son filtre, l'identifiant deviné du voisin, le
remplissage automatique, la valeur délibérée jamais écrasée, la page publique
qui reste ouverte, la vue plateforme du fondateur, et la jointure entre deux
tables portant chacune un `tenant_id`.

---

## Corrigés

### 1. Stored XSS — page Pay publique (HIGH, confiance 8/10)
- **Fichier :** `resources/views/pay/show.blade.php`
- **Cause :** `account_number` (texte libre saisi par le marchand) était injecté dans
  un littéral JS à l'intérieur d'un attribut `onclick`. `{{ }}` (htmlspecialchars)
  ne suffit pas en contexte « JS dans attribut » : le navigateur HTML-décode `&#039;`
  en `'` avant de parser le JS → évasion de chaîne possible.
- **Exploit :** un marchand met `account_number = ');alert(document.cookie);//` ;
  quand un client clique « Copier » sur `/pay/{alias}`, du JS arbitraire s'exécute
  dans le navigateur du payeur (XSS stocké visant les clients).
- **Correctif :** valeur déplacée dans `data-copy="{{ ... }}"` (contexte attribut
  normal, sûr) ; le JS lit `el.getAttribute('data-copy')`. Plus aucune donnée
  utilisateur dans un handler inline.

### 2. IDOR cross-tenant via `product_id` POS (MEDIUM→HIGH, confiance 8/10)
- **Fichier :** `app/Services/Pos/PosService.php`
- **Cause :** `Product::find($it['product_id'])->decrement('stock')` utilisait l'id
  brut du client sans vérifier que le produit appartient au terminal/tenant.
- **Exploit :** un marchand authentifié poste une vente sur son terminal avec le
  `product_id` d'un AUTRE tenant + `qty` élevé → décrémente le stock de la victime.
- **Correctif :** le produit est résolu **uniquement** via `$terminal->products()
  ->whereKey($id)` ; un id non possédé est ignoré (ni référence stockée, ni
  décrément).

### 3. Référence cross-tenant via `vcard_id` / `pay_page_id` (MEDIUM, confiance 7/10)
- **Fichiers :** `Pay`, `Loyalty`, `Links`, `Event` `DashboardController`
- **Cause :** `vcard_id` (et `pay_page_id` pour Links/Event) validés seulement
  `integer` ; la liste `vcards()` n'était pas filtrée par tenant. Un marchand
  pouvait lier sa page au `vcard_id` d'un autre tenant → ex. les notifications de
  preuve de paiement (PII : nom/téléphone/montant) pouvaient être routées vers le
  propriétaire de la vcard ciblée (si le modèle hôte `Vcard` n'a pas de global scope).
- **Correctif :** `vcards()` filtrée par `tenant_id`, et `vcard_id`/`pay_page_id`
  validés via `Rule::in()` sur les ids possédés par le tenant courant.

## Vérifié — OK (non vulnérable)
- CRUD dashboard (Pay/Loyalty/Links/Event/POS/Billing) scopé par `Tenant::id()`
  (`own()/ownCard()/ownEvent()`, `whereHas('page'|'program', tenant)`).
- `submitProof` valide que `payment_method_id` appartient à la page (pas d'IDOR).
- Pages publiques order/ticket/carte : jetons aléatoires non devinables
  (`public_token` 24, ticket `T`+11) — secrets par conception.
- QR `{!! $qr !!}` = SVG généré par la lib depuis des URLs applicatives (pas de HTML utilisateur).
- POS register : `addslashes()` + `e()` (double-échappement) → pas d'évasion ;
  données vues par le marchand lui-même.
- Uploads (preuves, logos, avatars, covers) : `image`/`mimes`/`max` + `store(...,'public')`
  avec noms hachés par le framework → pas de traversal ni d'extension exécutable.
- Endpoints POST publics (submitProof, buy) protégés par CSRF (middleware `web`).

## Reste à confirmer (hôte Biztap)
- `App\Models\Vcard` : présence d'un global scope tenant + colonne `tenant_id`
  (le correctif #3 fonctionne dans les deux cas).
