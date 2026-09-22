<?php

namespace Modules\Tagtoa\App\Services\Order;

use Modules\Tagtoa\App\Models\Order\Order;

/**
 * TAGTOA — « comment se porte le commerce aujourd'hui, comparé à hier ? »
 *
 * Lit la colonne vertébrale (Order — voir OrderSpine, qui l'écrit), pas les
 * modules d'origine : c'est justement parce qu'elle existe déjà, tous canaux
 * confondus, qu'un tel aperçu ne demande aucune nouvelle jointure dans POS,
 * MENU ou EVENT.
 *
 * Le revenu ne compte que les commandes qui COMPTENT dans la recette
 * (Order::scopeRevenue — payées ou partiellement payées, ni annulées ni
 * remboursées) ; le nombre de commandes et de clients compte TOUTE l'activité
 * du jour, y compris ce qui n'est pas encore encaissé — c'est ce qu'un
 * marchand veut savoir en ouvrant l'écran, pas seulement ce qui est soldé.
 *
 * Une comparaison sans hier (0 la veille) n'affiche AUCUN pourcentage : un
 * commerce qui passe de 0 à 1 vente n'a pas progressé de « l'infini », et un
 * faux chiffre précis est pire qu'aucun chiffre.
 */
class OrderStatsService
{
    /**
     * @return array{
     *   currency: string,
     *   revenue: array{value: float, previous: float, change: ?float},
     *   orders: array{value: int, previous: int, change: ?float},
     *   customers: array{value: int, previous: int, change: ?float}
     * }
     */
    public function todayVsYesterday(): array
    {
        $aujourdhui = now()->toDateString();
        $hier = now()->subDay()->toDateString();

        return [
            // La portée est automatique (BelongsToTenant) : ni ici ni dans
            // les requêtes ci-dessous il ne faut filtrer par tenant à la main.
            'currency'  => (string) (Order::whereDate('placed_at', $aujourdhui)->value('currency') ?? 'HTG'),
            'revenue'   => $this->comparer(
                (float) Order::whereDate('placed_at', $aujourdhui)->revenue()->sum('total'),
                (float) Order::whereDate('placed_at', $hier)->revenue()->sum('total')
            ),
            'orders'    => $this->comparer(
                Order::whereDate('placed_at', $aujourdhui)->count(),
                Order::whereDate('placed_at', $hier)->count()
            ),
            'customers' => $this->comparer(
                $this->clientsDistincts($aujourdhui),
                $this->clientsDistincts($hier)
            ),
        ];
    }

    /**
     * Clients distincts d'UN jour, identifiés par fiche client si elle
     * existe, sinon par téléphone. Une commande de passage — sans fiche NI
     * téléphone — n'est comptée pour personne : rapprocher des anonymes les
     * ferait paraître être le même client.
     */
    private function clientsDistincts(string $jour): int
    {
        return (int) Order::whereDate('placed_at', $jour)
            ->where(fn ($q) => $q->whereNotNull('customer_id')->orWhereNotNull('customer_phone'))
            ->selectRaw('COALESCE(customer_id, customer_phone) as qui')
            ->distinct()
            ->count('qui');
    }

    /** @return array{value: int|float, previous: int|float, change: ?float} */
    private function comparer(int|float $auj, int|float $hier): array
    {
        return [
            'value'    => $auj,
            'previous' => $hier,
            // Rien la veille : aucune base pour un pourcentage honnête.
            'change'   => $hier > 0 ? round((($auj - $hier) / $hier) * 100, 1) : null,
        ];
    }
}
