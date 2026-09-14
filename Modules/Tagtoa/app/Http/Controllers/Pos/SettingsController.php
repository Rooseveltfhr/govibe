<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA POS — les réglages de la caisse.
 *
 * Ce qui se règle ICI : les postes de caisse eux-mêmes — leur nom, leur devise,
 * et s'ils servent encore.
 *
 * Ce qui NE se règle PAS ici, volontairement : la taxe et les moyens de
 * paiement. Ils appartiennent au COMMERCE, pas à un poste. Les recopier sur cet
 * écran donnerait deux endroits pour un seul réglage — et le jour où ils
 * divergent, deux caisses du même commerce délivrent des reçus avec des taxes
 * différentes. On mène donc à l'écran qui en est le propriétaire.
 */
class SettingsController extends Controller
{
    public function index(): View
    {
        return view('tagtoa::pos.settings', [
            'terminals'  => Terminal::where('tenant_id', Tenant::id())
                ->withCount('products')->orderBy('id')->get(),
            'currencies' => Money::options(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'currency'  => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $terminal = Terminal::where('tenant_id', Tenant::id())->findOrFail($id);

        $terminal->update([
            'name'      => $data['name'],
            'currency'  => $data['currency'] ?: $terminal->currency,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return back()->with('success', __('Caisse mise à jour.'));
    }
}
