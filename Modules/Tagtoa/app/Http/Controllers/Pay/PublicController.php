<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Notifications\PayProofReceived;

/**
 * TAGTOA Pay — page publique + soumission de preuve.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $page = PaymentPage::where('alias', $alias)->where('is_active', true)
            ->with(['activeMethods', 'vcard'])->firstOrFail();

        $page->incrementQuietly('views');

        return view('tagtoa::pay.show', ['page' => $page, 'methods' => $page->activeMethods]);
    }

    /**
     * Paiement en ligne via passerelle API (PayPal, CoinPayments, Stripe…).
     * Tant qu'aucun driver n'est branché, on retombe proprement sur le manuel.
     */
    public function checkout(Request $request, string $alias, int $method): RedirectResponse
    {
        $page = PaymentPage::where('alias', $alias)->where('is_active', true)->firstOrFail();
        $m = $page->activeMethods()->whereKey($method)->firstOrFail();

        $gateway = \Modules\Tagtoa\App\Support\PaymentGateway::driver($m->type);
        $amount = round((float) $request->input('amount', 0), 2);

        // Passerelle non branchée/non configurée ou montant absent → repli manuel propre.
        if (! $gateway || ! \Modules\Tagtoa\App\Support\GatewayManager::enabled($gateway) || $amount <= 0) {
            return redirect()->route('tagtoa.pay.show', $page->alias)
                ->with('error', __('Le paiement en ligne n\'est pas disponible pour le moment. Utilisez les informations ci-dessous.'));
        }

        $url = app(\Modules\Tagtoa\App\Services\Pay\CheckoutService::class)->startPayPage($page, $m, $gateway, $amount, [
            'name'  => (string) $request->input('payer_name', ''),
            'phone' => (string) $request->input('payer_phone', ''),
        ]);

        if (! $url) {
            return redirect()->route('tagtoa.pay.show', $page->alias)
                ->with('error', __('Le paiement en ligne n\'a pas pu démarrer. Réessayez ou utilisez les informations ci-dessous.'));
        }

        return redirect()->away($url);
    }

    /**
     * Paiement par CARTE TAGTOA (closed-loop) : le payeur tape sa carte NFC
     * (UID) ou saisit son code + PIN + montant → débit instantané du solde.
     * Aucune passerelle externe : l'argent reste dans TAGTOA.
     */
    public function cardCharge(Request $request, string $alias): RedirectResponse
    {
        $page = PaymentPage::where('alias', $alias)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'card_uid'  => ['nullable', 'string', 'max:120'],
            'card_code' => ['nullable', 'string', 'max:40'],
            'pin'       => ['nullable', 'string', 'max:6'],
            'amount'    => ['required', 'numeric', 'min:0.01', 'max:99999999'],
        ]);

        if (empty($data['card_uid']) && empty($data['card_code'])) {
            return back()->withInput()->with('error', __('Tapez la carte ou saisissez son code.'));
        }

        $svc = app(\Modules\Tagtoa\App\Services\Card\CardWalletService::class);
        $card = ! empty($data['card_uid'])
            ? $svc->resolveByUid($data['card_uid'])
            : $svc->resolveByCode($data['card_code']);

        if (! $card) {
            return back()->withInput()->with('error', __('Carte TAGTOA introuvable.'));
        }

        $res = $svc->charge($card, (float) $data['amount'], $data['pin'] ?? null, [
            'tenant_id'    => $page->tenant_id,
            'context_type' => 'pay_page',
            'context_id'   => $page->id,
            'module'       => 'pay',
        ]);

        if (! $res['ok']) {
            return back()->withInput()->with('error', $res['message']);
        }

        // Trace la recette pour le marchand (preuve APPROUVÉE au tableau de bord).
        \Modules\Tagtoa\App\Models\Pay\PaymentProof::firstOrCreate(
            ['reference' => $res['txn']->reference, 'payment_page_id' => $page->id],
            [
                'payment_method_id' => $page->activeMethods()->where('type', 'tagtoa_card')->value('id'),
                'payer_name'        => $card->holder_name ?: __('Carte TAGTOA'),
                'payer_phone'       => $card->holder_phone,
                'amount'            => (float) $data['amount'],
                'currency'          => $card->currency,
                'status'            => \Modules\Tagtoa\App\Models\Pay\PaymentProof::STATUS_APPROVED,
                'note'              => __('Payé par Carte TAGTOA').' ('.$card->masked_code.')',
                'reviewed_at'       => now(),
            ]
        );

        return redirect()->route('tagtoa.pay.show', $page->alias)
            ->with('proof_submitted', true)
            ->with('card_paid', __('Paiement accepté. Nouveau solde : :bal', ['bal' => $card->fresh()->balance_label]));
    }

    public function submitProof(Request $request, string $alias): RedirectResponse
    {
        $page = PaymentPage::where('alias', $alias)->where('is_active', true)->firstOrFail();

        $data = $request->validate([
            'payment_method_id' => [
                'required', 'integer',
                fn ($a, $v, $fail) => $page->activeMethods()->whereKey($v)->exists() ?: $fail(__('Méthode invalide.')),
            ],
            'payer_name'  => ['required', 'string', 'max:120'],
            'payer_phone' => ['nullable', 'string', 'max:40'],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reference'   => ['nullable', 'string', 'max:120'],
            'proof'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $method = PaymentMethod::findOrFail($data['payment_method_id']);
        if ($method->requires_proof && ! $request->hasFile('proof')) {
            return back()->withInput()->withErrors(['proof' => __('Une preuve (capture) est requise pour cette méthode.')]);
        }

        // Disque PRIVÉ (pas 'public') : une preuve de paiement ne doit pas être
        // accessible par URL. Elle est servie via une route authentifiée+scopée.
        $path = $request->hasFile('proof')
            ? $request->file('proof')->store('tagtoa/pay-proofs')
            : null;

        $proof = PaymentProof::create([
            'payment_page_id'   => $page->id,
            'payment_method_id' => $method->id,
            'payer_name'        => $data['payer_name'],
            'payer_phone'       => $data['payer_phone'] ?? null,
            'amount'            => $data['amount'] ?? null,
            'currency'          => $page->default_currency,
            'reference'         => $data['reference'] ?? null,
            'proof_path'        => $path,
            'status'            => PaymentProof::STATUS_PENDING,
        ]);

        $this->notifyOwner($page, $proof);

        return redirect()->route('tagtoa.pay.show', $page->alias)->with('proof_submitted', true);
    }

    protected function notifyOwner(PaymentPage $page, PaymentProof $proof): void
    {
        $owner = optional($page->vcard)->user ?? null;
        if ($owner) {
            $owner->notify(new PayProofReceived($proof));
            return;
        }
        if ($email = optional($page->vcard)->email) {
            Notification::route('mail', $email)->notify(new PayProofReceived($proof));
        }
    }
}
