<?php

namespace Modules\Tagtoa\App\Http\Controllers\Card;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Card\CardAccount;
use Modules\Tagtoa\App\Services\Audit\AuditService;
use Modules\Tagtoa\App\Services\Billing\PlanService;
use Modules\Tagtoa\App\Services\Card\CardCreditService;
use Modules\Tagtoa\App\Services\Card\CardWalletService;
use Modules\Tagtoa\App\Support\Card\CardWallet;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use Modules\Tagtoa\App\Support\Money;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA CARD — gestion des cartes NFC prépayées closed-loop (émission,
 * recharge, blocage, historique). Scopé au marchand courant.
 * L'émission/activation de cartes est réservée aux forfaits qui l'autorisent
 * (Enterprise / Revendeur / Franchise) via EnforcesPlan.
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $query = CardAccount::where('tenant_id', Tenant::id());
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'like', '%'.strtoupper($q).'%')
                    ->orWhere('holder_name', 'like', '%'.$q.'%')
                    ->orWhere('holder_phone', 'like', '%'.$q.'%');
            });
        }

        $cards = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        // Solde total en circulation (par devise).
        $totals = CardAccount::where('tenant_id', Tenant::id())
            ->selectRaw('currency, SUM(balance_minor) as bal, COUNT(*) as n')
            ->groupBy('currency')->get()
            ->map(fn ($r) => [
                'currency' => $r->currency,
                'label'    => Money::formatMinor((int) $r->bal, $r->currency),
                'count'    => (int) $r->n,
            ]);

        return view('tagtoa::card.index', [
            'cards'      => $cards,
            'totals'     => $totals,
            'q'          => $q,
            'currencies' => Money::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Quota d'activation = limite du forfait (Enterprise/Revendeur/Franchise)
        // + crédits d'activation achetés. Sans quota → on oriente vers le forfait.
        $tid       = Tenant::id();
        $plans     = app(PlanService::class);
        $planLimit = $plans->limit($tid, 'cards');
        $usage     = $plans->usage($tid, 'cards');
        $credits   = app(CardCreditService::class)->available($tid);

        if (! CardWallet::canActivate($planLimit, $credits, $usage)) {
            return redirect()->route('tagtoa.plan.index')->with('error', __('Quota de cartes atteint. Passez à un forfait Revendeur/Franchise ou obtenez des crédits d\'activation.'));
        }

        $data = $request->validate([
            'card_uid'       => ['required', 'string', 'max:120'],
            'holder_name'    => ['nullable', 'string', 'max:120'],
            'holder_phone'   => ['nullable', 'string', 'max:40'],
            'currency'       => ['required', 'string', 'size:3'],
            // PIN OBLIGATOIRE : une carte de paiement porte de l'argent → un UID
            // cloné seul reste inutilisable sans le PIN (anti-clone des tags simples).
            'pin'            => ['required', 'regex:/^\d{4,6}$/'],
            'initial_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ]);

        $svc = app(CardWalletService::class);
        $card = $svc->issue($data['card_uid'], [
            'tenant_id'    => Tenant::id(),
            'holder_name'  => $data['holder_name'] ?? null,
            'holder_phone' => $data['holder_phone'] ?? null,
            'currency'     => strtoupper($data['currency']),
            'pin'          => $data['pin'] ?? null,
        ]);

        // Carte nouvellement activée → officielle + consomme un crédit si l'on
        // dépasse le quota de base du forfait (idempotent : re-tap = pas de doublon).
        if ($card->wasRecentlyCreated) {
            if (CardWallet::consumesCredit($planLimit, $usage)) {
                app(CardCreditService::class)->consume($tid, 1);
            }
            $card->update(['official' => true, 'activated_at' => now()]);
        }

        if (! empty($data['initial_amount']) && (float) $data['initial_amount'] > 0) {
            $svc->topUp($card, (float) $data['initial_amount'], [
                'tenant_id' => Tenant::id(), 'context_type' => 'issue', 'meta' => ['reason' => 'initial'],
            ]);
        }

        app(AuditService::class)->log('card.issued', $card, $card->masked_code);

        return back()->with('success', __('Carte officielle activée : :code', ['code' => $card->code]));
    }

    public function topUp(Request $request, CardAccount $card): RedirectResponse
    {
        $this->authorizeCard($card);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        $txn = app(CardWalletService::class)->topUp($card, (float) $data['amount'], [
            'tenant_id' => Tenant::id(), 'context_type' => 'dashboard',
        ]);

        if ($txn) {
            app(AuditService::class)->log('card.top_up', $card, Money::formatMinor((int) $txn->amount_minor, $card->currency));
        }

        return back()->with('success', __('Carte rechargée. Nouveau solde : :bal', ['bal' => $card->fresh()->balance_label]));
    }

    public function setStatus(Request $request, CardAccount $card): RedirectResponse
    {
        $this->authorizeCard($card);
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', CardWallet::STATUSES)],
        ]);

        app(CardWalletService::class)->setStatus($card, $data['status']);
        app(AuditService::class)->log('card.status', $card, $data['status']);

        return back()->with('success', __('Statut de la carte mis à jour.'));
    }

    public function show(CardAccount $card): View
    {
        $this->authorizeCard($card);
        $txns = $card->txns()->paginate(40);

        return view('tagtoa::card.show', compact('card', 'txns'));
    }

    /**
     * Carte physique imprimable, brandée TAGTOA (à imprimer/coller localement —
     * la marque voyage numériquement, sans envoi de matériel). QR vers la recharge.
     */
    public function printCard(CardAccount $card): View
    {
        $this->authorizeCard($card);
        $rechargeUrl = route('tagtoa.card.recharge').'?code='.urlencode($card->code);
        $qr = \Modules\Tagtoa\App\Support\Qr::svg($rechargeUrl, 200);

        return view('tagtoa::card.print', compact('card', 'rechargeUrl', 'qr'));
    }

    protected function authorizeCard(CardAccount $card): void
    {
        abort_unless($card->tenant_id === Tenant::id(), 403);
    }
}
