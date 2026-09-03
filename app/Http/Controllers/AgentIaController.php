<?php

namespace App\Http\Controllers;

use App\Models\AgentIa;
use App\Models\DemandeAgentIa;
use App\Models\PasserellePaiement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentIaController extends Controller
{
    /** La page commerciale : catalogue et arguments. */
    public function index()
    {
        return view('agents-ia.index', [
            'agents' => AgentIa::actif()->get(),
        ]);
    }

    /**
     * Le formulaire de demande. L'agent choisi arrive par l'URL et reste
     * modifiable : un visiteur qui arrive par un lien direct doit pouvoir
     * changer d'avis sans repartir du catalogue.
     */
    public function demande(Request $request)
    {
        $agents = AgentIa::actif()->get();

        return view('agents-ia.demande', [
            'agents' => $agents,
            'choisi' => $agents->firstWhere('slug', $request->query('agent')),
            'passerelles' => PasserellePaiement::actif()->get()->reject->est_incomplete->values(),
        ]);
    }

    public function store(Request $request)
    {
        $valide = $request->validate([
            'agent' => ['required', Rule::exists('agents_ia', 'slug')->where('actif', true)],

            'entreprise' => 'required|string|max:200',
            'responsable' => 'required|string|max:150',
            'email' => 'required|email|max:190',
            'telephone' => 'required|string|max:40',
            'secteur' => 'nullable|string|max:80',
            'pays' => 'nullable|string|max:80',
            'ville' => 'nullable|string|max:120',
            'site_web' => 'nullable|string|max:255',

            'objectifs' => 'nullable|string|max:2000',
            'a_automatiser' => 'nullable|string|max:2000',
            'volume_conversations' => ['nullable', Rule::in(array_keys(AgentIa::volumesConversations()))],
            'langues' => 'nullable|string|max:120',
            'canal' => ['nullable', Rule::in(array_keys(AgentIa::canauxDisponibles()))],
            'integrations' => 'nullable|array',
            'integrations.*' => ['string', Rule::in(array_keys(AgentIa::integrationsDisponibles()))],
            'message' => 'nullable|string|max:3000',

            'moyen_paiement' => 'nullable|string|max:60',
        ], [
            'agent.required' => 'Choisissez un agent.',
            'agent.exists' => "Cet agent n'est plus proposé. Choisissez-en un autre.",
        ]);

        $agent = AgentIa::where('slug', $valide['agent'])->firstOrFail();

        $passerelle = empty($valide['moyen_paiement'])
            ? null
            : PasserellePaiement::where('code', $valide['moyen_paiement'])->first();

        $demande = DemandeAgentIa::create([
            'reference' => DemandeAgentIa::genererReference(),
            'agent_ia_id' => $agent->id,

            // Nom et prix figés : le catalogue peut changer après la commande,
            // le client doit retrouver ce qu'il a demandé et à quel prix.
            'agent_nom' => $agent->nom,
            'prix_installation' => $agent->prix_installation,
            'prix_mensuel' => $agent->prix_mensuel,
            'devise' => $agent->devise,
            'sur_devis' => $agent->sur_devis,

            'entreprise' => $valide['entreprise'],
            'responsable' => $valide['responsable'],
            'email' => $valide['email'],
            'telephone' => $valide['telephone'],
            'secteur' => $valide['secteur'] ?? null,
            'pays' => $valide['pays'] ?? null,
            'ville' => $valide['ville'] ?? null,
            'site_web' => $valide['site_web'] ?? null,

            'objectifs' => $valide['objectifs'] ?? null,
            'a_automatiser' => $valide['a_automatiser'] ?? null,
            'volume_conversations' => $valide['volume_conversations'] ?? null,
            'langues' => $valide['langues'] ?? null,
            'canal' => $valide['canal'] ?? null,
            'integrations' => $valide['integrations'] ?? [],
            'message' => $valide['message'] ?? null,

            'moyen_paiement' => $passerelle?->code,
            'moyen_paiement_nom' => $passerelle?->nom,

            // Un agent sur devis n'a pas de montant à régler : son paiement
            // n'est pas « en attente », il est à établir.
            'statut_paiement' => $agent->sur_devis ? 'sur_devis' : 'en_attente',
            'statut' => 'nouvelle',
            'ip' => $request->ip(),
        ]);

        // La confirmation porte le nom du client, ses coordonnées et son
        // montant : elle passe par la session, pas par un identifiant d'URL.
        return redirect()->route('agents-ia.confirmation')
            ->with('demande_id', $demande->id);
    }

    public function confirmation(Request $request)
    {
        $id = session('demande_id');

        if (! $id || ! ($demande = DemandeAgentIa::find($id))) {
            return redirect()->route('agents-ia.index');
        }

        $request->session()->reflash();

        return view('agents-ia.confirmation', [
            'demande' => $demande,
            'passerelle' => $demande->moyen_paiement
                ? PasserellePaiement::where('code', $demande->moyen_paiement)->first()
                : null,
        ]);
    }
}
