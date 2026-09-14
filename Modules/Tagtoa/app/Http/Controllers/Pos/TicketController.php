<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
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
     */
    public function show(int $id): View
    {
        $caisses = Terminal::where('tenant_id', Tenant::id())->pluck('id');

        $sale = Sale::whereIn('terminal_id', $caisses)
            ->with(['items', 'terminal', 'staff:id,name'])
            ->whereKey($id)
            ->firstOrFail();

        return view('tagtoa::pos.receipt', ['sale' => $sale]);
    }
}
