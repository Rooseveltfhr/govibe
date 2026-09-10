<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Abonnement;
use App\Models\Client;
use App\Models\Plan;
use App\Services\AbonnementService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AbonnementAdminController extends Controller
{
    public function __construct(private AbonnementService $service) {}

    // ── Abonnements ──────────────────────────────────────

    public function index(Request $request)
    {
        $requete = Abonnement::with(['client', 'plan'])->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($service = $request->query('service')) {
            $requete->where('service', $service);
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('plan_nom', 'like', "%{$q}%")
                    ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        $parStatut = Abonnement::selectRaw('statut, count(*) as n')->groupBy('statut')->pluck('n', 'statut');

        $tunnel = [];
        foreach (Abonnement::statuts() as $cle => $libelle) {
            $tunnel[$cle] = ['libelle' => $libelle, 'n' => (int) ($parStatut[$cle] ?? 0)];
        }

        return view('erp.abonnements.index', [
            'abonnements' => $requete->paginate(25)->withQueryString(),
            'tunnel' => $tunnel,
            'stats' => [
                'total' => Abonnement::count(),
                'en_service' => Abonnement::enService()->count(),
                'impayes' => Abonnement::whereIn('statut', ['en_retard', 'suspendu'])->count(),
                'a_facturer' => Abonnement::afacturer()->count(),
            ],
        ]);
    }

    public function show(Abonnement $abonnement)
    {
        $abonnement->load(['client', 'plan', 'factures', 'evenements.user']);

        return view('erp.abonnements.show', compact('abonnement'));
    }

    public function souscrire(Request $request)
    {
        $valide = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'plan_id' => 'required|exists:plans,id',
            'cycle' => ['required', Rule::in(array_keys(Plan::cycles()))],
        ]);

        $plan = Plan::findOrFail($valide['plan_id']);

        try {
            $abonnement = $this->service->souscrire(
                Client::findOrFail($valide['client_id']),
                $plan,
                $valide['cycle']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['plan_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('erp.abonnements.show', $abonnement)
            ->with('success', "Abonnement {$abonnement->reference} créé.");
    }

    public function facturer(Abonnement $abonnement)
    {
        $facture = $this->service->facturer($abonnement);

        return back()->with(
            'success',
            $facture
                ? "Facture {$facture->reference} émise."
                : 'Aucune facture à émettre pour cet abonnement.'
        );
    }

    public function changerStatut(Request $request, Abonnement $abonnement)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(Abonnement::statuts()))],
        ]);

        $this->service->changerStatut($abonnement, $valide['statut'], $request->user()->id);

        return back()->with('success', "Abonnement {$abonnement->reference} mis à jour.");
    }

    public function resilier(Request $request, Abonnement $abonnement)
    {
        $valide = $request->validate(['motif' => 'required|string|max:1000']);

        $this->service->resilier($abonnement, $valide['motif'], $request->user()->id);

        return back()->with('success', "Abonnement {$abonnement->reference} résilié. Le service court jusqu'à la fin de la période payée.");
    }

    // ── Catalogue des plans ──────────────────────────────

    public function plans()
    {
        return view('erp.abonnements.plans', [
            'plans' => Plan::withCount('abonnements')->orderBy('service')->orderBy('ordre')->get(),
        ]);
    }

    public function storePlan(Request $request)
    {
        $this->appliquerPlan($request, new Plan);

        return redirect()->route('erp.abonnements.plans')->with('success', 'Plan créé.');
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $this->appliquerPlan($request, $plan);

        return redirect()->route('erp.abonnements.plans')->with('success', "Plan « {$plan->nom} » mis à jour.");
    }

    public function destroyPlan(Plan $plan)
    {
        // Les abonnements gardent leur nom et leur prix figés : retirer un
        // plan du catalogue ne touche pas aux contrats en cours.
        $nom = $plan->nom;
        $plan->delete();

        return redirect()->route('erp.abonnements.plans')
            ->with('success', "Plan « {$nom} » retiré du catalogue.");
    }

    private function appliquerPlan(Request $request, Plan $plan): void
    {
        $valide = $request->validate([
            'nom' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'service' => ['required', Rule::in(array_keys(Plan::services()))],
            'description' => 'nullable|string|max:2000',
            'prix_mensuel' => 'nullable|numeric|min:0|max:9999999',
            'prix_annuel' => 'nullable|numeric|min:0|max:9999999',
            'devise' => ['required', Rule::in(['USD', 'HTG'])],
            'tca_taux' => 'nullable|numeric|min:0|max:100',
            'quotas' => 'nullable|string|max:2000',
            'fonctionnalites' => 'nullable|string|max:4000',
            'essai_jours' => 'nullable|integer|min:0|max:365',
            'sur_devis' => 'nullable|boolean',
            'actif' => 'nullable|boolean',
            'mis_en_avant' => 'nullable|boolean',
            'ordre' => 'nullable|integer|min:0|max:999',
        ]);

        $plan->fill([
            'nom' => $valide['nom'],
            'slug' => ($valide['slug'] ?? null) ?: ($plan->slug ?: Str::slug($valide['nom'])),
            'service' => $valide['service'],
            'description' => $valide['description'] ?? null,
            'prix_mensuel' => $valide['prix_mensuel'] ?? null,
            'prix_annuel' => $valide['prix_annuel'] ?? null,
            'devise' => $valide['devise'],
            // Aucune valeur par défaut inventée : ce que l'ERP saisit fait foi.
            'tca_taux' => $valide['tca_taux'] ?? 0,
            'quotas' => $this->lignesCles($valide['quotas'] ?? null),
            'fonctionnalites' => $this->lignes($valide['fonctionnalites'] ?? null),
            'essai_jours' => $valide['essai_jours'] ?? 0,
            'sur_devis' => (bool) ($valide['sur_devis'] ?? false),
            'actif' => (bool) ($valide['actif'] ?? false),
            'mis_en_avant' => (bool) ($valide['mis_en_avant'] ?? false),
            'ordre' => $valide['ordre'] ?? 0,
        ])->save();
    }

    /** @return array<int,string> */
    private function lignes(?string $texte): array
    {
        if (! $texte) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $texte) ?: []),
            fn ($l) => $l !== ''
        ));
    }

    /** « stockage_mo: 5000 » par ligne — la forme la plus simple à corriger. */
    private function lignesCles(?string $texte): array
    {
        $quotas = [];

        foreach ($this->lignes($texte) as $ligne) {
            if (! str_contains($ligne, ':')) {
                continue;
            }
            [$cle, $valeur] = array_map('trim', explode(':', $ligne, 2));
            if ($cle !== '') {
                $quotas[$cle] = is_numeric($valeur) ? $valeur + 0 : $valeur;
            }
        }

        return $quotas;
    }
}
