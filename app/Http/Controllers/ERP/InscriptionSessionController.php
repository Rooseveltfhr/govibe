<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\InscriptionSession;
use App\Models\SessionFormation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class InscriptionSessionController extends Controller
{
    public function index(Request $request)
    {
        $requete = InscriptionSession::with('session')->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }
        if ($formation = $request->query('formation')) {
            $requete->whereHas('session', fn ($q) => $q->where('slug', $formation));
        }
        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('nom_complet', 'like', "%{$q}%")
                    ->orWhere('whatsapp', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%");
            });
        }

        return view('erp.formations.index', [
            'inscriptions' => $requete->paginate(25)->withQueryString(),
            'formations' => SessionFormation::orderBy('ordre')->get(),
            'stats' => [
                'total' => InscriptionSession::count(),
                'a_verifier' => InscriptionSession::where('statut', 'a_verifier')->count(),
                'confirmees' => InscriptionSession::where('statut', 'confirmee')->count(),
                'encaisse' => InscriptionSession::where('statut', 'confirmee')->sum('montant'),
            ],
        ]);
    }

    public function show(InscriptionSession $inscription)
    {
        return view('erp.formations.show', compact('inscription'));
    }

    /**
     * La preuve vit sur le disque privé : elle est servie ici, derrière
     * l'authentification ERP, jamais par une URL publique.
     */
    public function fichier(InscriptionSession $inscription)
    {
        abort_if(! $inscription->fichier || ! Storage::exists($inscription->fichier), 404);

        return Storage::response(
            $inscription->fichier,
            $inscription->fichier_nom_origine ?: basename($inscription->fichier),
            ['Content-Type' => $inscription->fichier_mime ?: 'application/octet-stream'],
            'inline'
        );
    }

    public function update(Request $request, InscriptionSession $inscription)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(InscriptionSession::statuts()))],
            'commentaire_admin' => 'nullable|string|max:2000',
        ]);

        $inscription->update([
            'statut' => $valide['statut'],
            'commentaire_admin' => $valide['commentaire_admin'] ?? $inscription->commentaire_admin,
            'verifiee_par' => auth()->user()?->name ?? auth()->user()?->email,
            'verifiee_le' => now(),
        ]);

        return back()->with('success', "Inscription {$inscription->reference} marquée « {$inscription->statut_libelle} ».");
    }

    public function destroy(InscriptionSession $inscription)
    {
        // La preuve part avec l'inscription : un fichier orphelin resterait
        // lisible sans que rien ne le référence.
        if ($inscription->fichier && Storage::exists($inscription->fichier)) {
            Storage::delete($inscription->fichier);
        }

        $reference = $inscription->reference;
        $inscription->delete();

        return redirect()->route('erp.formations.index')
            ->with('success', "Inscription {$reference} supprimée.");
    }

    public function export(Request $request)
    {
        $requete = InscriptionSession::with('session')->latest();
        if ($formation = $request->query('formation')) {
            $requete->whereHas('session', fn ($q) => $q->where('slug', $formation));
        }

        $nom = 'inscriptions-formation-'.now()->format('Ymd-Hi').'.csv';

        return response()->streamDownload(function () use ($requete) {
            $sortie = fopen('php://output', 'w');
            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, [
                'Référence', 'Formation', 'Nom complet', 'WhatsApp', 'Suivi',
                'Montant', 'Devise', 'Moyen', 'Statut', 'Inscrit le',
            ], ';', '"', '');

            $requete->chunk(200, function ($lot) use ($sortie) {
                foreach ($lot as $i) {
                    fputcsv($sortie, [
                        $i->reference,
                        $i->session?->titre,
                        $i->nom_complet,
                        $i->whatsapp,
                        $i->mode_lisible,
                        $i->montant,
                        $i->devise,
                        $i->moyen_paiement_nom ?: $i->moyen_paiement,
                        $i->statut_libelle,
                        $i->created_at->format('d/m/Y H:i'),
                    ], ';', '"', '');
                }
            });

            fclose($sortie);
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
