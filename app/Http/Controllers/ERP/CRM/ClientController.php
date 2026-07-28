<?php

namespace App\Http\Controllers\ERP\CRM;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $query = Client::query();

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('name','like',"%$s%")->orWhere('email','like',"%$s%")->orWhere('phone','like',"%$s%"));
        }
        if ($request->filled('type'))   $query->where('type', $request->type);
        if ($request->filled('status')) $query->where('status', $request->status);

        $clients = $query->latest()->paginate(20)->withQueryString();
        $stats = [
            'total'    => Client::count(),
            'active'   => Client::where('status','active')->count(),
            'prospect' => Client::where('status','prospect')->count(),
        ];

        return view('erp.crm.clients.index', compact('clients', 'stats'));
    }

    public function create()
    {
        $businessUnits = BusinessUnit::where('active',true)->get();
        return view('erp.crm.clients.create', compact('businessUnits'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|in:individual,company,ngo,government,university,association',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'status'  => 'required|in:active,prospect,inactive',
            'source'  => 'nullable|string|max:100',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $validated['reference_number'] = 'CLI-' . strtoupper(substr(uniqid(), -6));
        $validated['assigned_user_id'] = auth()->id();

        $client = Client::create($validated);

        return redirect()->route('erp.crm.clients.show', $client)->with('success', 'Client créé avec succès.');
    }

    public function show(Client $client)
    {
        $client->load(['contacts', 'projects', 'invoices', 'quotations', 'contracts']);
        return view('erp.crm.clients.show', compact('client'));
    }

    public function edit(Client $client)
    {
        $businessUnits = BusinessUnit::where('active',true)->get();
        return view('erp.crm.clients.edit', compact('client', 'businessUnits'));
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'type'    => 'required|in:individual,company,ngo,government,university,association',
            'email'   => 'nullable|email|max:255',
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
            'city'    => 'nullable|string|max:100',
            'website' => 'nullable|url|max:255',
            'status'  => 'required|in:active,prospect,inactive',
            'source'  => 'nullable|string|max:100',
            'notes'   => 'nullable|string|max:2000',
        ]);

        $client->update($validated);
        return redirect()->route('erp.crm.clients.show', $client)->with('success', 'Client mis à jour.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('erp.crm.clients')->with('success', 'Client supprimé.');
    }
}
