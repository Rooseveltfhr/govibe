<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\FicheSuivi;
use App\Models\FicheTechnique;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FicheTechniqueAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = FicheTechnique::withCount('suivis')->latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->where('nom_organisation', 'like', "%$q%")
                  ->orWhere('nom_commercial', 'like', "%$q%")
                  ->orWhere('contact_nom', 'like', "%$q%")
                  ->orWhere('commune', 'like', "%$q%")
                  ->orWhere('reference', 'like', "%$q%");
            });
        }

        foreach (['statut', 'type_organisation', 'secteur', 'agent'] as $filtre) {
            if ($request->filled($filtre)) {
                $query->where($filtre, $request->input($filtre));
            }
        }

        // Les trois vues dont l'équipe se sert au quotidien.
        match ($request->input('vue')) {
            'qualifies'  => $query->qualifies(),
            'a_relancer' => $query->aRelancer(),
            // « Sans suivi » : les fiches ramassées que personne n'a reprises.
            'sans_suivi' => $query->doesntHave('suivis'),
            default      => null,
        };

        $fiches = $query->paginate(25)->withQueryString();

        $stats = [
            'total'      => FicheTechnique::count(),
            'qualifies'  => FicheTechnique::qualifies()->count(),
            'suivies'    => FicheTechnique::has('suivis')->count(),
            'sans_suivi' => FicheTechnique::doesntHave('suivis')->count(),
            'a_relancer' => FicheTechnique::aRelancer()->count(),
            'clients'    => FicheTechnique::where('statut', 'client')->count(),
        ];

        // Le tunnel commercial, dans l'ordre où un dossier le traverse.
        $tunnel = [];
        foreach (FicheTechnique::statuts() as $cle => $libelle) {
            $tunnel[$libelle] = FicheTechnique::where('statut', $cle)->count();
        }

        $agents = FicheTechnique::whereNotNull('agent')->distinct()->pluck('agent')->sort()->values();

        return view('erp.fiches.index', compact('fiches', 'stats', 'tunnel', 'agents'));
    }

    public function show(FicheTechnique $fiche)
    {
        $fiche->load('suivis');

        return view('erp.fiches.show', compact('fiche'));
    }

    /**
     * Ajout d'un suivi : remarque, échange, message. Le statut de la fiche
     * peut avancer dans le même geste — c'est l'usage réel, l'agent note ce
     * qu'il a fait et où en est le dossier.
     */
    public function storeSuivi(Request $request, FicheTechnique $fiche)
    {
        $data = $request->validate([
            'type'    => ['required', Rule::in(array_keys(FicheSuivi::types()))],
            'message' => 'required|string|max:3000',
            'reponse_prospect' => 'nullable|string|max:3000',
            'agent'   => 'nullable|string|max:120',
            'statut_apres' => ['nullable', Rule::in(array_keys(FicheTechnique::statuts()))],
            'date_relance' => 'nullable|date',
        ], [
            'message.required' => 'Écrivez ce qui a été fait ou dit.',
        ]);

        $fiche->suivis()->create($data);

        $misAJour = [];
        if (filled($data['statut_apres'] ?? null)) {
            $misAJour['statut'] = $data['statut_apres'];
        }
        if ($request->filled('date_relance')) {
            $misAJour['date_relance'] = $data['date_relance'];
        }
        if ($misAJour) {
            $fiche->update($misAJour);
        }

        return back()->with('success', 'Suivi enregistré.');
    }

    /**
     * Requalification depuis l'ERP : les scores et l'affectation évoluent
     * après le terrain, quand on en sait plus que le jour de la visite.
     */
    public function updateQualification(Request $request, FicheTechnique $fiche)
    {
        $data = $request->validate([
            'score_besoin'    => 'required|integer|min:0|max:4',
            'score_potentiel' => 'required|integer|min:0|max:4',
            'statut'          => ['required', Rule::in(array_keys(FicheTechnique::statuts()))],
            'prochaine_action' => ['nullable', Rule::in(array_keys(FicheTechnique::prochainesActions()))],
            'date_relance'    => 'nullable|date',
            'responsable_assigne' => 'nullable|string|max:120',
        ]);

        $fiche->update($data);

        return back()->with('success', 'Qualification mise à jour.');
    }

    public function destroy(FicheTechnique $fiche)
    {
        // Les suivis partent avec la fiche (cascade en base).
        $fiche->delete();

        return redirect()
            ->route('erp.fiches.index')
            ->with('success', 'Fiche supprimée.');
    }

    public function export(Request $request): StreamedResponse
    {
        $nom = 'fiches-techniques-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Référence', 'Organisation', 'Type', 'Secteur', 'Commune',
                'Téléphone', 'Email', 'Employés', 'Contact', 'Fonction',
                'Décideur identifié', 'Besoin', 'Potentiel', 'Score total',
                'Qualifié', 'Statut', 'Prochaine action', 'Relance',
                'Agent', 'Responsable', 'Suivis', 'Créée le',
            ], ';', '"', '');

            FicheTechnique::withCount('suivis')->orderBy('id')->chunk(200, function ($lot) use ($out) {
                foreach ($lot as $f) {
                    fputcsv($out, [
                        $f->reference, $f->nom_organisation,
                        FicheTechnique::typesOrganisation()[$f->type_organisation] ?? $f->type_organisation,
                        FicheTechnique::secteurs()[$f->secteur] ?? $f->secteur,
                        $f->commune, $f->telephone, $f->email, $f->taille_employes,
                        $f->contact_nom,
                        FicheTechnique::fonctions()[$f->contact_fonction] ?? $f->contact_fonction,
                        $f->decideur_nom ?: ($f->est_decideur ? $f->contact_nom : ''),
                        $f->score_besoin, $f->score_potentiel, $f->score_total,
                        $f->est_qualifie ? 'Oui' : 'Non',
                        FicheTechnique::statuts()[$f->statut] ?? $f->statut,
                        FicheTechnique::prochainesActions()[$f->prochaine_action] ?? $f->prochaine_action,
                        $f->date_relance?->format('d/m/Y'),
                        $f->agent, $f->responsable_assigne, $f->suivis_count,
                        $f->created_at->format('d/m/Y H:i'),
                    ], ';', '"', '');
                }
            });

            fclose($out);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
