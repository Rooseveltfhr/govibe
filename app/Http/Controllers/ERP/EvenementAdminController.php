<?php

namespace App\Http\Controllers\ERP;

use App\Http\Controllers\Controller;
use App\Models\Evenement;
use App\Models\EvenementReservation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvenementAdminController extends Controller
{
    public function index()
    {
        $evenements = Evenement::withCount('reservations')
            ->orderBy('ordre')
            ->orderByDesc('created_at')
            ->get();

        $stats = [
            'total'        => $evenements->count(),
            'actifs'       => $evenements->where('actif', true)->count(),
            'ouverts'      => $evenements->where('inscriptions_ouvertes', true)->count(),
            'reservations' => EvenementReservation::count(),
        ];

        return view('erp.evenements.index', compact('evenements', 'stats'));
    }

    public function store(Request $request)
    {
        $data = $this->valider($request);

        $data['slug'] = Evenement::genererSlug($data['titre']);
        Evenement::create($data);

        return back()->with('success', 'Événement créé. Son formulaire est en ligne.');
    }

    public function update(Request $request, Evenement $evenement)
    {
        $data = $this->valider($request);

        // Le slug ne suit le titre que si l'URL n'a pas encore été diffusée
        // sans inscription : sinon une publicité en cours cesserait de marcher.
        if ($data['titre'] !== $evenement->titre && $evenement->reservations()->doesntExist()) {
            $data['slug'] = Evenement::genererSlug($data['titre'], $evenement->id);
        }

        $evenement->update($data);

        return back()->with('success', 'Événement mis à jour.');
    }

    public function destroy(Evenement $evenement)
    {
        // Les réservations partent avec l'événement (cascade en base).
        $evenement->delete();

        return back()->with('success', 'Événement supprimé.');
    }

    /**
     * Réservations d'un événement, avec recherche et filtre de statut.
     */
    public function reservations(Request $request, Evenement $evenement)
    {
        $query = $evenement->reservations()->latest();

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($b) use ($q) {
                $b->where('nom', 'like', "%$q%")
                  ->orWhere('prenom', 'like', "%$q%")
                  ->orWhere('email', 'like', "%$q%")
                  ->orWhere('whatsapp', 'like', "%$q%")
                  ->orWhere('ville', 'like', "%$q%");
            });
        }

        if ($request->filled('statut_actuel')) {
            $query->where('statut_actuel', $request->statut_actuel);
        }

        $reservations = $query->paginate(30)->withQueryString();

        $stats = [
            'total'     => $evenement->reservations()->count(),
            'confirmes' => $evenement->reservations()->where('presence_confirmee', true)->count(),
            'femmes'    => $evenement->reservations()->where('sexe', 'femme')->count(),
            'villes'    => $evenement->reservations()->distinct()->count('ville'),
        ];

        return view('erp.evenements.reservations', compact('evenement', 'reservations', 'stats'));
    }

    public function togglePresence(EvenementReservation $reservation)
    {
        $reservation->update(['presence_confirmee' => ! $reservation->presence_confirmee]);

        return back();
    }

    public function destroyReservation(EvenementReservation $reservation)
    {
        $reservation->delete();

        return back()->with('success', 'Inscription supprimée.');
    }

    /**
     * Export CSV des inscrits, pour les organisateurs sur le terrain.
     */
    public function exportReservations(Evenement $evenement): StreamedResponse
    {
        $nomFichier = 'inscriptions-' . $evenement->slug . '-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($evenement) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8 : sans lui Excel casse les accents.
            fwrite($out, "\xEF\xBB\xBF");

            // Séparateur « ; » attendu par Excel en locale française.
            // Échappement explicitement vide : c'est la règle CSV standard
            // (guillemets doublés) et le futur défaut de PHP, dont le
            // changement provoque sinon une dépréciation en 8.4.
            fputcsv($out, [
                'Prénom', 'Nom', 'Email', 'WhatsApp', 'Téléphone',
                'Pays', 'Ville', 'Commune', 'Profession', 'Sexe',
                'Situation matrimoniale', 'Statut actuel', 'Motivation',
                'Présence confirmée', 'Inscrit le',
            ], ';', '"', '');

            // chunk : la mémoire ne dépend pas du nombre d'inscrits.
            $evenement->reservations()->orderBy('id')->chunk(200, function ($lot) use ($out) {
                foreach ($lot as $r) {
                    fputcsv($out, [
                        $r->prenom, $r->nom, $r->email, $r->whatsapp, $r->telephone,
                        $r->pays, $r->ville, $r->commune, $r->profession,
                        $r->sexe_libelle, $r->situation_libelle, $r->statut_libelle,
                        $r->motivation,
                        $r->presence_confirmee ? 'Oui' : 'Non',
                        $r->created_at->format('d/m/Y H:i'),
                    ], ';', '"', '');
                }
            });

            fclose($out);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function valider(Request $request): array
    {
        $data = $request->validate([
            'titre'      => 'required|string|max:200',
            'sous_titre' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'lieu'       => 'nullable|string|max:200',
            'date_debut' => 'nullable|date',
            'date_fin'   => 'nullable|date|after_or_equal:date_debut',
            'whatsapp_group_url' => 'nullable|url|max:255',
            'actif'      => 'nullable|boolean',
            'inscriptions_ouvertes' => 'nullable|boolean',
            'ordre'      => 'nullable|integer|min:0|max:9999',
        ], [
            'titre.required'   => 'Le titre est obligatoire.',
            'date_fin.after_or_equal' => 'La date de fin ne peut pas précéder la date de début.',
            'whatsapp_group_url.url'  => 'Le lien du groupe doit être une URL complète (https://…).',
        ]);

        // Une case décochée n'est pas envoyée : sans ces valeurs explicites,
        // désactiver un événement serait impossible.
        return array_merge($data, [
            'actif'                 => $request->boolean('actif'),
            'inscriptions_ouvertes' => $request->boolean('inscriptions_ouvertes'),
            'ordre'                 => $request->integer('ordre'),
        ]);
    }
}
