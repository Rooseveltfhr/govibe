<?php

namespace App\Http\Controllers;

use App\Models\Evenement;
use App\Models\EvenementReservation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EvenementController extends Controller
{
    /**
     * Formulaire d'inscription commun : le visiteur choisit l'événement dans
     * la liste. C'est l'URL générique à partager.
     */
    public function index()
    {
        $evenements = Evenement::actif()->get();

        return view('evenements.index', [
            'evenements'         => $evenements,
            'evenementSelection' => null,
        ]);
    }

    /**
     * Même formulaire, avec un événement présélectionné. C'est l'URL à mettre
     * en publicité pour un événement précis : /evenements/{slug}
     *
     * Un événement inactif reste accessible par ce lien direct — une publicité
     * déjà diffusée ne doit pas mener à une page introuvable.
     */
    public function show(Evenement $evenement)
    {
        return view('evenements.index', [
            'evenements'         => Evenement::actif()->get(),
            'evenementSelection' => $evenement,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'evenement_id' => [
                'required',
                // Une inscription ne vaut que pour un événement ouvert.
                Rule::exists('evenements', 'id')->where('inscriptions_ouvertes', true),
            ],
            'prenom'    => 'required|string|max:100',
            'nom'       => 'required|string|max:100',
            'email'     => 'required|email|max:255',
            'whatsapp'  => 'required|string|max:30',
            'telephone' => 'nullable|string|max:30',
            'pays'      => 'required|string|max:100',
            'ville'     => 'required|string|max:100',
            'commune'   => 'nullable|string|max:100',
            'profession' => 'nullable|string|max:150',
            'sexe'      => ['nullable', Rule::in(array_keys(EvenementReservation::sexes()))],
            'situation_matrimoniale' => ['nullable', Rule::in(array_keys(EvenementReservation::situationsMatrimoniales()))],
            'statut_actuel' => ['nullable', Rule::in(array_keys(EvenementReservation::statutsActuels()))],
            'motivation' => 'nullable|string|max:2000',
        ], [
            'evenement_id.required' => "Veuillez sélectionner un événement.",
            'evenement_id.exists'   => "Les inscriptions pour cet événement ne sont pas ouvertes.",
            'prenom.required'   => 'Le prénom est obligatoire.',
            'nom.required'      => 'Le nom est obligatoire.',
            'email.required'    => "L'adresse email est obligatoire.",
            'email.email'       => "L'adresse email n'est pas valide.",
            'whatsapp.required' => 'Le numéro WhatsApp est obligatoire.',
            'pays.required'     => 'Le pays est obligatoire.',
            'ville.required'    => 'La ville est obligatoire.',
        ]);

        $evenement = Evenement::findOrFail($validated['evenement_id']);

        // Une même adresse ne s'inscrit qu'une fois par événement : on met à
        // jour plutôt que de rejeter, pour ne pas bloquer une correction.
        $reservation = EvenementReservation::updateOrCreate(
            [
                'evenement_id' => $evenement->id,
                'email'        => $validated['email'],
            ],
            $validated
        );

        return redirect()
            ->route('evenements.confirmation', [$evenement, 'ref' => $reservation->id])
            ->with('success', true);
    }

    /**
     * Page de confirmation : porte le lien du groupe WhatsApp de l'événement.
     */
    public function confirmation(Request $request, Evenement $evenement)
    {
        $reservation = EvenementReservation::where('evenement_id', $evenement->id)
            ->find($request->query('ref'));

        // Sans référence valide, on renvoie au formulaire plutôt que d'afficher
        // une confirmation qui ne correspond à aucune inscription.
        if (! $reservation) {
            return redirect()->route('evenements.show', $evenement);
        }

        return view('evenements.confirmation', compact('evenement', 'reservation'));
    }
}
