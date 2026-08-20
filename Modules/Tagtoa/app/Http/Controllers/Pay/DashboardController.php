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
use Modules\Tagtoa\App\Support\Pay\GatewayCatalog;
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
            'catalog' => GatewayCatalog::split(GatewayCatalog::forMerchant()),
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
            'catalog' => GatewayCatalog::split(GatewayCatalog::forMerchant()),
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
            'amount'           => ['nullable', 'numeric', 'min:0', 'max:99999999'], // prix fixe (vide = libre)
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Synchronise les méthodes depuis le CATALOGUE (formulaire indexé par clé de
     * passerelle : methods[moncash][…], pas par index numérique).
     *
     * Sécurité : toute entrée est validée. Les clés inconnues du catalogue sont
     * écartées AVANT toute écriture (le `type` n'est donc jamais une chaîne
     * libre), et les images sont contraintes en type + taille.
     *
     * Désactiver une passerelle ne supprime PAS la ligne : on passe `is_active`
     * à faux et le marchand retrouve ses coordonnées s'il la réactive.
     */
    protected function syncMethods(PaymentPage $page, Request $request): void
    {
        $catalog = GatewayCatalog::forMerchant();
        $order   = array_flip(array_keys($catalog));

        $rows = $request->input('methods', []);
        if (! is_array($rows)) {
            return;
        }
        // Anti-injection : on ne garde que des passerelles réellement au catalogue.
        $rows = array_intersect_key($rows, $catalog);

        $rules = [];
        foreach (array_keys($rows) as $type) {
            $rules["methods.$type.label"]          = ['nullable', 'string', 'max:120'];
            $rules["methods.$type.account_holder"] = ['nullable', 'string', 'max:160'];
            $rules["methods.$type.institution"]    = ['nullable', 'string', 'max:160'];
            $rules["methods.$type.account_number"] = ['nullable', 'string', 'max:190'];
            $rules["methods.$type.instructions"]   = ['nullable', 'string', 'max:1000'];
            $rules["methods.$type.qr"]             = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'];
            $rules["methods.$type.logo"]           = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'];
        }
        if ($rules) {
            $request->validate($rules);
        }

        DB::transaction(function () use ($page, $rows, $request, $catalog, $order) {
            $existing = $page->methods()->get()->keyBy('type');

            foreach ($rows as $type => $row) {
                $row     = is_array($row) ? $row : [];
                $meta    = $catalog[$type];
                $enabled = ! empty($row['enabled']);
                $current = $existing->get($type);

                $hasDetails = filled($row['account_number'] ?? null)
                    || filled($row['account_holder'] ?? null)
                    || filled($row['instructions'] ?? null)
                    || $request->hasFile("methods.$type.qr");

                // Jamais activée, aucune coordonnée saisie, aucune ligne existante :
                // inutile de créer une ligne vide.
                if (! $enabled && ! $current && ! $hasDetails) {
                    continue;
                }

                $attrs = [
                    'type'           => $type,
                    'label'          => $row['label'] ?? null,
                    'account_holder' => $row['account_holder'] ?? null,
                    'institution'    => $row['institution'] ?? null,
                    'account_number' => $row['account_number'] ?? null,
                    'instructions'   => $row['instructions'] ?? null,
                    // Une passerelle réellement branchée en API encaisse toute seule :
                    // pas de preuve à réclamer au client. Sinon, preuve obligatoire.
                    'requires_proof' => ! $meta['online_ready'],
                    'is_active'      => $enabled,
                    'sort'           => (int) ($order[$type] ?? 0),
                ];

                $current ? $current->update($attrs) : $current = $page->methods()->create($attrs);

                if ($request->hasFile("methods.$type.qr")) {
                    $current->update(['qr_path' => $request->file("methods.$type.qr")->store('tagtoa/pay-qr', 'public')]);
                }
                if ($request->hasFile("methods.$type.logo")) {
                    $current->update(['logo_path' => $request->file("methods.$type.logo")->store('tagtoa/pay-logos', 'public')]);
                }
            }

            // Purge les lignes dont la passerelle a disparu du registre.
            $page->methods()->whereNotIn('type', array_keys($catalog))->delete();
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
