<?php

namespace App\Http\Controllers\ERP\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientSubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(): View
    {
        $plans = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();

        if ($plans->isEmpty()) {
            $plans = collect(SubscriptionPlan::defaults())->map(fn($p) => (object) $p);
        }

        $subscriptions = ClientSubscription::with(['client', 'assignedBy'])
            ->whereIn('status', ['active', 'trial'])
            ->latest()
            ->get();

        $clients = Client::orderBy('name')->get(['id', 'name', 'type', 'status']);

        $stats = [
            'active'   => ClientSubscription::where('status', 'active')->count(),
            'trial'    => ClientSubscription::where('status', 'trial')->count(),
            'expired'  => ClientSubscription::where('status', 'expired')->count(),
            'revenue'  => ClientSubscription::where('status', 'active')->sum('price'),
        ];

        return view('erp.admin.plans.index', compact('plans', 'subscriptions', 'clients', 'stats'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id'     => ['required', 'exists:clients,id'],
            'plan_slug'     => ['required', 'string'],
            'plan_name'     => ['required', 'string'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'price'         => ['required', 'numeric', 'min:0'],
            'currency'      => ['required', 'string', 'max:10'],
            'starts_at'     => ['required', 'date'],
            'ends_at'       => ['nullable', 'date', 'after:starts_at'],
            'status'        => ['required', 'in:active,trial'],
            'notes'         => ['nullable', 'string', 'max:500'],
        ]);

        // Cancel any existing active subscription for this client
        ClientSubscription::where('client_id', $data['client_id'])
            ->whereIn('status', ['active', 'trial'])
            ->update(['status' => 'cancelled']);

        ClientSubscription::create([
            ...$data,
            'assigned_by' => auth()->id(),
        ]);

        $client = Client::find($data['client_id']);
        return back()->with('success', "Plan {$data['plan_name']} assigné à {$client->name}.");
    }

    public function cancel(ClientSubscription $subscription): RedirectResponse
    {
        $subscription->update(['status' => 'cancelled']);
        return back()->with('success', 'Abonnement annulé.');
    }
}
