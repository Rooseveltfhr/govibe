<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\ReservationLandry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReservationLandryController extends Controller
{
    public function index(Request $request)
    {
        $requete = ReservationLandry::latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($service = $request->query('mode_service')) {
            $requete->where('mode_service', $service);
        }
        if ($request->query('frais') === 'oui') {
            $requete->where('accepte_frais_inscription', true);
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('nom_complet', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('adresse', 'like', "%{$q}%");
            });
        }

        return view('erp.landry.index', [
            'reservations' => $requete->paginate(25)->withQueryString(),
            'stats' => [
                'total' => ReservationLandry::count(),
                'nouvelles' => ReservationLandry::nouvelles()->count(),
                'confirmees' => ReservationLandry::where('statut', 'confirmee')->count(),
                'payantes' => ReservationLandry::where('accepte_frais_inscription', true)->count(),
            ],
            'repartition' => [
                'domicile' => ReservationLandry::where('mode_service', 'domicile')->count(),
                'local' => ReservationLandry::where('mode_service', 'local')->count(),
            ],
        ]);
    }

    public function show(ReservationLandry $reservation)
    {
        return view('erp.landry.show', compact('reservation'));
    }

    public function update(Request $request, ReservationLandry $reservation)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(ReservationLandry::statuts()))],
            'notes_internes' => 'nullable|string|max:2000',
        ]);

        $reservation->update([
            'statut' => $valide['statut'],
            'notes_internes' => $valide['notes_internes'] ?? $reservation->notes_internes,
            'traitee_par' => $request->user()->id,
            'traitee_le' => now(),
        ]);

        return back()->with('success', "Réservation {$reservation->reference} mise à jour.");
    }

    public function destroy(ReservationLandry $reservation)
    {
        $reference = $reservation->reference;
        $reservation->delete();

        return redirect()->route('erp.landry.index')
            ->with('success', "Réservation {$reference} supprimée.");
    }

    public function export()
    {
        $nom = 'reservations-landry-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () {
            $sortie = fopen('php://output', 'w');
            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Référence', 'Nom', 'WhatsApp', 'Adresse', 'Point de repère',
                'Service', 'Instructions', 'Facturation', 'Quantité', 'Fréquence',
                'Paiement', 'Inscription', 'Statut', 'Reçue le',
            ], ';', '"', '');

            ReservationLandry::latest()->chunk(200, function ($lot) use ($sortie) {
                foreach ($lot as $r) {
                    fputcsv($sortie, [
                        $r->reference, $r->nom_complet, $r->whatsapp, $r->adresse,
                        $r->point_repere, $r->mode_service_libelle, $r->instructions_recuperation,
                        $r->mode_facturation_libelle, $r->quantite_vetements, $r->frequence_libelle,
                        $r->mode_paiement_libelle,
                        $r->accepte_frais_inscription ? $r->frais_affiche : 'Non',
                        $r->statut_libelle, $r->created_at->format('d/m/Y H:i'),
                    ], ';', '"', '');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
