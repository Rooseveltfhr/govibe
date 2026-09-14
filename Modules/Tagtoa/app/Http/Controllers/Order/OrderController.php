<?php

namespace Modules\Tagtoa\App\Http\Controllers\Order;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Order\Order;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Order\Channel;
use Modules\Tagtoa\App\Support\Order\OrderStatus;

/**
 * TAGTOA — toutes les commandes, quel que soit le chemin par lequel elles sont
 * arrivées.
 *
 * La colonne vertébrale (étape 02) existait sans écran : chaque module montrait
 * ses propres commandes, et le marchand devait ouvrir quatre écrans pour savoir
 * ce qu'il avait vendu dans la journée. C'est précisément ce que la colonne
 * vertébrale devait permettre d'éviter.
 *
 * Lecture seule : le détail métier — le numéro de table, l'adresse de
 * livraison, le billet — reste chez le module qui le connaît. On mène donc à
 * lui plutôt que de le recopier ici, où il finirait par diverger.
 */
class OrderController extends Controller
{
    /** Assez pour une journée chargée, pas assez pour bloquer un téléphone. */
    private const PAR_PAGE = 40;

    public function index(Request $request): View
    {
        $filtres = $request->validate([
            'channel' => ['nullable', 'string'],
            'status'  => ['nullable', 'string'],
            'q'       => ['nullable', 'string', 'max:60'],
        ]);

        // La portée par commerce est automatique (BelongsToTenant) : c'est le
        // trait qui empêche qu'un filtre oublié montre les ventes du voisin.
        $query = Order::query()->orderByDesc('placed_at')->orderByDesc('id');

        if (Channel::isValid($filtres['channel'] ?? null)) {
            $query->where('channel', $filtres['channel']);
        }

        // « ouvertes » n'est pas un statut : c'est TOUT ce qui n'est pas fini.
        // C'est la question que se pose réellement un marchand en service — et
        // la lister statut par statut lui ferait manquer celles qu'il ne pense
        // pas à cocher.
        if (($filtres['status'] ?? null) === 'open') {
            $query->whereNotIn('status', OrderStatus::CLOSED);
        } elseif (OrderStatus::isValid($filtres['status'] ?? null)) {
            $query->where('status', $filtres['status']);
        }

        if ($terme = trim((string) ($filtres['q'] ?? ''))) {
            $query->where(function ($q) use ($terme) {
                $q->where('reference', 'like', '%'.$terme.'%')
                  ->orWhere('customer_name', 'like', '%'.$terme.'%')
                  ->orWhere('customer_phone', 'like', '%'.$terme.'%');
            });
        }

        $orders = $query->paginate(self::PAR_PAGE)->withQueryString();

        return view('tagtoa::order.index', [
            'orders'   => $orders,
            'channels' => Channel::LABELS,
            'filtres'  => $filtres,
            'totaux'   => $this->totaux(),
        ]);
    }

    /**
     * Ce que le marchand veut savoir en ouvrant l'écran : combien aujourd'hui,
     * et combien reste-t-il à servir.
     *
     * Calculé à part de la liste filtrée : un chiffre d'affaires qui change
     * quand on filtre par canal n'est plus un chiffre d'affaires.
     */
    private function totaux(): array
    {
        $jour = Order::whereDate('placed_at', now()->toDateString());

        return [
            'devise'   => (clone $jour)->value('currency') ?: 'HTG',
            'total'    => (float) (clone $jour)->whereNotIn('status', [OrderStatus::CANCELLED, OrderStatus::REFUNDED])->sum('total'),
            'nombre'   => (clone $jour)->count(),
            'ouvertes' => Order::whereNotIn('status', OrderStatus::CLOSED)->count(),
        ];
    }
}
