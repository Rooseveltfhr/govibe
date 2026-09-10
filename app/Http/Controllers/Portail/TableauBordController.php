<?php

namespace App\Http\Controllers\Portail;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DemandeAgentIa;
use App\Models\InscriptionSession;
use App\Models\Invoice;

class TableauBordController extends Controller
{
    /**
     * Le portail rassemble ce que le client a avec GOVIBE, toutes unités
     * d'affaires confondues : un seul compte, une seule facturation.
     */
    public function index()
    {
        $compte = auth('client')->user();
        $clientId = $compte->client_id;

        $factures = Invoice::where('client_id', $clientId)->latest('issued_date')->take(5)->get();

        return view('portail.tableau-bord', [
            'compte' => $compte,
            'client' => $compte->client,
            'factures' => $factures,
            'aRegler' => Invoice::where('client_id', $clientId)
                ->whereNotIn('status', ['paid', 'payee', 'cancelled', 'annulee'])
                ->sum('total'),
            'services' => $this->servicesDuClient($clientId),
        ]);
    }

    public function services()
    {
        $compte = auth('client')->user();

        return view('portail.services', [
            'compte' => $compte,
            'services' => $this->servicesDuClient($compte->client_id),
        ]);
    }

    public function factures()
    {
        $compte = auth('client')->user();

        return view('portail.factures', [
            'compte' => $compte,
            'factures' => Invoice::where('client_id', $compte->client_id)
                ->latest('issued_date')->paginate(20),
        ]);
    }

    /**
     * Chaque unité d'affaires rend la même forme, pour que le portail les
     * présente ensemble sans connaître leurs tables.
     */
    private function servicesDuClient(int $clientId): array
    {
        $agents = DemandeAgentIa::where('client_id', $clientId)->latest()->get()
            ->map(fn ($d) => [
                'unite' => 'Agents IA',
                'icone' => 'fa-robot',
                'titre' => $d->agent_nom,
                'detail' => $d->canal_lisible,
                'statut' => $d->statut_libelle,
                'etat' => $d->statut === 'actif' ? 'actif' : ($d->statut === 'termine' ? 'termine' : 'en_cours'),
                'date' => $d->created_at,
                'reference' => $d->reference,
            ]);

        $formations = InscriptionSession::with('session')->where('client_id', $clientId)->latest()->get()
            ->map(fn ($i) => [
                'unite' => 'Formations',
                'icone' => 'fa-graduation-cap',
                'titre' => $i->session?->titre ?? 'Formation',
                'detail' => $i->mode_lisible,
                'statut' => $i->statut_libelle,
                'etat' => $i->statut === 'confirmee' ? 'actif' : ($i->statut === 'rejetee' ? 'termine' : 'en_cours'),
                'date' => $i->created_at,
                'reference' => $i->reference,
            ]);

        $reservations = Booking::where('client_id', $clientId)->latest()->get()
            ->map(fn ($b) => [
                'unite' => 'Espaces',
                'icone' => 'fa-calendar-check',
                'titre' => $b->title ?: 'Réservation',
                'detail' => $b->start_datetime?->format('d/m/Y H:i'),
                'statut' => ucfirst((string) $b->status),
                'etat' => $b->status === 'cancelled' ? 'termine' : 'en_cours',
                'date' => $b->created_at,
                'reference' => $b->reference,
            ]);

        return $agents->concat($formations)->concat($reservations)
            ->sortByDesc('date')->values()->all();
    }
}
