<?php

namespace App\Http\Controllers\ERP\CRM;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->get('status', 'all');
        $query  = Contract::with(['client', 'template', 'createdBy'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $contracts = $query->paginate(25)->withQueryString();

        $stats = [
            'total'    => Contract::count(),
            'active'   => Contract::where('status', 'active')->count(),
            'draft'    => Contract::where('status', 'draft')->count(),
            'signed'   => Contract::whereNotNull('signed_at')->count(),
        ];

        $templates = ContractTemplate::where('is_active', true)->orderBy('category')->get();

        return view('erp.contracts.index', compact('contracts', 'stats', 'status', 'templates'));
    }

    public function create(Request $request): View
    {
        $templates = ContractTemplate::where('is_active', true)->orderBy('category')->get();
        $clients   = Client::orderBy('name')->get(['id', 'name', 'type', 'email']);
        $template  = $request->filled('template_id')
            ? ContractTemplate::findOrFail($request->template_id)
            : null;

        return view('erp.contracts.create', compact('templates', 'clients', 'template'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'   => ['required', 'exists:clients,id'],
            'template_id' => ['nullable', 'exists:contract_templates,id'],
            'title'       => ['required', 'string', 'max:255'],
            'content'     => ['required', 'string'],
            'start_date'  => ['required', 'date'],
            'end_date'    => ['nullable', 'date'],
            'value'       => ['nullable', 'numeric', 'min:0'],
            'status'      => ['required', 'in:draft,active'],
        ]);

        $ref = 'CTR-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));

        $contract = Contract::create([
            ...$data,
            'reference'  => $ref,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('erp.contracts.show', $contract)
            ->with('success', "Contrat {$ref} créé avec succès.");
    }

    public function show(Contract $contract): View
    {
        $contract->load(['client', 'template', 'createdBy', 'sentBy']);
        return view('erp.contracts.show', compact('contract'));
    }

    public function edit(Contract $contract): View
    {
        $clients   = Client::orderBy('name')->get(['id', 'name', 'type']);
        $templates = ContractTemplate::where('is_active', true)->get();
        return view('erp.contracts.edit', compact('contract', 'clients', 'templates'));
    }

    public function update(Request $request, Contract $contract): RedirectResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'content'    => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['nullable', 'date'],
            'value'      => ['nullable', 'numeric', 'min:0'],
            'status'     => ['required', 'in:draft,active,expired,terminated'],
        ]);

        $contract->update($data);

        return redirect()->route('erp.contracts.show', $contract)
            ->with('success', 'Contrat mis à jour.');
    }

    public function sign(Contract $contract): RedirectResponse
    {
        $contract->update([
            'signed_at' => now(),
            'status'    => 'active',
        ]);

        return back()->with('success', 'Contrat marqué comme signé.');
    }

    public function sendByEmail(Contract $contract, NotificationService $notif): RedirectResponse
    {
        $client = $contract->client;
        if (! $client?->email) {
            return back()->with('error', 'Ce client n\'a pas d\'adresse email.');
        }

        $html = $this->buildEmailBody($contract);
        $notif->email(
            $client->email,
            $client->name,
            "Contrat {$contract->reference} — GOVIBE",
            $html,
            Contract::class,
            $contract->id,
        );

        $contract->update([
            'sent_at' => now(),
            'sent_by' => Auth::id(),
        ]);

        return back()->with('success', "Contrat envoyé par email à {$client->email}.");
    }

    public function destroy(Contract $contract): RedirectResponse
    {
        $contract->delete();
        return redirect()->route('erp.contracts.index')->with('success', 'Contrat supprimé.');
    }

    /* ── Templates list ─────────────────────────────────────────────── */

    public function templates(): View
    {
        $templates = ContractTemplate::withCount('contracts')->orderBy('category')->get();
        return view('erp.contracts.templates', compact('templates'));
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'category'    => ['required', 'in:coworking,erp,formation,developpement,nda,autre'],
            'description' => ['nullable', 'string', 'max:500'],
            'body'        => ['required', 'string'],
        ]);

        $template = ContractTemplate::create([
            ...$data,
            'variables'  => (new ContractTemplate($data))->fill($data)->extractVariables(),
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', "Modèle « {$template->title} » créé.");
    }

    public function seedTemplates(): RedirectResponse
    {
        foreach (ContractTemplate::defaults() as $tpl) {
            ContractTemplate::firstOrCreate(['title' => $tpl['title']], [
                ...$tpl,
                'created_by' => Auth::id(),
            ]);
        }
        return back()->with('success', '5 modèles GOVIBE installés avec succès.');
    }

    /* ── Private ────────────────────────────────────────────────────── */

    private function buildEmailBody(Contract $contract): string
    {
        $client = $contract->client;
        return "<!DOCTYPE html><html><head><meta charset='utf-8'><style>
body{margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif}
.wrap{max-width:700px;margin:20px auto;background:#fff;border-radius:12px;overflow:hidden}
.hdr{background:#0a0a0a;padding:24px 32px;text-align:center}
.hdr h1{color:#fff;margin:0;font-size:20px;font-weight:700}
.hdr span{color:#DC2626}
.body{padding:32px}
.body p{color:#444;font-size:14px;line-height:1.7;margin:0 0 14px}
.contract-box{background:#f8f8f8;border-left:4px solid #DC2626;border-radius:8px;padding:20px;margin:20px 0;font-size:13px;line-height:1.8}
.btn{display:inline-block;background:#DC2626;color:#fff;text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:700;font-size:14px;margin:16px 0}
.ftr{background:#f4f4f4;padding:16px 32px;text-align:center;color:#999;font-size:11px}
</style></head><body>
<div class='wrap'>
<div class='hdr'><h1><span>GOVIBE</span> Innovation Hub</h1></div>
<div class='body'>
  <p>Bonjour <strong>{$client->name}</strong>,</p>
  <p>Veuillez trouver ci-dessous votre contrat <strong>{$contract->reference}</strong> à examiner et signer.</p>
  <div class='contract-box'>
    <strong>Référence :</strong> {$contract->reference}<br>
    <strong>Titre :</strong> {$contract->title}<br>
    <strong>Date de début :</strong> {$contract->start_date->format('d/m/Y')}<br>
    " . ($contract->end_date ? "<strong>Date de fin :</strong> {$contract->end_date->format('d/m/Y')}<br>" : '') . "
    " . ($contract->value ? "<strong>Valeur :</strong> HTG " . number_format($contract->value, 0, '.', ',') . "<br>" : '') . "
  </div>
  <p>Pour toute question, n'hésitez pas à nous contacter.</p>
  <a class='btn' href='https://govibeht.com'>Contacter GOVIBE</a>
</div>
<div class='ftr'>GOVIBE Innovation Hub · Port-au-Prince, Haïti · govibeht.com</div>
</div></body></html>";
    }
}
