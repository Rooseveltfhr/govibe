<?php

namespace App\Http\Controllers;

use App\Models\Paiement;
use App\Services\PaiementService;
use Illuminate\Http\Request;

class PaiementRetourController extends Controller
{
    public function __construct(private PaiementService $service) {}

    /**
     * Retour du client depuis la passerelle.
     *
     * Rien n'est lu dans cette requête : ni montant, ni statut, ni référence.
     * Une URL de retour se falsifie en la retapant. Le paiement est retrouvé
     * par son identifiant interne, puis c'est la passerelle qu'on interroge.
     */
    public function retour(Request $request, Paiement $paiement)
    {
        $paiement = $this->service->verifier($paiement);

        return view('paiement.retour', compact('paiement'));
    }

    /**
     * Notification serveur à serveur, quand la passerelle en émet une.
     *
     * Elle ne sert que de signal : le verdict est redemandé à la passerelle,
     * exactement comme au retour du navigateur. Un appel forgé ne peut donc
     * rien créditer.
     */
    public function notification(Request $request, Paiement $paiement)
    {
        $this->service->verifier($paiement);

        return response()->json(['recu' => true]);
    }
}
