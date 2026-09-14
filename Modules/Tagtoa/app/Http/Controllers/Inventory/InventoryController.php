<?php

namespace Modules\Tagtoa\App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Inventory\Supplier;
use Modules\Tagtoa\App\Models\Menu\Item as MenuItem;
use Modules\Tagtoa\App\Models\Pos\Product;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Inventory\StockLedger;
use Modules\Tagtoa\App\Services\Inventory\StockReport;
use Modules\Tagtoa\App\Support\Inventory\MovementType;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA INVENTORY — la réserve, vue du patron.
 *
 * Trois écrans, trois questions dans l'ordre où elles se posent :
 *   • le stock       — qu'est-ce que je dois recommander aujourd'hui ?
 *   • le journal     — où sont passées les douze bouteilles qui manquent ?
 *   • les fournisseurs — chez qui je rappelle ?
 *
 * Tout mouvement passe par StockLedger. Ce contrôleur ne touche jamais la
 * colonne `stock` : il transmet une intention et un motif.
 */
class InventoryController extends Controller
{
    public function __construct(protected StockReport $report, protected StockLedger $ledger)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = Tenant::id();
        $faibles  = $request->boolean('low');

        return view('tagtoa::inventory.index', [
            'articles'  => $this->report->articles($tenantId, $faibles),
            'summary'   => $this->report->summary($tenantId),
            'shrink'    => $this->report->shrinkage($tenantId, now()->subDays(30)->toDateString()),
            'suppliers' => $this->suppliers(),
            'motifs'    => MovementType::MANUAL,
            'types'     => MovementType::TYPES,
            'currency'  => Tenant::currency(),
            'onlyLow'   => $faibles,
        ]);
    }

    public function movements(Request $request): View
    {
        $tenantId = Tenant::id();

        $filtres = $request->validate([
            'ref'   => ['nullable', 'string', 'max:32'],
            'type'  => ['nullable', 'string', Rule::in(array_keys(MovementType::TYPES))],
            'staff' => ['nullable', 'integer'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date'],
        ]);

        return view('tagtoa::inventory.movements', [
            'movements' => $this->report->journal($tenantId, $filtres)->paginate(50)->withQueryString(),
            'filtres'   => $filtres,
            'types'     => MovementType::TYPES,
            'team'      => Staff::where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'currency'  => Tenant::currency(),
        ]);
    }

    /**
     * Enregistre un mouvement saisi à la main.
     *
     * Le comptage physique est traité à part : le patron y tape ce qu'il a
     * COMPTÉ, pas l'écart. Lui demander l'écart reviendrait à lui faire deviner
     * le chiffre qu'il vient justement chercher.
     */
    public function move(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ref'         => ['required', 'string', 'max:32'],
            'type'        => ['required', 'string', Rule::in(MovementType::MANUAL)],
            'qty'         => ['required', 'numeric', 'min:0.001', 'max:999999999'],
            'reason'      => ['nullable', 'string', 'max:240'],
            'unit_cost'   => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $article = $this->article($data['ref']);
        abort_unless($article, 404);

        $contexte = [
            'reason'      => $data['reason'] ?? null,
            'unit_cost'   => $data['unit_cost'] ?? null,
            'supplier_id' => $this->supplierId($data['supplier_id'] ?? null),
        ];

        $mouvement = MovementType::replacesStock($data['type'])
            ? $this->ledger->count($article, (float) $data['qty'], $contexte)
            : $this->ledger->apply(
                $article,
                MovementType::direction($data['type']) === 'in' ? (float) $data['qty'] : -(float) $data['qty'],
                $data['type'],
                $contexte
            );

        if (! $mouvement) {
            // Article non suivi, ou comptage identique au stock affiché : il
            // n'y a rien à raconter, et l'écran doit le dire plutôt que de
            // laisser croire à un enregistrement.
            return back()->with('success', __('Rien à enregistrer : le stock est déjà à jour.'));
        }

        // Une réception dit aussi le prix du jour : c'est l'occasion la plus
        // naturelle de tenir le prix d'achat à jour, et sans lui aucune marge
        // ne peut être calculée.
        if ($data['type'] === MovementType::PURCHASE && ($data['unit_cost'] ?? null) !== null) {
            $article->forceFill(['cost_price' => (float) $data['unit_cost']])->save();
        }

        app(AuditService::class)->log('stock.movement', null,
            $mouvement->product_name.' '.$mouvement->delta_label.' — '.$mouvement->type_label);

        return back()->with('success', __(':article : :ecart (:motif). Nouveau stock : :stock', [
            'article' => $mouvement->product_name,
            'ecart'   => $mouvement->delta_label,
            'motif'   => $mouvement->type_label,
            'stock'   => rtrim(rtrim(number_format((float) $mouvement->stock_after, 3, '.', ''), '0'), '.'),
        ]));
    }

    /* ---------------- fournisseurs ---------------- */

    public function suppliersIndex(): View
    {
        return view('tagtoa::inventory.suppliers', [
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function supplierStore(Request $request): RedirectResponse
    {
        $data = $this->validatedSupplier($request);

        $fournisseur = Supplier::create($data + ['tenant_id' => Tenant::id(), 'is_active' => true]);

        app(AuditService::class)->log('supplier.created', null, $fournisseur->name);

        return back()->with('success', __('Fournisseur enregistré.'));
    }

    public function supplierUpdate(Request $request, int $id): RedirectResponse
    {
        $fournisseur = $this->ownSupplier($id);
        $fournisseur->update($this->validatedSupplier($request));

        return back()->with('success', __('Fiche fournisseur mise à jour.'));
    }

    /**
     * Désactive plutôt que supprimer.
     *
     * L'historique des réceptions continue de désigner ce fournisseur : le
     * faire disparaître laisserait des mouvements orphelins, c'est-à-dire des
     * réceptions dont on ne saurait plus d'où elles viennent.
     */
    public function supplierToggle(int $id): RedirectResponse
    {
        $fournisseur = $this->ownSupplier($id);
        $fournisseur->update(['is_active' => ! $fournisseur->is_active]);

        return back()->with('success', $fournisseur->is_active
            ? __('Fournisseur réactivé.')
            : __('Fournisseur archivé. Son historique reste intact.'));
    }

    /* ---------------- helpers ---------------- */

    /**
     * L'article désigné par « pos:7 » ou « menu:7 », DANS ce commerce.
     *
     * Jamais un find() nu : un identifiant deviné donnerait l'article du
     * voisin, et le mouvement irait décrémenter son stock à lui.
     */
    private function article(string $ref): Product|MenuItem|null
    {
        if (! str_contains($ref, ':')) {
            return null;
        }

        [$source, $id] = explode(':', $ref, 2);
        if (! ctype_digit($id)) {
            return null;
        }

        $tenantId = Tenant::id();

        return $source === 'menu'
            ? MenuItem::whereHas('menu', fn ($m) => $m->where('tenant_id', $tenantId))->whereKey((int) $id)->first()
            : Product::where('tenant_id', $tenantId)->whereKey((int) $id)->first();
    }

    /** Un fournisseur de CE commerce, sinon rien. */
    private function supplierId(mixed $id): ?int
    {
        if (! $id) {
            return null;
        }

        return Supplier::whereKey((int) $id)->value('id');
    }

    private function ownSupplier(int $id): Supplier
    {
        return Supplier::whereKey($id)->firstOrFail();
    }

    private function suppliers()
    {
        return Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    private function validatedSupplier(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'phone'        => ['nullable', 'string', 'max:40'],
            'whatsapp'     => ['nullable', 'string', 'max:40'],
            'email'        => ['nullable', 'email', 'max:160'],
            'address'      => ['nullable', 'string', 'max:240'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
