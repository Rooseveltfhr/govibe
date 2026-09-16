<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Pos\PosSales;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Catalog\Pricing;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — caisse tactile + back-office.
 */
class PosController extends Controller
{
    use EnforcesPlan;

    public function __construct(protected PosService $service)
    {
    }

    public function index(): View
    {
        $terminals = Terminal::where('tenant_id', Tenant::id())->withCount('products')->latest()->get();

        return view('tagtoa::pos.index', compact('terminals'));
    }

    public function store(Request $request): RedirectResponse
    {
        if ($r = $this->planGuard('pos')) {
            return $r;
        }
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'currency' => ['nullable', 'string', 'max:10']]);
        $terminal = new Terminal($data);
        $terminal->tenant_id = Tenant::id();
        $terminal->save();

        return redirect()->route('tagtoa.pos.products.terminal', $terminal->id)->with('success', __('Caisse créée.'));
    }

    public function register(int $id): View
    {
        $terminal = $this->own($id);

        return view('tagtoa::pos.register', [
            'terminal' => $terminal,
            // Boutons de la caisse ET articles du menu du commerce : le
            // marchand saisit un plat une fois et le vend aussi au comptoir.
            'sellable' => app(PosCatalog::class)->sellable($terminal->tenant_id),
            'methods'  => Sale::METHODS,
            // Employé au poste, et faut-il en demander un ? Tant que le commerce
            // n'a créé personne, la caisse fonctionne comme avant.
            'staff'      => $this->currentStaff($terminal),
            'hasStaff'   => Staff::where('tenant_id', $terminal->tenant_id)->where('is_active', true)->exists(),
            // Le régime de taxe, pour que la caisse annonce le BON montant
            // avant d'encaisser. Avec des prix hors taxe, afficher le
            // sous-total ferait annoncer moins que ce que le client paiera.
            'tax'        => \Modules\Tagtoa\App\Services\Tax\TaxProfile::current($terminal->tenant_id),
        ]);
    }

    public function sale(Request $request, int $id): JsonResponse
    {
        $terminal = $this->own($id);
        $data = $request->validate([
            'items'              => ['required', 'array', 'min:1'],
            'items.*.name'       => ['required', 'string', 'max:120'],
            'items.*.price'      => ['required', 'numeric', 'min:0'],
            // DÉCIMAL : le riz se vend à la mamit, la viande à la livre. Un
            // « integer » ici bloquait 2,5 livres alors que la caisse et la
            // base savent les traiter depuis 0.2b.
            'items.*.qty'        => ['required', 'numeric', 'min:0', 'max:999999'],
            // « menu:7 » / « pos:7 ». `product_id` reste accepté : une caisse
            // déjà installée ne doit pas s'arrêter de vendre à la mise à jour.
            'items.*.ref'        => ['nullable', 'string', 'max:30'],
            'items.*.product_id' => ['nullable', 'integer'],
            'discount'           => ['nullable', 'numeric', 'min:0'],
            'payments'           => ['nullable', 'array'],
            'customer_phone'     => ['nullable', 'string', 'max:40'],
            'client_uuid'        => ['nullable', 'string', 'max:64'],
        ]);

        $sale = $this->service->recordSale($terminal, $data, $this->currentStaff($terminal));

        // Le TOTAL vient du serveur, toujours : avec des prix hors taxe, il est
        // supérieur à ce que la caisse avait calculé, et c'est ce montant-là
        // que le caissier doit annoncer au client.
        return response()->json([
            'ok'        => true,
            'reference' => $sale->reference,
            'total'     => (float) $sale->total,
            'tax'       => (float) $sale->tax_total,
            'tax_label' => $sale->tax_label,
        ]);
    }

    public function sync(Request $request, int $id): JsonResponse
    {
        $terminal = $this->own($id);
        // Employé au poste au moment de la REPRISE. La caisse hors-ligne rejoue
        // ses ventes dès le retour du réseau, donc en pratique la même personne
        // — et si le poste a été fermé entre-temps, la vente revient au patron
        // plutôt que d'être attribuée à qui a repris le comptoir.
        $staff = $this->currentStaff($terminal);
        $results = [];
        foreach ($request->input('sales', []) as $payload) {
            try {
                $sale = $this->service->recordSale($terminal, $payload, $staff);
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => true, 'reference' => $sale->reference];
            } catch (\Throwable $e) {
                $results[] = ['client_uuid' => $payload['client_uuid'] ?? null, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function report(int $id, Request $request): View
    {
        $terminal = $this->own($id);
        $date = $request->date('date') ?: now();

        // Ce que la personne au poste a le DROIT de voir. Le tableau de bord est
        // celui du patron : sans employé connecté, la vue reste complète.
        $staff = $this->currentStaff($terminal);
        $visibles = $staff
            ? app(PosSales::class)->visibleTo($staff, $terminal)
            : app(PosSales::class)->forOwner($terminal->tenant_id, $terminal);

        $sales = (clone $visibles)->whereDate('sold_at', $date)->where('status', 1)
            ->with('staff')->latest()->get();

        $byMethod = [];
        foreach ($sales as $s) {
            foreach ((array) $s->payments as $p) {
                $m = $p['method'] ?? 'cash';
                $byMethod[$m] = ($byMethod[$m] ?? 0) + (float) ($p['amount'] ?? 0);
            }
        }
        $z = ['date' => $date->format('Y-m-d'), 'count' => $sales->count(), 'total' => $sales->sum('total'), 'by_method' => $byMethod];

        // « Qui a encaissé combien » — n'a de sens que pour qui voit plus que
        // ses propres ventes.
        $byCashier = $staff && $staff->salesScope() === 'own'
            ? []
            : app(PosSales::class)->byCashier(
                (clone $visibles)->whereDate('sold_at', $date)->where('status', 1)
            );

        // Ce que le commerce doit déclarer sur la journée. Calculé sur les
        // montants FIGÉS des ventes : une déclaration qui changerait parce
        // qu'on a modifié un taux depuis ne vaudrait rien.
        $tax = app(PosSales::class)->taxReport(
            (clone $visibles)->whereDate('sold_at', $date)->where('status', 1)
        );

        return view('tagtoa::pos.report', compact('terminal', 'sales', 'z', 'staff', 'byCashier', 'tax'));
    }

    public function products(int $id): View
    {
        $terminal = $this->own($id, ['products']);

        // Type d'activité du commerce (pharmacie, bar, boutique…) : décide
        // quelles unités mettre en tête du menu déroulant, sans en interdire
        // aucune — un commerce réel vend rarement une seule sorte d'article.
        $type = \Modules\Tagtoa\App\Models\Business\Business::whereKey($terminal->tenant_id)->value('type');

        return view('tagtoa::pos.products', [
            'terminal'   => $terminal,
            'suppliers'  => \Modules\Tagtoa\App\Models\Inventory\Supplier::where('is_active', true)
                ->orderBy('name')->get(['id', 'name']),
            'categories' => \Modules\Tagtoa\App\Models\Pos\Category::shown()->get(['id', 'name']),
            'suggestedUnits' => Pricing::unitsFor($type),
        ]);
    }

    /**
     * AJOUTER UN SEUL ARTICLE — et l'enregistrer tout de suite.
     *
     * L'écran empilait des lignes vides qu'il fallait penser à enregistrer à la
     * fin. Trois conséquences, toutes vécues :
     *   • on scanne cinq produits, le téléphone se verrouille, tout est perdu ;
     *   • on ne sait plus lesquels sont déjà au catalogue et lesquels attendent ;
     *   • au-delà de quelques dizaines de lignes, PHP tronque l'envoi
     *     (`max_input_vars`) et la fin disparaît sans un mot.
     *
     * Un article s'ajoute donc seul et part en base immédiatement. Le formulaire
     * se vide, le curseur revient sur le nom, on enchaîne. Chaque article est
     * acquis au moment où on le voit apparaître dans la liste.
     */
    public function addProduct(Request $request, int $id): RedirectResponse
    {
        $terminal = $this->own($id);

        $data = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            // Courte à dessein : deux lignes sur la carte produit, pas un
            // paragraphe qui pousserait le prix hors de l'écran.
            'description'         => ['nullable', 'string', 'max:160'],
            'price'               => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'stock'               => ['nullable', 'numeric', 'min:-999999', 'max:999999999'],
            'cost_price'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'unit'                => ['nullable', 'string', Rule::in(array_keys(Pricing::UNITS))],
            'sku'                 => ['nullable', 'string', 'max:60'],
            'supplier_id'         => ['nullable', 'integer'],
            'category_id'         => ['nullable', 'integer'],
            // La date d'achat du lot. Elle ne sert pas à vendre : elle répond à
            // « depuis quand cette caisse de bière dort-elle ici ? », la
            // question qui distingue un commerce qui tourne d'un commerce dont
            // la trésorerie est immobilisée sur ses étagères.
            'purchased_at'        => ['nullable', 'date'],
            'emoji'               => ['nullable', 'string', 'max:16'],
            'color'               => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'new_code'            => ['nullable', 'string', 'max:64'],
            // 2 Mo : au-delà, une photo de plat prise au téléphone échouerait à
            // l'envoi sur une connexion haïtienne sans qu'on sache pourquoi.
            'image'               => ['nullable', 'image', 'max:2048'],
        ]);

        $attrs = [
            'name'                => $data['name'],
            'description'         => trim((string) ($data['description'] ?? '')) ?: null,
            'price'               => (float) ($data['price'] ?? 0),
            'emoji'               => $data['emoji'] ?? null,
            'color'               => $data['color'] ?? '#2cb809',
            'stock'               => $this->nombreOuNull($data['stock'] ?? null),
            'is_active'           => true,
            'sort'                => (int) app(PosCatalog::class)->query($terminal->tenant_id)->max('sort') + 1,
            'cost_price'          => $this->nombreOuNull($data['cost_price'] ?? null),
            'unit'                => Pricing::unit($data['unit'] ?? null),
            'low_stock_threshold' => $this->nombreOuNull($data['low_stock_threshold'] ?? null),
            'sku'                 => trim((string) ($data['sku'] ?? '')) ?: null,
            'supplier_id'         => $this->fournisseur($data['supplier_id'] ?? null),
            'category_id'         => $this->rayon($data['category_id'] ?? null),
            'purchased_at'        => $data['purchased_at'] ?? null,
        ];

        if ($request->hasFile('image')) {
            $attrs['image_path'] = $request->file('image')->store('tagtoa/pos-products', 'public');
        }

        $product = app(PosCatalog::class)->save($terminal, $attrs);

        // Le code scanné ne peut s'attacher qu'ICI : un code se rattache à
        // quelque chose qui existe. C'est ce qui ferme la boucle — scanner un
        // produit inconnu, le créer, et le revendre en le scannant.
        if (! empty($data['new_code'])) {
            app(\Modules\Tagtoa\App\Services\Catalog\ProductCodes::class)
                ->attach($terminal->tenant_id, 'pos:'.$product->id, $data['new_code']);
        }

        return back()->with('success', __('« :nom » ajouté au catalogue.', ['nom' => $product->name]));
    }

    /**
     * SCANNER POUR CRÉER — l'article existe avant qu'on l'ait nommé.
     *
     * C'est le geste d'un commerce qui reçoit un carton : on passe la douchette
     * sur trente articles d'affilée, on les nomme ensuite, assis. Demander un
     * nom et un prix à chaque bip ferait abandonner à l'article cinq — et les
     * vingt-cinq autres resteraient hors du catalogue.
     *
     * L'article est donc créé AUSSITÔT, avec son code accroché, sous un nom
     * provisoire qui porte le code lui-même : il est retrouvable, il apparaît
     * dans la liste, et il est INACTIF tant qu'il n'a pas de prix. Un article
     * sans prix proposé à la vente ferait encaisser zéro.
     */
    public function scanProduct(Request $request, int $id): JsonResponse
    {
        $terminal = $this->own($id);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = trim($data['code']);

        // Déjà connu : on le montre, on n'en crée pas un deuxième. Deux articles
        // pour le même produit, c'est un stock coupé en deux — et un inventaire
        // qui ne retombe jamais juste.
        $codes = app(\Modules\Tagtoa\App\Services\Catalog\ProductCodes::class);
        if ($article = $codes->find($terminal->tenant_id, $code)) {
            return response()->json([
                'result'  => 'exists',
                'message' => __('Ce code est déjà celui de : :nom', ['nom' => $article->name]),
                'product' => ['id' => $article->id, 'name' => $article->name],
            ]);
        }

        $product = app(PosCatalog::class)->save($terminal, [
            'name'        => __('Article :code', ['code' => $code]),
            'price'       => 0,
            'stock'       => null,
            // INACTIF tant qu'il n'a pas de prix : un bouton à zéro gourde en
            // caisse, c'est une vente encaissée pour rien.
            'is_active'   => false,
            'color'       => '#8a8a8a',
            'sort'        => (int) app(PosCatalog::class)->query($terminal->tenant_id)->max('sort') + 1,
        ]);

        $codes->attach($terminal->tenant_id, 'pos:'.$product->id, $code);

        return response()->json([
            'result'  => 'created',
            'message' => __('Article créé et enregistré. Donnez-lui un nom et un prix.'),
            'product' => ['id' => $product->id, 'name' => $product->name],
        ]);
    }

    /**
     * MODIFIER les articles déjà au catalogue.
     *
     * Distinct de l'ajout : ici rien n'est créé, on retouche des lignes qui
     * existent déjà et que le marchand voit à l'écran.
     */
    public function saveProducts(Request $request, int $id): RedirectResponse
    {
        $terminal = $this->own($id);

        // ENVOI TRONQUÉ — la panne silencieuse de PHP.
        //
        // Au-delà de `max_input_vars` (1000 par défaut), PHP coupe l'envoi SANS
        // erreur : les derniers articles n'arrivent simplement jamais, et le
        // marchand lit « Produits enregistrés ». Un champ sentinelle posé en
        // DERNIER dans le formulaire dit si la fin est arrivée.
        //
        // Même défaut, même remède qu'au menu (0.2d) — il manquait ici.
        if ($request->has('products') && ! $request->filled('form_end')) {
            return back()->with('error', __(
                'Votre navigateur n\'a pas pu envoyer toute la liste : rien n\'a été modifié. '
                .'Modifiez moins d\'articles à la fois.'
            ));
        }

        // Le formulaire n'était pas validé : un prix négatif, un stock
        // aberrant ou une unité inventée entraient tels quels dans la base et
        // ressortaient au moment d'encaisser.
        $request->validate([
            'products'                       => ['array', 'max:500'],
            'products.*.name'                => ['nullable', 'string', 'max:120'],
            'products.*.description'         => ['nullable', 'string', 'max:160'],
            'products.*.toggle_active'       => ['nullable', 'boolean'],
            'products.*.price'               => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'products.*.cost_price'          => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'products.*.stock'               => ['nullable', 'numeric', 'min:-999999', 'max:999999999'],
            'products.*.low_stock_threshold' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'products.*.unit'                => ['nullable', 'string', Rule::in(array_keys(Pricing::UNITS))],
            'products.*.sku'                 => ['nullable', 'string', 'max:60'],
            'products.*.supplier_id'         => ['nullable', 'integer'],
            'products.*.category_id'         => ['nullable', 'integer'],
            'products.*.purchased_at'        => ['nullable', 'date'],
            'products.*.new_code'            => ['nullable', 'string', 'max:64'],
            'products.*.emoji'               => ['nullable', 'string', 'max:16'],
            'products.*.color'               => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'products.*.image'               => ['nullable', 'image', 'max:2048'],
        ]);

        $keep = [];
        foreach ($request->input('products', []) as $i => $row) {
            // BASCULER LA MISE EN VENTE — une action à part, pas un
            // enregistrement complet, et contrôlée AVANT l'exigence d'un nom.
            //
            // Le menu « Retirer de la vente » n'envoie que l'identifiant. S'il
            // passait par le chemin ordinaire, tous les champs absents seraient
            // écrits à vide : retirer un article de la vente effacerait son
            // prix, son stock et sa photo.
            if (! empty($row['toggle_active'])) {
                $p = app(PosCatalog::class)->find($terminal->tenant_id, (int) ($row['id'] ?? 0));
                if ($p) {
                    $p->forceFill(['is_active' => ! $p->is_active])->save();
                }
                continue;
            }

            if (empty($row['name'])) {
                continue;
            }

            $attrs = [
                'name'      => $row['name'],
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
                'price'     => (float) ($row['price'] ?? 0),
                'emoji'     => $row['emoji'] ?? null,
                'color'     => $row['color'] ?? '#2cb809',
                // Stock DÉCIMAL : le riz se compte à la mamit, la viande à la
                // livre. Un cast entier ferait disparaître une demi-livre à
                // chaque enregistrement.
                'stock'     => $this->nombreOuNull($row['stock'] ?? null),
                'is_active' => ! empty($row['is_active']),
                'sort'      => (int) ($row['sort'] ?? $i),

                // Volet commercial : ce qui permet enfin de dire au marchand
                // combien il GAGNE, et pas seulement combien il encaisse.
                // Le coût reste null quand il n'est pas renseigné — « 0 »
                // laisserait croire que la marge est totale.
                'cost_price'          => $this->nombreOuNull($row['cost_price'] ?? null),
                'unit'                => Pricing::unit($row['unit'] ?? null),
                'low_stock_threshold' => $this->nombreOuNull($row['low_stock_threshold'] ?? null),
                'sku'                 => trim((string) ($row['sku'] ?? '')) ?: null,
                // Chez qui cet article est acheté d'habitude. Un identifiant
                // deviné ne doit pas rattacher le fournisseur du voisin :
                // la recherche est cloisonnée par le commerce courant.
                'supplier_id'         => $this->fournisseur($row['supplier_id'] ?? null),
                'category_id'         => $this->rayon($row['category_id'] ?? null),
                'purchased_at'        => $row['purchased_at'] ?? null,
            ];

            // La photo arrive hors de `input()` : un fichier n'est pas une
            // valeur. On la cherche donc par son chemin exact dans l'envoi, et
            // on ne touche à la colonne QUE si quelque chose a été envoyé —
            // sinon un simple changement de prix effacerait l'image.
            $image = $request->file("products.$i.image");
            if ($image) {
                $request->validate(["products.$i.image" => ['image', 'max:2048']]);
                $attrs['image_path'] = $image->store('tagtoa/pos-products', 'public');
            } elseif (! empty($row['remove_image'])) {
                $attrs['image_path'] = null;
            }

            // Catalogue du COMMERCE : l'article est partagé par toutes ses caisses.
            $p = app(PosCatalog::class)->save($terminal, $attrs, ! empty($row['id']) ? (int) $row['id'] : null);
            $keep[] = $p->id;

            // Code scanné au moment de créer la ligne : on ne peut l'attacher
            // qu'ici, une fois l'article réellement enregistré. C'est ce qui
            // ferme la boucle — scanner un produit inconnu, le créer, et le
            // revendre en le scannant, sans jamais taper de chiffres.
            if (! empty($row['new_code'])) {
                app(\Modules\Tagtoa\App\Services\Catalog\ProductCodes::class)
                    ->attach($terminal->tenant_id, 'pos:'.$p->id, $row['new_code']);
            }
        }

        // ENREGISTRER NE SUPPRIME JAMAIS.
        // Le formulaire effaçait auparavant tout article absent de l'envoi. Le
        // catalogue étant désormais partagé par toutes les caisses, un envoi
        // partiel — connexion coupée, deux personnes qui modifient en même
        // temps, un navigateur qui ne poste pas tout — effaçait les articles de
        // TOUT le commerce. Supprimer est maintenant une action à part.
        return back()->with('success', __('Produits enregistrés.'));
    }

    /**
     * Un rayon de CE commerce, sinon rien.
     *
     * L'identifiant vient du navigateur : sans ce filtre, un numéro deviné
     * rangerait l'article dans le rayon du voisin — et le ferait apparaître
     * dans SA grille de caisse.
     */
    private function rayon(mixed $id): ?int
    {
        return $id
            ? \Modules\Tagtoa\App\Models\Pos\Category::whereKey((int) $id)->value('id')
            : null;
    }

    /** Un fournisseur de CE commerce, sinon rien. */
    private function fournisseur(mixed $id): ?int
    {
        return $id ? \Modules\Tagtoa\App\Models\Inventory\Supplier::whereKey((int) $id)->value('id') : null;
    }

    /** Champ numérique laissé vide = « non renseigné », pas « zéro ». */
    private function nombreOuNull(mixed $valeur): ?float
    {
        return ($valeur === null || $valeur === '') ? null : (float) $valeur;
    }

    /**
     * Supprime UN article du catalogue — acte délibéré du patron.
     *
     * Une caisse ne supprime rien : le caissier vend, rend un article à un
     * client et retire une ligne du panier en cours, mais le catalogue est celui
     * du commerce. Pour retirer un article de la vente sans le perdre, il existe
     * l'interrupteur « actif » : l'historique et le stock restent intacts.
     */
    public function destroyProduct(int $id, int $productId): RedirectResponse
    {
        $terminal = $this->own($id);

        $product = app(PosCatalog::class)->find($terminal->tenant_id, $productId);
        abort_unless($product, 404);

        $nom = $product->name;
        $product->delete();

        // Les ventes déjà encaissées gardent le nom et le prix de l'article :
        // supprimer un produit ne réécrit aucun historique.
        app(\Modules\Tagtoa\App\Services\Audit\AuditService::class)
            ->log('pos.product_deleted', null, $nom);

        return back()->with('success', __('Article supprimé du catalogue.').' ('.$nom.')');
    }


    /* ---------- Qui tient la caisse ---------- */

    /**
     * Employé connecté SUR CETTE CAISSE, ou null.
     *
     * La session ne garde qu'un identifiant : l'employé est relu en base à
     * chaque requête, pour qu'une désactivation ferme la caisse immédiatement
     * plutôt qu'à la prochaine connexion.
     */
    protected function currentStaff(Terminal $terminal): ?Staff
    {
        $id = session('tagtoa_pos_staff.'.$terminal->id);

        return $id
            ? Staff::where('tenant_id', $terminal->tenant_id)->where('is_active', true)->find($id)
            : null;
    }

    /** Ouvre le poste après vérification du code. */
    public function staffLogin(Request $request, int $id): RedirectResponse
    {
        $terminal = $this->own($id);
        $data = $request->validate(['pin' => ['required', 'string', 'max:20']]);

        $staff = app(StaffService::class)->authenticate($terminal->tenant_id, $data['pin'], $terminal->id);

        if (! $staff) {
            // Message volontairement identique pour un code faux et pour un
            // employé désactivé : ne rien apprendre à qui essaie des codes.
            return back()->with('error', __('Code incorrect.'));
        }

        session(['tagtoa_pos_staff.'.$terminal->id => $staff->id]);

        return back()->with('success', __('Bonjour :nom.', ['nom' => $staff->name]));
    }

    /** Ferme le poste (fin de service, ou relève par un collègue). */
    public function staffLogout(int $id): RedirectResponse
    {
        $terminal = $this->own($id);
        session()->forget('tagtoa_pos_staff.'.$terminal->id);

        return back()->with('success', __('Poste fermé.'));
    }

    /* ---------- PWA (installable + offline) ---------- */

    /** Manifeste Web App de la caisse (par terminal). */
    public function manifest(int $id): JsonResponse
    {
        $terminal = $this->own($id);
        $scope = rtrim(url('/tagtoa/pos'), '/').'/';

        return response()->json([
            'name'             => $terminal->name.' — TAGTOA POS',
            'short_name'       => 'TAGTOA POS',
            'start_url'        => route('tagtoa.pos.register', $terminal->id),
            'scope'            => $scope,
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#0A0A0A',
            'theme_color'      => '#2cb809',
            'lang'             => app()->getLocale(),
            'icons'            => [
                ['src' => route('tagtoa.pos.icon'), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ],
        ]);
    }

    /** Icône SVG (vectorielle, sans fichier binaire à publier). */
    public function icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">'
            .'<rect width="512" height="512" rx="96" fill="#2cb809"/>'
            .'<path d="M300 96 154 288h86l-28 128 160-208h-92z" fill="#fff"/>'
            .'</svg>';

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /** Service worker : cache l'enveloppe (app shell) pour un usage hors ligne. */
    public function serviceWorker()
    {
        $scope = rtrim(url('/tagtoa/pos'), '/').'/';
        $js = <<<JS
const CACHE = 'tagtoa-pos-v1';
self.addEventListener('install', (e) => self.skipWaiting());
self.addEventListener('activate', (e) => {
    e.waitUntil(caches.keys().then(ks => Promise.all(ks.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
// Network-first pour la navigation (HTML), cache-first pour le reste. GET seulement.
self.addEventListener('fetch', (e) => {
    const req = e.request;
    if (req.method !== 'GET') return;
    if (req.mode === 'navigate') {
        e.respondWith(
            fetch(req).then(res => { const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c)); return res; })
                      .catch(() => caches.match(req))
        );
        return;
    }
    e.respondWith(
        caches.match(req).then(hit => hit || fetch(req).then(res => {
            if (res && res.status === 200 && (res.type === 'basic' || res.type === 'cors')) {
                const c = res.clone(); caches.open(CACHE).then(ca => ca.put(req, c));
            }
            return res;
        }).catch(() => hit))
    );
});
JS;

        return response($js, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', $scope);
    }

    protected function own(int $id, array $with = []): Terminal
    {
        return Terminal::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    /* ==================================================================
       LA CAISSE DU COMMERCE — pour que le POS s'ouvre sans numéro.
       ================================================================== */

    /**
     * La caisse courante du commerce, créée au besoin.
     *
     * TOUS les écrans du POS exigeaient un identifiant de caisse dans leur URL
     * (`/tagtoa/pos/7/products`). Conséquence pratique : rien ne pouvait être
     * mis au menu, puisqu'un menu ne connaît pas le numéro 7. C'est la vraie
     * raison pour laquelle la caisse n'avait qu'une entrée là où elle a treize
     * écrans — et non un choix de design.
     *
     * Or depuis B-1 l'unité est le COMMERCE, et depuis B-3 le catalogue lui
     * appartient déjà : la caisse n'est plus qu'un poste de travail. Les écrans
     * se rangent donc sous le commerce, et le numéro ne sert plus qu'à ceux qui
     * désignent vraiment un poste — vendre, faire son rapport de poste.
     *
     * Créer la caisse manquante est délibéré : demander à un marchand de créer
     * « une caisse » avant de pouvoir ouvrir « Produits » est une marche qui
     * n'apprend rien et sur laquelle on trébuche.
     */
    protected function caisseCourante(array $with = []): Terminal
    {
        $tenantId = Tenant::id();

        $terminal = Terminal::with($with)->where('tenant_id', $tenantId)
            ->orderBy('id')->first();

        if ($terminal) {
            return $terminal;
        }

        $terminal = new Terminal([
            'name'      => __('Caisse principale'),
            'currency'  => 'HTG',
            'is_active' => true,
        ]);
        $terminal->tenant_id = $tenantId;
        $terminal->save();

        return $with ? $terminal->load($with) : $terminal;
    }

    /* ---- Les mêmes écrans, sans numéro dans l'URL ---- */

    public function currentProducts(): View
    {
        return $this->products($this->caisseCourante(['products'])->id);
    }

    public function currentRegister(): RedirectResponse
    {
        // Redirection plutôt que rendu direct : la caisse est une application
        // installable (manifeste, service worker) dont l'adresse porte le
        // numéro du poste. La servir sous deux adresses en ferait deux
        // installations, avec deux files d'attente hors ligne distinctes.
        return redirect()->route('tagtoa.pos.register', $this->caisseCourante()->id);
    }

    public function currentReport(Request $request): View
    {
        return $this->report($this->caisseCourante()->id, $request);
    }
}
