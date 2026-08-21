<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use Illuminate\Http\Request;

class PartenaireAdminController extends Controller
{
    public function index(Request $request)
    {
        $query = Partenaire::latest();

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }
        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->where('nom', 'like', "%$q%")
                  ->orWhere('prenom', 'like', "%$q%")
                  ->orWhere('organisation', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%");
            });
        }

        $partenaires = $query->paginate(20)->withQueryString();
        $stats = [
            'total'    => Partenaire::count(),
            'nouveau'  => Partenaire::where('statut', 'nouveau')->count(),
            'en_cours' => Partenaire::where('statut', 'en_cours')->count(),
            'accepte'  => Partenaire::where('statut', 'accepte')->count(),
        ];

        return view('erp.partenaires.index', compact('partenaires', 'stats'));
    }

    public function updateStatut(Request $request, Partenaire $partenaire)
    {
        $request->validate([
            'statut'      => 'required|in:nouveau,en_cours,accepte,refuse',
            'notes_admin' => 'nullable|string|max:1000',
        ]);

        $partenaire->update([
            'statut'      => $request->statut,
            'notes_admin' => $request->notes_admin,
        ]);

        return back()->with('success', 'Statut mis à jour.');
    }

    public function destroy(Partenaire $partenaire)
    {
        $partenaire->delete();
        return back()->with('success', 'Demande supprimée.');
    }
}
