<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Services\Inventory\BatchService;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\GuardsStaffAbility;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — les lots et leur péremption.
 *
 * Écran à part, comme les rayons ou les retours : une pharmacie y voit d'un
 * coup d'œil ce qui périme bientôt, ce qui doit être écoulé en priorité.
 * Pas de numéro de caisse dans ces routes — un lot appartient au COMMERCE,
 * comme le catalogue lui-même.
 */
class BatchController extends Controller
{
    use GuardsStaffAbility;

    public function __construct(protected BatchService $lots)
    {
    }

    public function index(): View
    {
        return view('tagtoa::pos.lots', [
            'expirant'  => $this->lots->expiringWithin(30),
            'tous'      => $this->lots->all(),
            'produits'  => app(PosCatalog::class)->active(Tenant::id()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->denyUnless(app(StaffService::class)->currentAny(), 'catalog.edit');

        $data = $request->validate([
            'product_id'  => ['required', 'integer'],
            'quantity'    => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'expires_at'  => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'note'        => ['nullable', 'string', 'max:160'],
        ]);

        // Un article de CE commerce, jamais un identifiant deviné.
        $product = app(PosCatalog::class)->find(Tenant::id(), (int) $data['product_id']);
        abort_unless($product, 404);

        $this->lots->receive($product, (float) $data['quantity'], [
            'expires_at'  => $data['expires_at'] ?? null,
            'received_at' => $data['received_at'] ?? null,
            'note'        => trim((string) ($data['note'] ?? '')) ?: null,
        ]);

        return back()->with('success', __('Lot enregistré pour « :nom ».', ['nom' => $product->name]));
    }
}
