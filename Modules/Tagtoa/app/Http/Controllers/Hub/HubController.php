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
        $tenantId = Tenant::id();

        // Aperçu aligné sur les modules mis en avant, et TOUJOURS limité au
        // marchand courant : ces compteurs étaient calculés sur toute la base,
        // donc chaque marchand voyait les chiffres de la plateforme entière.
        $stats = [
            'menus'       => $this->safeCount(\Modules\Tagtoa\App\Models\Menu\Menu::class, $tenantId),
            'pay_pages'   => $this->safeCount(PaymentPage::class, $tenantId,
                fn ($q) => $q->where('is_library', false)), // page technique : jamais comptée
            'pay_pending' => $this->safeCount(\Modules\Tagtoa\App\Models\Pay\PaymentProof::class, null,
                fn ($q) => $q->where('status', 0)
                    ->whereHas('page', fn ($p) => $p->where('tenant_id', $tenantId))),
            'events'      => $this->safeCount(\Modules\Tagtoa\App\Models\Event\Event::class, $tenantId),
        ];

        // Nouveau marchand = aucune ressource nulle part → hero « Commencer ».
        $isNew = array_sum($stats) === 0
            && $this->safeCount(\Modules\Tagtoa\App\Models\Pos\Terminal::class, $tenantId) === 0;

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

    /**
     * Compte tolérant : selon le déploiement, toutes les tables n'existent pas
     * forcément. `$tenantId` non nul ⇒ le modèle porte une colonne tenant_id et
     * le comptage y est restreint.
     */
    private function safeCount(string $model, ?string $tenantId = null, ?callable $scope = null): int
    {
        try {
            $q = $model::query();
            if ($tenantId !== null) {
                $q->where('tenant_id', $tenantId);
            }
            if ($scope) {
                $scope($q);
            }

            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
