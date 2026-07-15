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
    public function checkout(string $alias, int $method): RedirectResponse
    {
        $page = PaymentPage::where('alias', $alias)->where('is_active', true)->firstOrFail();
        $m = $page->activeMethods()->whereKey($method)->firstOrFail();

        // Driver pas encore branché → retour à la page avec instructions manuelles.
        return redirect()->route('tagtoa.pay.show', $page->alias)
            ->with('proof_submitted', false)
            ->with('error', __('Le paiement en ligne arrive bientôt. Utilisez les informations ci-dessous.'));
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
