<?php

namespace Modules\Tagtoa\App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Card\CardCredit;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Card\CardCreditService;

/**
 * TAGTOA SUPER-ADMIN — accorder/vendre des crédits d'activation de cartes
 * officielles aux marchands/revendeurs (modèle international sans envoi de matériel).
 */
class CardCreditController extends Controller
{
    public function index(): View
    {
        $credits = CardCredit::orderByDesc('granted')->paginate(50);

        return view('tagtoa::superadmin.card-credits', compact('credits'));
    }

    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'max:191'],
            'qty'       => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        $balance = app(CardCreditService::class)->grant($data['tenant_id'], (int) $data['qty']);
        app(AuditService::class)->log('card.credits_granted', null, $data['tenant_id'].' +'.$data['qty'].' → '.$balance);

        return back()->with('success', __('Crédits accordés. Nouveau solde : :n', ['n' => $balance]));
    }
}
