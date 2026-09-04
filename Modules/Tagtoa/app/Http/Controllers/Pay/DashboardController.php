<?php

namespace Modules\Tagtoa\App\Http\Controllers\Pay;

use App\Http\Controllers\Controller;
use Modules\Tagtoa\App\Support\EnforcesPlan;
use App\Models\Vcard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Services\Api\ApiPaymentService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA Pay — dashboard propriétaire (CRUD pages + méthodes + preuves).
 */
class DashboardController extends Controller
{
    use EnforcesPlan;

    public function index(): View
    {
        $pages = PaymentPage::where('tenant_id', Tenant::id())
            ->where('is_library', false) // page technique : jamais listée
            ->withCount('proofs')
            ->latest()->paginate(12);

        return view('tagtoa::pay.dashboard.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa::pay.dashboard.form', [
            'page'   => new PaymentPage(['type' => PaymentPage::TYPE_INVOICE]),
            'vcards' => $this->vcards(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        
        if ($r = $this->planGuard('pay')) {
            return $r;
        }
$data = $this->validatePage($request);
        $page = new PaymentPage($data);
        $page->tenant_id = Tenant::id();
        $page->alias = $data['alias'] ?: PaymentPage::generateAlias($data['title'] ?? 'pay');
        $page->save();

        return redirect()->route('tagtoa.pay.dashboard.share', $page->id)
            ->with('success', __('Lien créé. Partagez-le à votre client.'));
    }

    public function edit(int $id): View
    {
        return view('tagtoa::pay.dashboard.form', [
            'page'   => $this->ownPage($id),
            'vcards' => $this->vcards(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = $this->ownPage($id);
        $data = $this->validatePage($request, $page->id);
        $data['alias'] = $data['alias'] ?: $page->alias;
        $page->update($data);

        return back()->with('success', __('Lien mis à jour.'));
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->ownPage($id)->delete();

        return redirect()->route('tagtoa.pay.dashboard.index')->with('success', __('Page supprimée.'));
    }

    public function proofs(int $id): View
    {
        $page   = $this->ownPage($id);
        $proofs = $page->proofs()->with('method')->paginate(20);

        return view('tagtoa::pay.dashboard.proofs', compact('page', 'proofs'));
    }

    /** Sert l'image d'une preuve — UNIQUEMENT au tenant propriétaire (disque privé). */
    public function proofImage(int $id): StreamedResponse
    {
        $proof = PaymentProof::whereHas('page', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
        abort_unless($proof->proof_path, 404);

        // Fichier sur le disque privé (nouvelles preuves) ; repli sur 'public'
        // pour d'éventuelles preuves héritées de l'ancien stockage.
        $disk = \Illuminate\Support\Facades\Storage::disk('local')->exists($proof->proof_path) ? 'local' : 'public';
        abort_unless(\Illuminate\Support\Facades\Storage::disk($disk)->exists($proof->proof_path), 404);

        return \Illuminate\Support\Facades\Storage::disk($disk)->response($proof->proof_path);
    }

    public function approveProof(int $id): RedirectResponse
    {
        return $this->review($id, PaymentProof::STATUS_APPROVED, __('Preuve approuvée.'));
    }

    public function rejectProof(Request $request, int $id): RedirectResponse
    {
        return $this->review($id, PaymentProof::STATUS_REJECTED, __('Preuve rejetée.'), $request->input('note'));
    }

    /* ----------------------------------------------------------------- helpers */

    protected function review(int $id, int $status, string $msg, ?string $note = null): RedirectResponse
    {
        $proof = PaymentProof::whereHas('page', fn ($q) => $q->where('tenant_id', Tenant::id()))->findOrFail($id);
        $proof->update(['status' => $status, 'note' => $note ?? $proof->note, 'reviewed_at' => now()]);

        // Preuve issue d'un paiement API : approuver la preuve fait passer le
        // paiement du site tiers en « payé » et déclenche sa notification.
        if ($status === PaymentProof::STATUS_APPROVED && $proof->api_payment_id) {
            $payment = ApiPayment::find($proof->api_payment_id);
            if ($payment) {
                app(ApiPaymentService::class)->markPaid($payment);
            }
        }

        return back()->with('success', $msg);
    }

    protected function ownPage(int $id, array $with = []): PaymentPage
    {
        return PaymentPage::with($with)->where('tenant_id', Tenant::id())
            ->where('is_library', false)->findOrFail($id);
    }

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        $ownVcardIds = $this->vcards()->pluck('id')->all();

        return $request->validate([
            'vcard_id'         => ['nullable', 'integer', Rule::in($ownVcardIds)],
            'title'            => ['nullable', 'string', 'max:160'],
            'type'             => ['nullable', Rule::in(array_keys(PaymentPage::TYPES))],
            'alias'            => ['nullable', 'string', 'max:120', 'alpha_dash',
                                   'unique:tagtoa_payment_pages,alias'.($ignoreId ? ','.$ignoreId : '')],
            'description'      => ['nullable', 'string', 'max:1000'],
            // Devise contrainte au catalogue : une devise inconnue casserait
            // le formatage des montants sur la page publique.
            'default_currency' => ['nullable', Rule::in(array_keys(\Modules\Tagtoa\App\Support\Money::currencies()))],
            'amount'           => ['nullable', 'numeric', 'min:0', 'max:99999999'], // prix fixe (vide = libre)
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }

    /** Écran de partage d'un lien : copier, WhatsApp, QR. */
    public function share(int $id): View
    {
        return view('tagtoa::pay.dashboard.share', ['page' => $this->ownPage($id)]);
    }

    protected function vcards()
    {
        try {
            return Vcard::query()->where('tenant_id', Tenant::id())->orderBy('name')->get(['id', 'name']);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
