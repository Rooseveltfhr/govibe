<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\CommandeAbonnement;
use App\Models\Plan;
use App\Services\AbonnementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommandeAbonnementController extends Controller
{
    public function __construct(private AbonnementService $abonnements) {}

    public function index(Request $request)
    {
        $requete = CommandeAbonnement::with(['plan', 'passerelle', 'abonnement'])->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($service = $request->query('service')) {
            $requete->where('service', $service);
        }
        if ($request->query('mode') === 'manuel') {
            $requete->where('mode_paiement', 'manuel');
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('reference', 'like', "%{$q}%")
                    ->orWhere('nom_complet', 'like', "%{$q}%")
                    ->orWhere('entreprise', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%")
                    ->orWhere('domaine', 'like', "%{$q}%");
            });
        }

        return view('erp.commandes.index', [
            'commandes' => $requete->paginate(25)->withQueryString(),
            'stats' => [
                'total' => CommandeAbonnement::count(),
                'a_traiter' => CommandeAbonnement::atraiter()->count(),
                'payees' => CommandeAbonnement::whereIn('statut', ['paiement_recu', 'en_traitement'])->count(),
                'livrees' => CommandeAbonnement::where('statut', 'livree')->count(),
            ],
            // C'est cette répartition qui dit où porter l'effort de mise en
            // service : un domaine se traite autrement qu'un site.
            'repartition' => collect(Plan::services())
                ->map(fn ($libelle, $cle) => [
                    'libelle' => $libelle,
                    'total' => CommandeAbonnement::pourService($cle)->count(),
                ])->all(),
        ]);
    }

    public function show(CommandeAbonnement $commande)
    {
        $commande->load(['plan', 'passerelle', 'preuve', 'paiement.evenements', 'abonnement', 'agent', 'client']);

        return view('erp.commandes.show', compact('commande'));
    }

    public function update(Request $request, CommandeAbonnement $commande)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(CommandeAbonnement::statuts()))],
            'notes_internes' => 'nullable|string|max:2000',
        ]);

        // « Livrée » n'est pas un statut qu'on pose à la main : il signifie
        // qu'un abonnement facture. Le poser sans abonnement donnerait une
        // commande livrée que rien ne facture jamais.
        if ($valide['statut'] === 'livree' && ! $commande->estActivee()) {
            return back()->withErrors([
                'statut' => 'Mettez d\'abord le service en route : c\'est ce qui crée l\'abonnement.',
            ]);
        }

        $commande->update([
            'statut' => $valide['statut'],
            'notes_internes' => $valide['notes_internes'] ?? $commande->notes_internes,
            'traitee_par' => $request->user()->id,
            'traitee_le' => now(),
        ]);

        return back()->with('success', "Commande {$commande->reference} mise à jour.");
    }

    /**
     * Crée le client s'il le faut, ouvre l'abonnement, et passe la commande en
     * livrée. C'est le seul chemin du site vers la facturation récurrente.
     */
    public function activer(Request $request, CommandeAbonnement $commande)
    {
        try {
            $abonnement = $this->abonnements->activerCommande($commande, $request->user()->id);
        } catch (\DomainException $e) {
            return back()->withErrors(['activation' => $e->getMessage()]);
        }

        return redirect()->route('erp.abonnements.show', $abonnement)
            ->with('success', "Abonnement {$abonnement->reference} ouvert depuis la commande {$commande->reference}.");
    }

    public function export()
    {
        $nom = 'commandes-abonnement-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () {
            $sortie = fopen('php://output', 'w');
            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Référence', 'Reçue le', 'Service', 'Offre', 'Cycle',
                'Nom', 'Entreprise', 'E-mail', 'WhatsApp',
                'Domaine', 'Origine', 'Durée (ans)',
                'Montant réglé', 'Devise réglée', 'Montant facturé', 'Devise', 'Taux',
                'Moyen', 'Mode', 'Statut', 'Abonnement',
            ], ';', '"', '');

            CommandeAbonnement::with('abonnement')->latest()->chunk(200, function ($lot) use ($sortie) {
                foreach ($lot as $c) {
                    fputcsv($sortie, [
                        $c->reference, $c->created_at->format('d/m/Y H:i'),
                        $c->service_libelle, $c->plan_nom, $c->cycle_libelle,
                        $c->nom_complet, $c->entreprise, $c->email, $c->whatsapp,
                        $c->domaine, $c->origine_domaine_libelle, $c->duree_annees,
                        $c->montant_a_payer, $c->devise_paiement,
                        $c->montant_ttc, $c->devise, $c->taux_change,
                        $c->passerelle_nom, $c->mode_paiement,
                        $c->statut_libelle, $c->abonnement?->reference,
                    ], ';', '"', '');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
