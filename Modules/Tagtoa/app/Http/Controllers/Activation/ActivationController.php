<?php

namespace Modules\Tagtoa\App\Http\Controllers\Activation;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Stand\StandActivator;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — « Activer un produit », le premier geste quand un carton arrive.
 *
 * Avant cet écran, un marchand devait déjà SAVOIR si ce qu'il tient est un
 * Smart Stand ou une Carte TAGTOA pour trouver le bon menu — et pour un
 * Stand, l'écran d'activation en série n'a jamais laissé dire à quoi il sert
 * (Menu, Paiement, Liens) : il envoyait toujours `target_module=menu`, quel
 * que soit le produit réellement déballé.
 *
 * Cet écran ne réimplémente RIEN : il pose une seule question de plus (« que
 * tenez-vous ? ») puis achemine vers les DEUX services existants, inchangés —
 * StandActivator (via la même route `tagtoa.stand.activate.scan`) et
 * CardWalletService (via la même route `tagtoa.cards.store`).
 */
class ActivationController extends Controller
{
    public function __construct(protected StandActivator $activator)
    {
    }

    public function screen(): View
    {
        $tenantId = Tenant::id();

        return view('tagtoa::activation.hub', [
            'nextLabel'  => $this->activator->nextLabel($tenantId),
            'prefix'     => StandActivator::DEFAULT_PREFIX,
            'currencies' => Money::options(),
        ]);
    }
}
