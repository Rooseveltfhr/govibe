<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\PreuvePaiement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PreuvePaiementAdminController extends Controller
{
    public function index(Request $request)
    {
        $requete = PreuvePaiement::query()->latest();

        if ($statut = $request->query('statut')) {
            $requete->where('statut', $statut);
        }

        if ($q = trim((string) $request->query('q'))) {
            $requete->where(function ($w) use ($q) {
                $w->where('nom', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%")
                    ->orWhere('reference', 'like', "%{$q}%")
                    ->orWhere('transaction_id', 'like', "%{$q}%")
                    ->orWhere('motif', 'like', "%{$q}%");
            });
        }

        return view('erp.preuves.index', [
            'preuves' => $requete->paginate(25)->withQueryString(),
            'stats' => [
                'total' => PreuvePaiement::count(),
                'en_attente' => PreuvePaiement::where('statut', 'recue')->count(),
                'verifiees' => PreuvePaiement::where('statut', 'verifiee')->count(),
                'rejetees' => PreuvePaiement::where('statut', 'rejetee')->count(),
            ],
        ]);
    }

    public function show(PreuvePaiement $preuve)
    {
        return view('erp.preuves.show', compact('preuve'));
    }

    /**
     * La capture vit sur le disque privé. Elle est servie ici, derrière
     * l'authentification ERP, plutôt que par une URL publique.
     */
    public function fichier(PreuvePaiement $preuve)
    {
        abort_if(! $preuve->fichier || ! Storage::exists($preuve->fichier), 404);

        return Storage::response(
            $preuve->fichier,
            $preuve->fichier_nom_origine ?: basename($preuve->fichier),
            ['Content-Type' => $preuve->fichier_mime ?: 'application/octet-stream'],
            'inline'
        );
    }

    public function updateStatut(Request $request, PreuvePaiement $preuve)
    {
        $valide = $request->validate([
            'statut' => ['required', Rule::in(array_keys(PreuvePaiement::statuts()))],
            'commentaire_admin' => 'nullable|string|max:2000',
        ]);

        $preuve->update([
            'statut' => $valide['statut'],
            'commentaire_admin' => $valide['commentaire_admin'] ?? $preuve->commentaire_admin,
            'verifiee_par' => auth()->user()?->name ?? auth()->user()?->email,
            'verifiee_le' => now(),
        ]);

        return back()->with('success', "Preuve {$preuve->reference} marquée « {$preuve->statut_libelle} ».");
    }

    public function destroy(PreuvePaiement $preuve)
    {
        // Le fichier part avec la fiche : une capture orpheline sur le disque
        // resterait lisible sans que rien ne la référence.
        if ($preuve->fichier && Storage::exists($preuve->fichier)) {
            Storage::delete($preuve->fichier);
        }

        $reference = $preuve->reference;
        $preuve->delete();

        return redirect()->route('erp.preuves.index')
            ->with('success', "Preuve {$reference} supprimée.");
    }
}
