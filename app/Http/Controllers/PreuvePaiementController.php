<?php

namespace App\Http\Controllers;

use App\Models\PasserellePaiement;
use App\Models\PreuvePaiement;
use Illuminate\Http\Request;

class PreuvePaiementController extends Controller
{
    public function create(Request $request)
    {
        return view('paiement.preuve', [
            'passerelles' => PasserellePaiement::actif()->get()->reject->est_incomplete->values(),
            'moyen' => $request->query('moyen'),
            'montant' => $request->query('montant'),
            'motif' => $request->query('motif'),
        ]);
    }

    public function store(Request $request)
    {
        $valide = $request->validate([
            'nom' => 'required|string|max:150',
            'telephone' => 'required|string|max:40',
            'email' => 'nullable|email|max:190',
            'moyen' => 'nullable|string|max:60',
            'montant' => 'nullable|numeric|min:0|max:99999999',
            'devise' => 'nullable|in:HTG,USD',
            'transaction_id' => 'nullable|string|max:120',
            'motif' => 'nullable|string|max:200',
            'note' => 'nullable|string|max:2000',

            // La capture est le cœur de l'envoi : sans elle rien ne prouve
            // le paiement. Le SVG est refusé — il peut porter du script.
            'capture' => 'required|file|mimes:jpeg,jpg,png,webp,heic,pdf|max:8192',
        ], [
            'capture.required' => 'Ajoutez la capture d\'écran du paiement.',
            'capture.mimes' => 'Envoyez une image (JPG, PNG, WEBP, HEIC) ou un PDF.',
            'capture.max' => 'Le fichier dépasse 8 Mo. Réduisez-le avant de l\'envoyer.',
        ]);

        $fichier = $request->file('capture');

        // Disque privé : une preuve de paiement porte des identifiants de
        // compte, elle ne doit pas être servie par une URL devinable. Elle
        // n'est lisible que par l'ERP, authentifié.
        $chemin = $fichier->store('preuves-paiement');

        $passerelle = empty($valide['moyen'])
            ? null
            : PasserellePaiement::where('code', $valide['moyen'])->first();

        $preuve = PreuvePaiement::create([
            'reference' => PreuvePaiement::genererReference(),
            'nom' => $valide['nom'],
            'telephone' => $valide['telephone'],
            'email' => $valide['email'] ?? null,
            'moyen' => $valide['moyen'] ?? null,
            'moyen_nom' => $passerelle?->nom,
            'montant' => $valide['montant'] ?? null,
            'devise' => $valide['devise'] ?? 'HTG',
            'transaction_id' => $valide['transaction_id'] ?? null,
            'motif' => $valide['motif'] ?? null,
            'note' => $valide['note'] ?? null,
            'fichier' => $chemin,
            'fichier_nom_origine' => $fichier->getClientOriginalName(),
            'fichier_taille' => $fichier->getSize(),
            'fichier_mime' => $fichier->getClientMimeType(),
            'ip' => $request->ip(),
        ]);

        // La confirmation passe par la session plutôt que par un identifiant
        // dans l'URL : cette page affiche le nom, le montant et la référence
        // d'un client, et une URL numérotée se parcourt de 1 à N.
        return redirect()->route('paiement.preuve.merci')
            ->with('preuve_id', $preuve->id);
    }

    public function merci(Request $request)
    {
        $id = session('preuve_id');

        if (! $id) {
            return redirect()->route('paiement');
        }

        $preuve = PreuvePaiement::find($id);

        if (! $preuve) {
            return redirect()->route('paiement');
        }

        // Rejouée telle quelle au rafraîchissement, sans repasser par l'envoi.
        $request->session()->reflash();

        return view('paiement.preuve-merci', compact('preuve'));
    }
}
