<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaPaymentMethod;
use App\Models\TaGtoaPaymentPage;
use App\Models\TaGtoaPaymentProof;
use App\Notifications\TaGtoaPayProofReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * TAGTOA PAY — pages publiques.
 *
 * Routes :
 *   GET  /pay/{alias}              -> show
 *   POST /pay/{alias}/submit-proof -> submitProof
 */
class TaGtoaPayController extends Controller
{
    /** Page de paiement publique (standalone HTML, mobile-first). */
    public function show(string $alias): View
    {
        $page = TaGtoaPaymentPage::where('alias', $alias)
            ->where('is_active', true)
            ->with(['activeMethods.media', 'vcard'])
            ->firstOrFail();

        // Compteur de vues léger (pas de table analytics pour rester < 1.5s sur 3G).
        $page->incrementQuietly('views');

        return view('tagtoa.pay.show', [
            'page'    => $page,
            'methods' => $page->activeMethods,
        ]);
    }

    /** Soumission d'une preuve de paiement par le client. */
    public function submitProof(Request $request, string $alias): RedirectResponse
    {
        $page = TaGtoaPaymentPage::where('alias', $alias)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'payment_method_id' => [
                'required',
                'integer',
                // La méthode doit appartenir à CETTE page et être active.
                function ($attr, $value, $fail) use ($page) {
                    $ok = $page->activeMethods()->whereKey($value)->exists();
                    if (! $ok) {
                        $fail(__('Méthode de paiement invalide.'));
                    }
                },
            ],
            'payer_name'  => ['required', 'string', 'max:120'],
            'payer_phone' => ['nullable', 'string', 'max:40'],
            'amount'      => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reference'   => ['nullable', 'string', 'max:120'],
            'proof'       => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $method = TaGtoaPaymentMethod::findOrFail($validated['payment_method_id']);

        if ($method->requires_proof && ! $request->hasFile('proof')) {
            return back()
                ->withInput()
                ->withErrors(['proof' => __('Une preuve de paiement (capture) est requise pour cette méthode.')]);
        }

        $proof = new TaGtoaPaymentProof([
            'payment_page_id'   => $page->id,
            'payment_method_id' => $method->id,
            'payer_name'        => $validated['payer_name'],
            'payer_phone'       => $validated['payer_phone'] ?? null,
            'amount'            => $validated['amount'] ?? null,
            'currency'          => $page->default_currency,
            'reference'         => $validated['reference'] ?? null,
            'status'            => TaGtoaPaymentProof::STATUS_PENDING,
        ]);
        $proof->save();

        if ($request->hasFile('proof')) {
            $proof->addMediaFromRequest('proof')->toMediaCollection('proof-image');
        }

        $this->notifyOwner($page, $proof);

        return redirect()
            ->route('tagtoa.pay.show', $page->alias)
            ->with('proof_submitted', true);
    }

    /** Notifie le propriétaire de la page (DB + email) si on peut résoudre l'utilisateur. */
    protected function notifyOwner(TaGtoaPaymentPage $page, TaGtoaPaymentProof $proof): void
    {
        $owner = optional($page->vcard)->user ?? null;

        if ($owner) {
            $owner->notify(new TaGtoaPayProofReceived($proof));
            return;
        }

        // Fallback : email du vcard si pas de relation user.
        $email = optional($page->vcard)->email;
        if ($email) {
            Notification::route('mail', $email)
                ->notify(new TaGtoaPayProofReceived($proof));
        }
    }
}
