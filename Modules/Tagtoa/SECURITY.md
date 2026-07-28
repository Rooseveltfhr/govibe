# TAGTOA — Revue de sécurité (juin 2026)

Revue ciblée du module `Modules/Tagtoa` (app multi-tenant manipulant de l'argent).
3 problèmes identifiés et **corrigés** ; reste documenté ci-dessous.

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
