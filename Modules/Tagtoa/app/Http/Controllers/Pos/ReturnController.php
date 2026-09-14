<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\SaleReturn;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Pos\ReturnService;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — les retours.
 *
 * Un retour fait SORTIR de l'argent. C'est la seule action du back-office qui
 * en fasse sortir, et elle est donc traitée comme telle : montants recalculés
 * côté serveur à partir des prix figés de la vente, clé d'idempotence contre le
 * double envoi, journal d'audit systématique.
 *
 * Le formulaire dit QUELLES lignes et COMBIEN d'unités — jamais combien
 * d'argent. Accepter un montant envoyé par la page, ce serait laisser
 * n'importe qui se rembourser ce qu'il veut.
 */
class ReturnController extends Controller
{
    public function __construct(protected ReturnService $service)
    {
    }

    public function index(): View
    {
        return view('tagtoa::pos.returns', [
            'returns' => SaleReturn::with(['items', 'sale:id,reference'])
                ->orderByDesc('returned_at')->orderByDesc('id')->paginate(30),
            'recents' => $this->ventesRecentes(),
        ]);
    }

    /** L'écran d'un retour : les lignes de la vente, et ce qui reste à rendre. */
    public function create(int $id): View
    {
        $sale = $this->vente($id);

        return view('tagtoa::pos.return-form', [
            'sale'     => $sale,
            'rendable' => $this->service->rendable($sale),
            'kinds'    => SaleReturn::KINDS,
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            // Des QUANTITÉS, jamais des montants : le prix est relu sur la
            // vente, où il est figé depuis l'encaissement.
            'qty'              => ['required', 'array'],
            'qty.*'            => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'kind'             => ['nullable', 'string', 'max:24'],
            'reason'           => ['nullable', 'string', 'max:160'],
            'restock'          => ['nullable', 'boolean'],
            // Posée par le formulaire, une fois : elle survit au rechargement
            // et c'est elle qui empêche le double remboursement.
            'idempotency_key'  => ['required', 'string', 'max:64'],
        ]);

        $sale = $this->vente($id);

        $resultat = $this->service->record($sale, $data['qty'], [
            'tenant_id'       => Tenant::id(),
            'kind'            => $data['kind'] ?? 'customer',
            'reason'          => $data['reason'] ?? null,
            // `boolean()` et NON `?? true` : une case décochée n'est pas
            // envoyée du tout, si bien qu'un défaut à `true` remettait la
            // marchandise en rayon alors que le caissier venait de dire le
            // contraire. Le formulaire pose aussi un champ caché à 0, pour que
            // l'intention arrive même sans cette lecture.
            'restock'         => $request->boolean('restock'),
            'idempotency_key' => $data['idempotency_key'],
            'staff_id'        => null,
        ]);

        if ($resultat['result'] === ReturnService::OK) {
            // Un mouvement d'argent laisse toujours une trace nominative :
            // c'est ce qui permet de trancher plus tard, et ce qui décourage
            // d'encaisser puis de « rendre » à soi-même.
            app(AuditService::class)->log(
                'pos.return',
                null,
                $resultat['return']->reference.' — '.$resultat['return']->total.' '.$sale->currency
            );

            return redirect()->route('tagtoa.pos.returns')
                ->with('success', __('Retour :ref enregistré.', ['ref' => $resultat['return']->reference]));
        }

        if ($resultat['result'] === ReturnService::DEJA_FAIT) {
            // Rejoué : on le dit calmement. Répondre « erreur » ferait
            // recommencer le caissier — et c'est ainsi qu'on rembourse deux fois.
            return redirect()->route('tagtoa.pos.returns')
                ->with('success', __('Ce retour était déjà enregistré (:ref).', ['ref' => $resultat['return']->reference]));
        }

        return back()->withInput()->with('error', match ($resultat['result']) {
            ReturnService::RIEN => __('Indiquez au moins une quantité à rendre.'),
            ReturnService::TROP => __('Vous ne pouvez pas rendre plus que ce qui a été vendu.'),
            default             => __('Ce retour n\'a pas pu être enregistré.'),
        });
    }

    /* ---------------- interne ---------------- */

    /**
     * Une vente de CE commerce, ou 404.
     *
     * Le cloisonnement passe par les caisses : une vente porte un poste, pas un
     * commerce. Sans ce filtre, un identifiant deviné rembourserait sur la
     * vente du voisin — avec notre argent.
     */
    private function vente(int $id): Sale
    {
        $caisses = Terminal::where('tenant_id', Tenant::id())->pluck('id');

        return Sale::whereIn('terminal_id', $caisses)
            ->with('items')->whereKey($id)->firstOrFail();
    }

    /** De quoi démarrer un retour sans chercher : les ventes du jour. */
    private function ventesRecentes()
    {
        $caisses = Terminal::where('tenant_id', Tenant::id())->pluck('id');

        return Sale::whereIn('terminal_id', $caisses)
            ->orderByDesc('sold_at')->orderByDesc('id')
            ->limit(15)->get(['id', 'reference', 'total', 'currency', 'sold_at']);
    }
}
