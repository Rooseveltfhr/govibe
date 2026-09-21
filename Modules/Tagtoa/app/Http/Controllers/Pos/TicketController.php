<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — les tickets déjà encaissés, et leur reçu.
 *
 * Il n'existait aucun moyen de retrouver une vente passée. Le rapport Z donne
 * un TOTAL de journée ; il ne répond pas à « la dame de ce matin dit qu'elle a
 * payé 500, qu'est-ce qu'elle a pris ? ». C'est pourtant la question qu'on pose
 * à une caisse tous les jours, et la seule façon de trancher un litige au
 * comptoir sans faire attendre la file.
 *
 * LECTURE SEULE. Un ticket encaissé ne se modifie pas : corriger une vente
 * après coup, c'est réécrire une recette. Ce qui doit être défait passe par un
 * RETOUR, qui laisse sa propre trace.
 */
class TicketController extends Controller
{
    /** Une journée chargée tient dedans ; un téléphone ne rame pas. */
    private const PAR_PAGE = 30;

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'q'    => ['nullable', 'string', 'max:60'],
            'date' => ['nullable', 'date'],
        ]);

        // Le cloisonnement passe par les caisses DU commerce : les ventes
        // portent un terminal, pas un commerce. Sans ce filtre, un identifiant
        // deviné donnerait le ticket du voisin.
        $caisses = Terminal::where('tenant_id', Tenant::id())->pluck('name', 'id');

        $query = Sale::whereIn('terminal_id', $caisses->keys())
            ->with(['items', 'staff:id,name'])
            ->orderByDesc('sold_at')->orderByDesc('id');

        if (! empty($filtres['date'])) {
            $query->whereDate('sold_at', $filtres['date']);
        }

        if ($terme = trim((string) ($filtres['q'] ?? ''))) {
            $query->where(function ($q) use ($terme) {
                $q->where('reference', 'like', '%'.$terme.'%')
                  ->orWhere('customer_phone', 'like', '%'.$terme.'%');
            });
        }

        return view('tagtoa::pos.tickets', [
            'sales'   => $query->paginate(self::PAR_PAGE)->withQueryString(),
            'caisses' => $caisses,
            'filtres' => $filtres,
        ]);
    }

    /**
     * Le reçu d'une vente, prêt à imprimer.
     *
     * Page autonome, sans le tableau de bord autour : on imprime un reçu, pas
     * une barre latérale. Et sur une imprimante thermique de 58 mm, tout ce qui
     * dépasse la largeur est simplement coupé.
     *
     * Le COMMERCE est passé à la vue, pas seulement la caisse : un client qui
     * revient contester présente son ticket, et il doit y lire chez qui il a
     * acheté et comment le joindre. « Caisse 1 » ne lui dit rien.
     */
    public function show(int $id): View
    {
        $sale = $this->saleById($id);

        return view('tagtoa::pos.receipt', [
            'sale'     => $sale,
            'business' => Business::whereKey(Tenant::id())->first(),
        ]);
    }

    /**
     * Le reçu de la DERNIÈRE vente d'une caisse, par sa référence.
     *
     * La caisse ne connaît que la référence qu'elle vient d'afficher — pas
     * l'identifiant en base. Sans ce chemin, le bouton « Imprimer » de l'écran
     * de confirmation ne pouvait qu'imprimer l'écran lui-même : une page A4
     * presque blanche, avec les boutons dessus. C'est ce qui sortait.
     *
     * Cloisonné comme le reste : la référence est cherchée UNIQUEMENT parmi les
     * caisses de ce commerce.
     */
    public function byReference(string $reference): View
    {
        $sale = $this->saleByReference($reference);

        return view('tagtoa::pos.receipt', [
            'sale'     => $sale,
            'business' => Business::whereKey(Tenant::id())->first(),
        ]);
    }

    /**
     * Le MÊME reçu, en JSON — pour une imprimante, pas un navigateur.
     *
     * Chaque montant est déjà passé par `Money::format()` : le format
     * d'affichage d'une devise (avant/après, décimales, symbole) est une
     * décision prise UNE fois, ici, jamais recopiée dans le JavaScript qui
     * dessine le ticket sur le papier. Un ticket Bluetooth qui recalculerait
     * son propre affichage finirait, un jour, par ne plus dire la même chose
     * que le reçu HTML du même client.
     *
     * Cloisonné exactement comme `byReference` : c'est la même vente, lue par
     * une autre porte.
     */
    public function data(string $reference): JsonResponse
    {
        $sale = $this->saleByReference($reference);
        $business = Business::whereKey(Tenant::id())->first();

        $paiements = is_array($sale->payments) ? $sale->payments : [];

        return response()->json([
            'reference'  => $sale->reference,
            'sold_at'    => optional($sale->sold_at)->format('d/m/Y H:i'),
            'terminal'   => $sale->terminal->name ?? '',
            'staff'      => $sale->staff->name ?? null,
            'business'   => [
                // `$business` peut être null (compte hors du cycle normal, ou
                // suppression manuelle) : `?->` sur chaque champ plutôt qu'un
                // avertissement PHP par accès à une propriété d'un null.
                'name'        => $business?->name ?? ($sale->terminal->name ?? 'TAGTOA'),
                'address'     => $business?->address,
                'phone'       => $business?->phone,
                'tax_number'  => $business?->tax_number,
            ],
            'items' => $sale->items->map(fn ($it) => [
                'name'       => $it->name,
                'qty'        => rtrim(rtrim(number_format((float) $it->qty, 3, '.', ''), '0'), '.'),
                'price'      => number_format((float) $it->price, 2),
                'line_total' => Money::format($it->line_total, $sale->currency),
            ])->all(),
            'subtotal'   => Money::format($sale->subtotal, $sale->currency),
            'discount'   => (float) $sale->discount > 0 ? Money::format($sale->discount, $sale->currency) : null,
            'tax_label'  => (float) $sale->tax_total > 0 ? ($sale->tax_label ?: __('Taxe')) : null,
            'tax_total'  => (float) $sale->tax_total > 0 ? Money::format($sale->tax_total, $sale->currency) : null,
            'total'      => Money::format($sale->total, $sale->currency),
            'payments'   => array_map(fn ($p) => [
                'label'  => Sale::METHODS[$p['method'] ?? ''] ?? ($p['method'] ?? ''),
                'amount' => Money::format($p['amount'] ?? 0, $sale->currency),
            ], $paiements),
            'footer'     => $business?->receipt_footer ?: __('Merci de votre confiance !'),
        ]);
    }

    /* ---------------- interne ---------------- */

    /** Un ticket de CE commerce, par identifiant, ou 404. */
    private function saleById(int $id): Sale
    {
        return Sale::whereIn('terminal_id', $this->mesCaisses())
            ->with(['items', 'terminal', 'staff:id,name'])
            ->whereKey($id)
            ->firstOrFail();
    }

    /** Un ticket de CE commerce, par référence, ou 404. */
    private function saleByReference(string $reference): Sale
    {
        return Sale::whereIn('terminal_id', $this->mesCaisses())
            ->with(['items', 'terminal', 'staff:id,name'])
            ->where('reference', $reference)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /**
     * Les caisses DE ce commerce.
     *
     * Les ventes portent un terminal, pas un commerce : sans ce filtre, un
     * identifiant ou une référence devinée donnerait le ticket du voisin.
     */
    private function mesCaisses()
    {
        return Terminal::where('tenant_id', Tenant::id())->pluck('id');
    }
}
