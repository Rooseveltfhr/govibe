<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Models\TauxChange;
use App\Paiement\RegistrePilotes;
use App\Services\PaiementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaiementAdminController extends Controller
{
    public function __construct(
        private PaiementService $service,
        private RegistrePilotes $pilotes,
    ) {}

    public function index(Request $request)
    {
        $requete = Paiement::with(['passerelle', 'client'])->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($mode = $request->query('mode')) {
            $requete->where('mode', $mode);
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('reference_externe', 'like', "%{$q}%")
                    ->orWhere('passerelle_nom', 'like', "%{$q}%");
            });
        }

        return view('erp.paiements.transactions', [
            'paiements' => $requete->paginate(25)->withQueryString(),
            'stats' => [
                'total' => Paiement::count(),
                'a_valider' => Paiement::averifier()->count(),
                'reussis' => Paiement::reussis()->count(),
                'encaisse' => Paiement::reussis()->where('devise', 'HTG')->sum('montant'),
            ],
        ]);
    }

    public function show(Paiement $paiement)
    {
        $paiement->load(['passerelle', 'client', 'evenements.user', 'preuve']);

        return view('erp.paiements.transaction', compact('paiement'));
    }

    /** Revérifier auprès de la passerelle, à la demande d'un agent. */
    public function reverifier(Paiement $paiement)
    {
        $this->autoriser();
        $this->service->verifier($paiement);

        return back()->with('success', "Paiement {$paiement->reference} revérifié auprès de la passerelle.");
    }

    public function approuver(Request $request, Paiement $paiement)
    {
        $this->autoriser();

        $valide = $request->validate([
            'reference_externe' => 'nullable|string|max:120',
        ]);

        $this->service->approuverManuel(
            $paiement,
            $request->user()->id,
            $valide['reference_externe'] ?? null
        );

        return back()->with('success', "Paiement {$paiement->reference} approuvé.");
    }

    public function rejeter(Request $request, Paiement $paiement)
    {
        $this->autoriser();

        $valide = $request->validate(['motif' => 'required|string|max:500']);

        $this->service->rejeter($paiement, $request->user()->id, $valide['motif']);

        return back()->with('success', "Paiement {$paiement->reference} rejeté.");
    }

    // ── Configuration des passerelles API ────────────────

    public function configuration()
    {
        return view('erp.paiements.configuration', [
            'passerelles' => PasserellePaiement::orderBy('ordre')->orderBy('nom')->get(),
            'pilotes' => $this->pilotes->tous(),
            'taux' => TauxChange::latest('applique_depuis')->take(10)->get(),
            'tauxActuel' => TauxChange::actuel('USD', 'HTG'),
        ]);
    }

    public function configurer(Request $request, PasserellePaiement $passerelle)
    {
        $this->autoriser();

        $valide = $request->validate([
            'mode' => ['required', Rule::in(['manuel', 'api'])],
            'pilote' => ['nullable', Rule::in($this->pilotes->cles())],
            'environnement' => ['required', Rule::in(['test', 'production'])],
            'disponible_public' => 'nullable|boolean',
            'disponible_caisse' => 'nullable|boolean',
            'frais_pourcent' => 'nullable|numeric|min:0|max:100',
            'frais_fixe' => 'nullable|numeric|min:0',
            'identifiants' => 'nullable|array',
            'identifiants.*' => 'nullable|string|max:500',
        ]);

        $pilote = $this->pilotes->trouver($valide['pilote'] ?? null);

        // Un champ laissé vide conserve la clé déjà enregistrée : le
        // formulaire ne la réaffiche jamais, l'effacer par inadvertance
        // couperait les encaissements.
        $identifiants = $passerelle->identifiants ?? [];

        foreach (($valide['identifiants'] ?? []) as $champ => $valeur) {
            if (filled($valeur)) {
                $identifiants[$champ] = $valeur;
            }
        }

        $passerelle->fill([
            'mode' => $valide['mode'],
            'pilote' => $valide['mode'] === 'api' ? ($valide['pilote'] ?? null) : null,
            'environnement' => $valide['environnement'],
            'identifiants' => $identifiants ?: null,
            'devises_supportees' => $pilote?->devises(),
            'frais_pourcent' => $valide['frais_pourcent'] ?? 0,
            'frais_fixe' => $valide['frais_fixe'] ?? 0,
            'disponible_public' => (bool) ($valide['disponible_public'] ?? false),
            'disponible_caisse' => (bool) ($valide['disponible_caisse'] ?? false),
        ])->save();

        $message = "Passerelle {$passerelle->nom} enregistrée.";

        if ($passerelle->estApi() && ! $passerelle->api_prete) {
            $message .= ' Les clés sont incomplètes : elle reste masquée du site.';
        } elseif ($passerelle->environnement === 'production') {
            $message .= ' Elle encaisse désormais de vrais paiements.';
        }

        return back()->with('success', $message);
    }

    public function enregistrerTaux(Request $request)
    {
        $this->autoriser();

        $valide = $request->validate([
            'devise_source' => ['required', Rule::in(['USD', 'HTG'])],
            'devise_cible' => ['required', 'different:devise_source', Rule::in(['USD', 'HTG'])],
            'taux' => 'required|numeric|min:0.000001|max:100000',
        ]);

        TauxChange::create([
            'devise_source' => $valide['devise_source'],
            'devise_cible' => $valide['devise_cible'],
            'taux' => $valide['taux'],
            'defini_par' => $request->user()->id,
            // Les taux précédents sont conservés : une facture émise hier
            // garde le sien.
            'applique_depuis' => now(),
        ]);

        return back()->with('success', 'Taux de change enregistré.');
    }

    /**
     * Toucher à l'argent demande une habilitation explicite. Les
     * administrateurs l'ont d'office ; une permission permet de l'ouvrir à un
     * caissier sans lui donner tout l'ERP.
     */
    private function autoriser(): void
    {
        $user = auth()->user();

        abort_unless(
            $user && ($user->is_admin || $user->can('paiements.approuver')),
            403,
            "Vous n'avez pas l'habilitation pour valider un paiement."
        );
    }
}
