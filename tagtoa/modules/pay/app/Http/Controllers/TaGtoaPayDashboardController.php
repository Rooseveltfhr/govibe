<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaPaymentMethod;
use App\Models\TaGtoaPaymentPage;
use App\Models\TaGtoaPaymentProof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * TAGTOA PAY — dashboard propriétaire (back-office).
 *
 * Toutes les requêtes sont scopées au tenant courant via BelongsToTenant
 * sur TaGtoaPaymentPage, donc un owner ne voit que ses pages.
 */
class TaGtoaPayDashboardController extends Controller
{
    public function index(): View
    {
        $pages = TaGtoaPaymentPage::withCount(['methods', 'pendingProofs'])
            ->latest()
            ->paginate(15);

        return view('tagtoa.pay.dashboard.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa.pay.dashboard.form', [
            'page'           => new TaGtoaPaymentPage(),
            'vcards'         => $this->ownerVcards(),
            'paymentMethods' => TaGtoaPaymentPage::PAYMENT_METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePage($request);

        $page = new TaGtoaPaymentPage($data);
        $page->tenant_id = getLogInTenantId();
        $page->alias     = $data['alias'] ?: TaGtoaPaymentPage::generateAlias($data['title'] ?? 'pay');
        $page->save();

        $this->syncMethods($page, $request);

        return redirect()
            ->route('tagtoa.pay.dashboard.edit', $page->id)
            ->with('success', __('Page de paiement créée.'));
    }

    public function edit(int $id): View
    {
        $page = TaGtoaPaymentPage::with('methods.media')->findOrFail($id);

        return view('tagtoa.pay.dashboard.form', [
            'page'           => $page,
            'vcards'         => $this->ownerVcards(),
            'paymentMethods' => TaGtoaPaymentPage::PAYMENT_METHODS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = TaGtoaPaymentPage::findOrFail($id);
        $data = $this->validatePage($request, $page->id);

        // L'alias ne peut être vidé : on garde l'ancien s'il n'est pas fourni.
        $data['alias'] = $data['alias'] ?: $page->alias;
        $page->update($data);

        $this->syncMethods($page, $request);

        return redirect()
            ->route('tagtoa.pay.dashboard.edit', $page->id)
            ->with('success', __('Page mise à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        TaGtoaPaymentPage::findOrFail($id)->delete();

        return redirect()
            ->route('tagtoa.pay.dashboard.index')
            ->with('success', __('Page supprimée.'));
    }

    /* ----------------------------------------------------------------- Proofs */

    public function proofs(int $pageId): View
    {
        $page   = TaGtoaPaymentPage::findOrFail($pageId);
        $proofs = $page->proofs()->with('method')->paginate(20);

        return view('tagtoa.pay.dashboard.proofs', compact('page', 'proofs'));
    }

    public function approveProof(int $id): RedirectResponse
    {
        return $this->reviewProof($id, TaGtoaPaymentProof::STATUS_APPROVED, __('Preuve approuvée.'));
    }

    public function rejectProof(Request $request, int $id): RedirectResponse
    {
        return $this->reviewProof(
            $id,
            TaGtoaPaymentProof::STATUS_REJECTED,
            __('Preuve rejetée.'),
            $request->input('note')
        );
    }

    /* ----------------------------------------------------------------- Helpers */

    protected function reviewProof(int $id, int $status, string $message, ?string $note = null): RedirectResponse
    {
        // On s'assure que la preuve appartient à une page du tenant courant.
        $proof = TaGtoaPaymentProof::whereHas('page')->findOrFail($id);

        $proof->update([
            'status'      => $status,
            'note'        => $note ?? $proof->note,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', $message);
    }

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'vcard_id'         => ['required', 'integer', 'exists:vcards,id'],
            'title'            => ['nullable', 'string', 'max:160'],
            'alias'            => [
                'nullable', 'string', 'max:120', 'alpha_dash',
                'unique:tagtoa_payment_pages,alias' . ($ignoreId ? ',' . $ignoreId : ''),
            ],
            'description'      => ['nullable', 'string', 'max:1000'],
            'default_currency' => ['nullable', 'string', 'max:10'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Synchronise les méthodes envoyées par le formulaire (tableau `methods[]`).
     * Gère création, mise à jour, suppression et upload du QR par méthode.
     */
    protected function syncMethods(TaGtoaPaymentPage $page, Request $request): void
    {
        $rows    = $request->input('methods', []);
        $keepIds = [];

        DB::transaction(function () use ($page, $rows, $request, &$keepIds) {
            foreach ($rows as $i => $row) {
                if (empty($row['type'])) {
                    continue;
                }

                $attributes = [
                    'type'           => $row['type'],
                    'label'          => $row['label'] ?? null,
                    'account_holder' => $row['account_holder'] ?? null,
                    'account_number' => $row['account_number'] ?? null,
                    'instructions'   => $row['instructions'] ?? null,
                    'requires_proof' => ! empty($row['requires_proof']),
                    'is_active'      => ! empty($row['is_active']),
                    'sort'           => (int) ($row['sort'] ?? $i),
                ];

                $method = ! empty($row['id'])
                    ? $page->methods()->whereKey($row['id'])->first()
                    : null;

                if ($method) {
                    $method->update($attributes);
                } else {
                    $method = $page->methods()->create($attributes);
                }

                // Upload QR éventuel : champ file `methods[$i][qr]`.
                if ($request->hasFile("methods.$i.qr")) {
                    $method->clearMediaCollection('payment-qr');
                    $method->addMediaFromRequest("methods.$i.qr")->toMediaCollection('payment-qr');
                }

                $keepIds[] = $method->id;
            }

            // Supprime les méthodes retirées du formulaire.
            $page->methods()->whereNotIn('id', $keepIds ?: [0])->delete();
        });
    }

    /** Vcards appartenant à l'utilisateur courant (scopés tenant). */
    protected function ownerVcards()
    {
        return \App\Models\Vcard::query()
            ->orderBy('name')
            ->get(['id', 'name', 'urlAlias']);
    }
}
