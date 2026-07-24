<?php

namespace Modules\Tagtoa\App\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — accueil (hub) du dashboard : aperçu + accès aux modules.
 */
class HubController extends Controller
{
    public function index(): View
    {
        // Aperçu léger et tolérant (les tables peuvent ne pas toutes exister selon le déploiement).
        $stats = [
            'pay_pages'    => $this->safeCount(PaymentPage::class),
            'pay_pending'  => $this->safeCount(\Modules\Tagtoa\App\Models\Pay\PaymentProof::class, fn ($q) => $q->where('status', 0)),
            'loyalty_cards'=> $this->safeCount(\Modules\Tagtoa\App\Models\Loyalty\Card::class),
            'events'       => $this->safeCount(\Modules\Tagtoa\App\Models\Event\Event::class),
        ];

        // Nouveau marchand = aucune ressource nulle part → hero « Commencer ».
        $isNew = array_sum($stats) === 0
            && $this->safeCount(\Modules\Tagtoa\App\Models\Menu\Menu::class) === 0
            && $this->safeCount(\Modules\Tagtoa\App\Models\Links\LinkPage::class) === 0
            && $this->safeCount(\Modules\Tagtoa\App\Models\Site\Site::class) === 0;

        // Le fondateur (super_admin) voit un accès au panneau plateforme.
        $isSuperAdmin = false;
        try {
            $u = Tenant::user() ?: auth()->user();
            $isSuperAdmin = $u && method_exists($u, 'hasRole') && $u->hasRole('super_admin');
        } catch (\Throwable $e) {
            // rôle indisponible → pas de lien (comportement sûr)
        }

        return view('tagtoa::hub.index', compact('stats', 'isNew', 'isSuperAdmin'));
    }

    private function safeCount(string $model, ?callable $scope = null): int
    {
        try {
            $q = $model::query();
            if ($scope) {
                $scope($q);
            }
            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
