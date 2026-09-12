<?php

namespace App\Http\Controllers;

use App\Models\ReservationLandry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class LandryController extends Controller
{
    public function index()
    {
        return view('landry.index', $this->contexte());
    }

    public function store(Request $request)
    {
        $valide = $request->validate([
            'nom_complet' => 'required|string|max:150',
            'whatsapp' => 'required|string|max:40',
            'adresse' => 'required|string|max:2000',
            'point_repere' => 'nullable|string|max:255',

            'mode_service' => ['required', Rule::in(array_keys(ReservationLandry::modesService()))],
            'instructions_recuperation' => 'nullable|string|max:2000',
            'mode_facturation' => ['required', Rule::in(array_keys(ReservationLandry::modesFacturation()))],
            'quantite_vetements' => 'nullable|integer|min:1|max:10000',
            'frequence' => ['required', Rule::in(array_keys(ReservationLandry::frequences()))],

            'mode_paiement' => ['required', Rule::in(array_keys(ReservationLandry::modesPaiement()))],
            'frais_inscription' => 'required|in:oui,non',
        ], [
            'nom_complet.required' => 'Indiquez votre nom complet.',
            'whatsapp.required' => 'Indiquez votre numéro WhatsApp.',
            'adresse.required' => 'Indiquez votre adresse.',
            'mode_service.required' => 'Choisissez comment vous voulez prendre le service.',
            'mode_facturation.required' => 'Choisissez comment vous voulez être facturé.',
            'frequence.required' => 'Indiquez votre fréquence de lavage.',
            'mode_paiement.required' => 'Choisissez comment vous voulez payer.',
            'frais_inscription.required' => "Répondez à la question sur les frais d'inscription.",
        ]);

        $accepte = $valide['frais_inscription'] === 'oui';

        $reservation = ReservationLandry::create([
            'reference' => ReservationLandry::genererReference(),
            'nom_complet' => $valide['nom_complet'],
            'whatsapp' => $valide['whatsapp'],
            'adresse' => $valide['adresse'],
            'point_repere' => $valide['point_repere'] ?? null,

            'mode_service' => $valide['mode_service'],
            // Les instructions ne concernent que la récupération à domicile :
            // gardées sur une réservation au local, elles induiraient l'équipe
            // en erreur au moment de la tournée.
            'instructions_recuperation' => $valide['mode_service'] === 'domicile'
                ? ($valide['instructions_recuperation'] ?? null)
                : null,
            'mode_facturation' => $valide['mode_facturation'],
            // Même logique : une quantité n'a de sens qu'à l'unité.
            'quantite_vetements' => $valide['mode_facturation'] === 'unite'
                ? ($valide['quantite_vetements'] ?? null)
                : null,
            'frequence' => $valide['frequence'],

            'mode_paiement' => $valide['mode_paiement'],
            'accepte_frais_inscription' => $accepte,
            // Montant figé : le tarif d'inscription peut changer plus tard.
            'frais_inscription' => $accepte ? (float) config('govibe.landry.frais_inscription') : null,
            'devise' => config('govibe.landry.devise', 'HTG'),

            'statut' => 'nouvelle',
            'ip' => $request->ip(),
        ]);

        // La confirmation affiche le nom et l'adresse d'une personne : elle
        // passe par la session, pas par un identifiant dans l'URL.
        return redirect()->route('landry.merci')->with('reservation_id', $reservation->id);
    }

    public function merci(Request $request)
    {
        $id = session('reservation_id');

        if (! $id || ! ($reservation = ReservationLandry::find($id))) {
            return redirect()->route('landry.index');
        }

        $request->session()->reflash();

        return view('landry.merci', array_merge($this->contexte(), [
            'reservation' => $reservation,
        ]));
    }

    /** Dates de campagne et frais, partagés par les deux vues. */
    private function contexte(): array
    {
        $debut = Carbon::parse(config('govibe.landry.ouverture_reservations'))->startOfDay();
        $ouverture = Carbon::parse(config('govibe.landry.ouverture_services'))->startOfDay();
        $maintenant = now();

        $total = max(1, $debut->diffInSeconds($ouverture));
        $ecoule = max(0, $debut->diffInSeconds($maintenant, false));
        $progression = max(0, min(100, ($ecoule / $total) * 100));

        return [
            'debut' => $debut,
            'ouverture' => $ouverture,
            'progression' => round($progression),
            'joursRestants' => max(0, (int) ceil($maintenant->diffInSeconds($ouverture, false) / 86400)),
            'ouvert' => $maintenant->greaterThanOrEqualTo($ouverture),
            'frais' => (float) config('govibe.landry.frais_inscription'),
            'devise' => config('govibe.landry.devise', 'HTG'),
            'adresse' => config('govibe.landry.adresse'),
            'whatsapp' => preg_replace('/\D+/', '', (string) config('govibe.landry.whatsapp')),
        ];
    }
}
