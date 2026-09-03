<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Partenaire;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Vitrine publique : logo, site web, visibilité et ordre d'affichage.
     */
    public function updateVitrine(Request $request, Partenaire $partenaire)
    {
        $request->validate([
            // mimes en plus de « image » : bloque les SVG, qui peuvent porter
            // du script exécuté par le navigateur des visiteurs.
            'logo'           => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'site_web'       => 'nullable|url|max:255',
            'affiche_public' => 'nullable|boolean',
            'ordre'          => 'nullable|integer|min:0|max:9999',
        ], [
            'logo.image' => 'Le logo doit être une image (JPG, PNG ou WEBP).',
            'logo.mimes' => 'Formats acceptés : JPG, PNG, WEBP.',
            'logo.max'   => 'Le logo ne doit pas dépasser 2 Mo.',
            'site_web.url' => 'Le site web doit être une URL complète (https://…).',
        ]);

        $data = [
            'site_web'       => $request->site_web,
            'affiche_public' => $request->boolean('affiche_public'),
            'ordre'          => $request->integer('ordre'),
        ];

        if ($request->hasFile('logo')) {
            // L'ancien fichier est supprimé pour ne pas accumuler d'orphelins.
            if ($partenaire->logo) {
                Storage::disk('public')->delete($partenaire->logo);
            }
            $data['logo'] = $request->file('logo')->store('partenaires', 'public');
        }

        $partenaire->update($data);

        return back()->with('success', 'Vitrine mise à jour.');
    }

    /**
     * Retire le logo sans supprimer la demande de partenariat.
     */
    public function destroyLogo(Partenaire $partenaire)
    {
        if ($partenaire->logo) {
            Storage::disk('public')->delete($partenaire->logo);
            $partenaire->update(['logo' => null]);
        }

        return back()->with('success', 'Logo supprimé.');
    }

    public function destroy(Partenaire $partenaire)
    {
        // Le fichier suit la demande, sinon il resterait sur le disque.
        if ($partenaire->logo) {
            Storage::disk('public')->delete($partenaire->logo);
        }

        $partenaire->delete();
        return back()->with('success', 'Demande supprimée.');
    }
}
