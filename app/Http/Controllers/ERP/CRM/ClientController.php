<?php

namespace App\Http\Controllers\ERP\CRM;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Inscription;
use App\Services\NotificationService;
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
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:individual,company,ngo,government,university,association',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'website'        => 'nullable|url|max:255',
            'status'         => 'required|in:active,prospect,inactive',
            'source'         => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
            'date_naissance' => 'nullable|date',
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
            'name'           => 'required|string|max:255',
            'type'           => 'required|in:individual,company,ngo,government,university,association',
            'email'          => 'nullable|email|max:255',
            'phone'          => 'nullable|string|max:30',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:100',
            'website'        => 'nullable|url|max:255',
            'status'         => 'required|in:active,prospect,inactive',
            'source'         => 'nullable|string|max:100',
            'notes'          => 'nullable|string|max:2000',
            'date_naissance' => 'nullable|date',
        ]);

        $client->update($validated);
        return redirect()->route('erp.crm.clients.show', $client)->with('success', 'Client mis à jour.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('erp.crm.clients')->with('success', 'Client supprimé.');
    }

    /* ── Anniversaires ───────────────────────────────────────────── */

    public function anniversaires()
    {
        $today = now();

        $clientsAujourdhui = $this->safeQuery(
            fn() => Client::whereNotNull('date_naissance')
                ->whereMonth('date_naissance', $today->month)
                ->whereDay('date_naissance', $today->day)
                ->get(),
            collect()
        );

        $inscritsAujourdhui = $this->safeQuery(
            fn() => Inscription::whereNotNull('date_naissance')
                ->whereMonth('date_naissance', $today->month)
                ->whereDay('date_naissance', $today->day)
                ->get(),
            collect()
        );

        // 7 prochains jours (hors aujourd'hui)
        $prochains = collect();
        for ($d = 1; $d <= 7; $d++) {
            $date = $today->copy()->addDays($d);
            $clients = $this->safeQuery(
                fn() => Client::whereNotNull('date_naissance')
                    ->whereMonth('date_naissance', $date->month)
                    ->whereDay('date_naissance', $date->day)
                    ->get()
                    ->map(fn($c) => ['type' => 'Client', 'nom' => $c->name, 'phone' => $c->phone, 'email' => $c->email, 'date' => $date->format('d/m'), 'jours' => $d]),
                collect()
            );
            $inscrits = $this->safeQuery(
                fn() => Inscription::whereNotNull('date_naissance')
                    ->whereMonth('date_naissance', $date->month)
                    ->whereDay('date_naissance', $date->day)
                    ->get()
                    ->map(fn($i) => ['type' => 'Inscrit', 'nom' => $i->nom_complet, 'phone' => $i->telephone, 'email' => $i->email, 'date' => $date->format('d/m'), 'jours' => $d]),
                collect()
            );
            $prochains = $prochains->merge($clients)->merge($inscrits);
        }

        $stats = [
            'aujourd_hui' => $clientsAujourdhui->count() + $inscritsAujourdhui->count(),
            'semaine'     => $prochains->count(),
            'total_clients_avec_date' => $this->safeCount(fn() => Client::whereNotNull('date_naissance')->count()),
            'total_inscrits_avec_date' => $this->safeCount(fn() => Inscription::whereNotNull('date_naissance')->count()),
        ];

        return view('erp.crm.anniversaires', compact(
            'clientsAujourdhui', 'inscritsAujourdhui', 'prochains', 'stats'
        ));
    }

    public function sendBirthdayManual(Request $request, NotificationService $notif)
    {
        $type = $request->input('type');
        $id   = $request->input('id');

        try {
            if ($type === 'client') {
                $person = Client::findOrFail($id);
                $nom    = $person->name;
                $phone  = $person->phone;
                $email  = $person->email;
                $age    = $person->date_naissance ? (now()->year - $person->date_naissance->year) : null;
                $ctx    = Client::class;
            } else {
                $person = Inscription::findOrFail($id);
                $nom    = $person->nom_complet;
                $phone  = $person->telephone;
                $email  = $person->email;
                $age    = $person->date_naissance ? (now()->year - $person->date_naissance->year) : null;
                $ctx    = Inscription::class;
            }

            $prenom  = explode(' ', $nom)[0];
            $ageStr  = $age ? "{$age}ème " : '';
            $message = "🎂 *Bonne fête {$prenom} !*\n\nToute l'équipe *GOVIBE Innovation Hub* vous souhaite un très joyeux anniversaire et une excellente {$ageStr}année ! 🎉\n\n_— L'équipe GOVIBE_";

            if ($phone) $notif->whatsapp($phone, $message, $nom, $ctx, $id);
            if ($email) {
                $notif->email($email, $nom, '🎂 Joyeux anniversaire — GOVIBE Innovation Hub',
                    "<p>Cher(e) <b>{$prenom}</b>, toute l'équipe GOVIBE vous souhaite un joyeux anniversaire ! 🎂🎉</p>",
                    $ctx, $id
                );
            }

            return back()->with('success', "Vœux envoyés à {$nom}.");
        } catch (\Exception $e) {
            return back()->with('error', 'Erreur : ' . $e->getMessage());
        }
    }

    private function safeCount(callable $fn, int $default = 0): int
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }

    private function safeQuery(callable $fn, $default)
    {
        try { return $fn(); } catch (\Exception $e) { return $default; }
    }
}
