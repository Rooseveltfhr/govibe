<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\AgentIa;
use App\Models\DemandeAgentIa;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AgentIaAdminController extends Controller
{
    /** Le catalogue : ce que GOVIBE propose et à quel prix. */
    public function catalogue()
    {
        return view('erp.agents-ia.catalogue', [
            'agents' => AgentIa::withCount('demandes')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $agent = new AgentIa;
        $this->appliquer($request, $agent);

        return redirect()->route('erp.agents-ia.catalogue')
            ->with('success', "Agent « {$agent->nom} » créé.");
    }

    public function update(Request $request, AgentIa $agent)
    {
        $this->appliquer($request, $agent);

        return redirect()->route('erp.agents-ia.catalogue')
            ->with('success', "Agent « {$agent->nom} » mis à jour.");
    }

    public function destroy(AgentIa $agent)
    {
        // Les demandes gardent le nom et les prix figés : supprimer l'agent du
        // catalogue ne détruit pas l'historique commercial.
        $nom = $agent->nom;
        $agent->delete();

        return redirect()->route('erp.agents-ia.catalogue')
            ->with('success', "Agent « {$nom} » retiré du catalogue.");
    }

    private function appliquer(Request $request, AgentIa $agent): void
    {
        $valide = $request->validate([
            'nom' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:80', 'alpha_dash',
                Rule::unique('agents_ia', 'slug')->ignore($agent->id)],
            'categorie' => 'nullable|string|max:60',
            'icone' => 'nullable|string|max:60',
            'description_courte' => 'required|string|max:400',
            'capacites' => 'nullable|string|max:4000',
            'canaux' => 'nullable|string|max:400',
            'prix_installation' => 'nullable|numeric|min:0|max:9999999',
            'prix_mensuel' => 'nullable|numeric|min:0|max:9999999',
            'devise' => 'nullable|string|max:8',
            'sur_devis' => 'nullable|boolean',
            'avertissement' => 'nullable|string|max:1000',
            'actif' => 'nullable|boolean',
            'ordre' => 'nullable|integer|min:0|max:999',
        ]);

        $agent->fill([
            'nom' => $valide['nom'],
            'slug' => $valide['slug'] ?: ($agent->slug ?: Str::slug($valide['nom'])),
            'categorie' => $valide['categorie'] ?? null,
            'icone' => $valide['icone'] ?: 'fa-robot',
            'description_courte' => $valide['description_courte'],

            // Une capacité par ligne, un canal par ligne : c'est la forme la
            // plus simple à corriger depuis un navigateur.
            'capacites' => $this->lignes($valide['capacites'] ?? null),
            'canaux' => $this->lignes($valide['canaux'] ?? null),

            'prix_installation' => $valide['prix_installation'] ?? null,
            'prix_mensuel' => $valide['prix_mensuel'] ?? null,
            'devise' => $valide['devise'] ?: 'USD',
            'sur_devis' => (bool) ($valide['sur_devis'] ?? false),
            'avertissement' => $valide['avertissement'] ?? null,
            'actif' => (bool) ($valide['actif'] ?? false),
            'ordre' => $valide['ordre'] ?? 0,
        ])->save();
    }

    /** @return array<int,string> */
    private function lignes(?string $texte): array
    {
        if (! $texte) {
            return [];
        }

        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\r\n|\r|\n/', $texte) ?: []
        ), fn ($l) => $l !== ''));
    }

    // ── Demandes ─────────────────────────────────────────

    public function demandes(Request $request)
    {
        $requete = DemandeAgentIa::with('agent')->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($sp = $request->query('statut_paiement')) {
            $requete->where('statut_paiement', $sp);
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('entreprise', 'like', "%{$q}%")
                    ->orWhere('responsable', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        // Le tunnel montre à quelle étape les dossiers s'arrêtent, comme pour
        // les fiches techniques : c'est là que se voit ce qui bloque.
        $parStatut = DemandeAgentIa::selectRaw('statut, count(*) as n')
            ->groupBy('statut')->pluck('n', 'statut');

        $tunnel = [];
        foreach (DemandeAgentIa::statuts() as $cle => $libelle) {
            $tunnel[$cle] = ['libelle' => $libelle, 'n' => (int) ($parStatut[$cle] ?? 0)];
        }

        return view('erp.agents-ia.demandes', [
            'demandes' => $requete->paginate(25)->withQueryString(),
            'tunnel' => $tunnel,
            'stats' => [
                'total' => DemandeAgentIa::count(),
                'a_traiter' => DemandeAgentIa::atraiter()->count(),
                'actifs' => DemandeAgentIa::where('statut', 'actif')->count(),
                'a_encaisser' => DemandeAgentIa::whereIn('statut_paiement', ['en_attente', 'preuve_envoyee'])->count(),
            ],
        ]);
    }

    public function demande(DemandeAgentIa $demande)
    {
        return view('erp.agents-ia.demande', compact('demande'));
    }

    public function updateDemande(Request $request, DemandeAgentIa $demande)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(DemandeAgentIa::statuts()))],
            'statut_paiement' => ['required', Rule::in(array_keys(DemandeAgentIa::statutsPaiement()))],
            'fournisseur' => 'nullable|string|max:80',
            'numero_whatsapp' => 'nullable|string|max:40',
            'url_agent' => 'nullable|string|max:255',
            'notes_internes' => 'nullable|string|max:5000',
        ]);

        // La date de mise en service est posée au passage à « actif », et
        // conservée ensuite : elle date le début de l'abonnement mensuel.
        if ($valide['statut'] === 'actif' && ! $demande->deploye_le) {
            $valide['deploye_le'] = now();
        }

        $demande->update($valide);

        return back()->with('success', "Demande {$demande->reference} mise à jour.");
    }

    public function destroyDemande(DemandeAgentIa $demande)
    {
        $reference = $demande->reference;
        $demande->delete();

        return redirect()->route('erp.agents-ia.demandes')
            ->with('success', "Demande {$reference} supprimée.");
    }
}
