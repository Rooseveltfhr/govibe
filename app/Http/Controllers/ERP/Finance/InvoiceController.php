<?php

namespace App\Http\Controllers\ERP\Finance;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Service;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Invoice::with('client');
        if ($request->filled('search')) $query->where(fn($q)=>$q->where('reference','like',"%{$request->search}%")->orWhereHas('client',fn($q2)=>$q2->where('name','like',"%{$request->search}%")));
        if ($request->filled('status')) $query->where('status',$request->status);
        $invoices = $query->latest()->paginate(20)->withQueryString();
        $stats = [
            'total'    => Invoice::count(),
            'paid'     => Invoice::where('status','paid')->count(),
            'pending'  => Invoice::whereIn('status',['draft','sent'])->count(),
            'overdue'  => Invoice::where('status','overdue')->count(),
            'revenue'  => Invoice::where('status','paid')->sum('total'),
        ];
        return view('erp.invoices.index', compact('invoices','stats'));
    }

    public function create()
    {
        $clients  = Client::where('status','active')->get();
        $projects = Project::where('status','active')->get();
        $services = Service::where('is_active',true)->get();
        return view('erp.invoices.create', compact('clients','projects','services'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id'  => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'due_date'   => 'required|date',
            'tax_rate'   => 'nullable|numeric|min:0|max:100',
            'discount'   => 'nullable|numeric|min:0',
            'notes'      => 'nullable|string|max:2000',
            'items'      => 'required|array|min:1',
            'items.*.description' => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit_price'  => 'required|numeric|min:0',
        ]);

        $subtotal   = collect($validated['items'])->sum(fn($i) => $i['quantity'] * $i['unit_price']);
        $taxAmount  = $subtotal * (($validated['tax_rate'] ?? 0) / 100);
        $total      = $subtotal + $taxAmount - ($validated['discount'] ?? 0);

        $invoice = Invoice::create([
            'reference'   => 'INV-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5)),
            'client_id'   => $validated['client_id'],
            'project_id'  => $validated['project_id'] ?? null,
            'subtotal'    => $subtotal,
            'tax_rate'    => $validated['tax_rate'] ?? 0,
            'tax_amount'  => $taxAmount,
            'discount'    => $validated['discount'] ?? 0,
            'total'       => $total,
            'status'      => 'draft',
            'issued_date' => today(),
            'due_date'    => $validated['due_date'],
            'notes'       => $validated['notes'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        foreach ($validated['items'] as $item) {
            $invoice->items()->create([
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total'       => $item['quantity'] * $item['unit_price'],
            ]);
        }

        return redirect()->route('erp.invoices.show', $invoice)->with('success', 'Facture créée avec succès.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['client','project','items','payments']);
        return view('erp.invoices.show', compact('invoice'));
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->items()->delete();
        $invoice->delete();
        return redirect()->route('erp.invoices.index')->with('success', 'Facture supprimée.');
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['client','items']);
        $pdf = Pdf::loadView('erp.invoices.pdf', compact('invoice'));
        return $pdf->download('facture-' . $invoice->reference . '.pdf');
    }

    public function markPaid(Invoice $invoice)
    {
        $invoice->update(['status' => 'paid', 'paid_at' => now()]);
        return back()->with('success', 'Facture marquée comme payée.');
    }
}
