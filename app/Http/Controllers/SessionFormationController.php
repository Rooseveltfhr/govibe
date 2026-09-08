<?php

namespace App\Http\Controllers;

use App\Models\InscriptionSession;
use App\Models\PasserellePaiement;
use App\Models\SessionFormation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SessionFormationController extends Controller
{
    public function show(SessionFormation $formation)
    {
        abort_unless($formation->actif, 404);

        return view('formations.show', [
            'formation' => $formation,
            'passerelles' => PasserellePaiement::actif()->get()->reject->est_incomplete->values(),
        ]);
    }

    public function store(Request $request, SessionFormation $formation)
    {
        abort_unless($formation->actif, 404);

        if (! $formation->accepte_inscriptions) {
            return back()->withInput()->withErrors([
                'nom_complet' => 'Les inscriptions pour cette formation sont closes. Écrivez-nous sur WhatsApp.',
            ]);
        }

        $valide = $request->validate([
            'nom_complet' => 'required|string|max:150',
            'whatsapp' => 'required|string|max:40',
            'mode' => ['required', Rule::in($formation->modes ?: array_keys(SessionFormation::modesDisponibles()))],
            'moyen_paiement' => 'required|string|max:60',

            // La preuve est exigée : une place limitée se réserve avec un
            // paiement, pas avec une intention.
            'preuve' => 'required|file|mimes:jpeg,jpg,png,webp,heic,pdf|max:8192',
        ], [
            'nom_complet.required' => 'Indiquez votre nom complet.',
            'whatsapp.required' => 'Indiquez votre numéro WhatsApp.',
            'mode.required' => 'Choisissez comment vous voulez suivre la formation.',
            'mode.in' => "Ce mode n'est pas proposé pour cette formation.",
            'moyen_paiement.required' => 'Choisissez le moyen par lequel vous avez payé.',
            'preuve.required' => 'Ajoutez la capture de votre paiement.',
            'preuve.mimes' => 'Envoyez une image (JPG, PNG, WEBP, HEIC) ou un PDF.',
            'preuve.max' => 'Le fichier dépasse 8 Mo. Réduisez-le avant de l\'envoyer.',
        ]);

        $fichier = $request->file('preuve');
        // Disque privé : une preuve de paiement porte des identifiants de compte.
        $chemin = $fichier->store('preuves-formation');

        $passerelle = PasserellePaiement::where('code', $valide['moyen_paiement'])->first();

        $inscription = InscriptionSession::create([
            'reference' => InscriptionSession::genererReference(),
            'session_formation_id' => $formation->id,
            'nom_complet' => $valide['nom_complet'],
            'whatsapp' => $valide['whatsapp'],
            'mode' => $valide['mode'],

            // Montant figé : le prix de la session peut changer après coup.
            'montant' => $formation->prix,
            'devise' => $formation->devise,
            'moyen_paiement' => $passerelle?->code ?? $valide['moyen_paiement'],
            'moyen_paiement_nom' => $passerelle?->nom,

            'fichier' => $chemin,
            'fichier_nom_origine' => $fichier->getClientOriginalName(),
            'fichier_taille' => $fichier->getSize(),
            'fichier_mime' => $fichier->getClientMimeType(),

            'statut' => 'a_verifier',
            'ip' => $request->ip(),
        ]);

        // La confirmation affiche le nom et le montant d'une personne : elle
        // passe par la session, pas par un identifiant dans l'URL.
        return redirect()->route('formations.merci', $formation)
            ->with('inscription_id', $inscription->id);
    }

    public function merci(Request $request, SessionFormation $formation)
    {
        $id = session('inscription_id');

        if (! $id || ! ($inscription = InscriptionSession::find($id))) {
            return redirect()->route('formations.show', $formation);
        }

        $request->session()->reflash();

        return view('formations.merci', compact('formation', 'inscription'));
    }
}
