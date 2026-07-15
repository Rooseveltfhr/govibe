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
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
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
            ->withCount(['methods', 'proofs'])
            ->latest()->paginate(12);

        return view('tagtoa::pay.dashboard.index', compact('pages'));
    }

    public function create(): View
    {
        return view('tagtoa::pay.dashboard.form', [
            'page'    => new PaymentPage(),
            'vcards'  => $this->vcards(),
            'methods' => PaymentPage::METHODS,
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
        $this->syncMethods($page, $request);

        return redirect()->route('tagtoa.pay.dashboard.edit', $page->id)->with('success', __('Page créée.'));
    }

    public function edit(int $id): View
    {
        $page = $this->ownPage($id, ['methods']);

        return view('tagtoa::pay.dashboard.form', [
            'page'    => $page,
            'vcards'  => $this->vcards(),
            'methods' => PaymentPage::METHODS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = $this->ownPage($id);
        $data = $this->validatePage($request, $page->id);
        $data['alias'] = $data['alias'] ?: $page->alias;
        $page->update($data);
        $this->syncMethods($page, $request);

        return back()->with('success', __('Page mise à jour.'));
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

        return back()->with('success', $msg);
    }

    protected function ownPage(int $id, array $with = []): PaymentPage
    {
        return PaymentPage::with($with)->where('tenant_id', Tenant::id())->findOrFail($id);
    }

    protected function validatePage(Request $request, ?int $ignoreId = null): array
    {
        $ownVcardIds = $this->vcards()->pluck('id')->all();

        return $request->validate([
            'vcard_id'         => ['nullable', 'integer', Rule::in($ownVcardIds)],
            'title'            => ['nullable', 'string', 'max:160'],
            'alias'            => ['nullable', 'string', 'max:120', 'alpha_dash',
                                   'unique:tagtoa_payment_pages,alias'.($ignoreId ? ','.$ignoreId : '')],
            'description'      => ['nullable', 'string', 'max:1000'],
            'default_currency' => ['nullable', 'string', 'max:10'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }

    protected function syncMethods(PaymentPage $page, Request $request): void
    {
        $rows = $request->input('methods', []);
        $keep = [];

        DB::transaction(function () use ($page, $rows, $request, &$keep) {
            foreach ($rows as $i => $row) {
                if (empty($row['type'])) {
                    continue;
                }
                $attrs = [
                    'type'           => $row['type'],
                    'label'          => $row['label'] ?? null,
                    'account_holder' => $row['account_holder'] ?? null,
                    'institution'    => $row['institution'] ?? null,
                    'account_number' => $row['account_number'] ?? null,
                    'instructions'   => $row['instructions'] ?? null,
                    'requires_proof' => ! empty($row['requires_proof']),
                    'is_active'      => ! empty($row['is_active']),
                    'sort'           => (int) ($row['sort'] ?? $i),
                ];
                $m = ! empty($row['id']) ? $page->methods()->whereKey($row['id'])->first() : null;
                $m ? $m->update($attrs) : $m = $page->methods()->create($attrs);

                if ($request->hasFile("methods.$i.qr")) {
                    $m->update(['qr_path' => $request->file("methods.$i.qr")->store('tagtoa/pay-qr', 'public')]);
                }
                if ($request->hasFile("methods.$i.logo")) {
                    $m->update(['logo_path' => $request->file("methods.$i.logo")->store('tagtoa/pay-logos', 'public')]);
                }
                $keep[] = $m->id;
            }
            $page->methods()->whereNotIn('id', $keep ?: [0])->delete();
        });
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
