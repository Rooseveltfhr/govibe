<?php

namespace App\Http\Controllers;

use App\Models\FicheTechnique;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FicheTechniqueController extends Controller
{
    public function create()
    {
        return view('fiche-technique.create');
    }

    public function store(Request $request)
    {
        $valide = $request->validate([
            // Identification — le minimum pour qu'une fiche soit exploitable.
            'nom_organisation'  => 'required|string|max:200',
            'nom_commercial'    => 'nullable|string|max:200',
            'type_organisation' => ['required', Rule::in(array_keys(FicheTechnique::typesOrganisation()))],
            'secteur'           => ['nullable', Rule::in(array_keys(FicheTechnique::secteurs()))],
            'commune'           => 'nullable|string|max:120',
            'adresse'           => 'nullable|string|max:255',
            'telephone'         => 'nullable|string|max:40',
            'email'             => 'nullable|email|max:190',
            'taille_employes'   => ['nullable', Rule::in(array_keys(FicheTechnique::taillesEmployes()))],

            'contact_nom'       => 'nullable|string|max:150',
            'contact_fonction'  => ['nullable', Rule::in(array_keys(FicheTechnique::fonctions()))],
            'contact_telephone' => 'nullable|string|max:40',
            'contact_email'     => 'nullable|email|max:190',
            'est_decideur'      => 'nullable|in:1,0,partiel',
            'decideur_nom'      => 'nullable|string|max:150',
            'decideur_contact'  => 'nullable|string|max:120',

            // Qualification — remplie par l'agent, pas par le prospect.
            'score_besoin'    => 'required|integer|min:0|max:4',
            'score_potentiel' => 'required|integer|min:0|max:4',
            'rendez_vous_possible' => 'nullable|in:1,0,relancer',
            'prochaine_action' => ['nullable', Rule::in(array_keys(FicheTechnique::prochainesActions()))],
            'date_relance'     => 'nullable|date',
            'agent'            => 'required|string|max:120',
            'responsable_assigne' => 'nullable|string|max:120',
            'observation_agent'   => 'nullable|string|max:3000',

            // Questionnaire libre.
            'reponses'   => 'nullable|array',
            'reponses.*' => 'nullable',
        ], [
            'nom_organisation.required'  => "Le nom de l'organisation est obligatoire.",
            'type_organisation.required' => "Le type d'organisation est obligatoire.",
            'agent.required'             => 'Indiquez quel agent remplit la fiche.',
            'score_besoin.required'      => 'Évaluez le besoin numérique.',
            'score_potentiel.required'   => 'Évaluez le potentiel commercial.',
        ]);

        // « Partiellement » n'est ni oui ni non : la nuance part dans les
        // réponses, la colonne ne garde que le tranché.
        $valide['reponses'] = array_merge($valide['reponses'] ?? [], [
            'est_decideur_brut'         => $request->input('est_decideur'),
            'rendez_vous_possible_brut' => $request->input('rendez_vous_possible'),
        ]);

        $valide['est_decideur'] = $request->input('est_decideur') === '1'
            ? true
            : ($request->input('est_decideur') === '0' ? false : null);

        $valide['rendez_vous_possible'] = $request->input('rendez_vous_possible') === '1'
            ? true
            : ($request->input('rendez_vous_possible') === '0' ? false : null);

        $fiche = FicheTechnique::create($valide + [
            'reference' => FicheTechnique::genererReference(),
            'statut'    => 'nouveau',
        ]);

        return redirect()
            ->route('fiche-technique.merci', $fiche)
            ->with('success', true);
    }

    public function merci(FicheTechnique $fiche)
    {
        return view('fiche-technique.merci', compact('fiche'));
    }
}
