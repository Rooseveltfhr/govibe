<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\EvenementAbonnement;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use App\Models\TauxChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbonnementService
{
    /**
     * Souscrit un client à un plan.
     *
     * Le prix, la devise et le taux de taxe sont copiés maintenant : un tarif
     * révisé le mois prochain ne réécrit pas ce contrat.
     */
    public function souscrire(
        Client $client,
        Plan $plan,
        string $cycle = 'mensuel',
        ?Carbon $debut = null,
    ): Abonnement {
        $debut = ($debut ?? now())->startOfDay();
        $prix = $plan->prixPour($cycle);

        if ($prix === null) {
            throw new \InvalidArgumentException(
                "Le plan « {$plan->nom} » n'a pas de tarif pour un cycle {$cycle}."
            );
        }

        // Dernier jour de l'essai, le premier compris : un essai de 14 jours
        // couvre 14 jours, pas 15. Le décalage d'un jour se répète sur chaque
        // souscription.
        $essaiFin = $plan->essai_jours > 0
            ? $debut->copy()->addDays($plan->essai_jours - 1)
            : null;

        // Pendant l'essai, rien n'est facturé : la première échéance tombe à
        // la fin de l'essai, pas au premier jour.
        $premiereFacture = $essaiFin ? $essaiFin->copy()->addDay() : $debut->copy();

        $abonnement = Abonnement::create([
            'reference' => Abonnement::genererReference(),
            'client_id' => $client->id,
            'plan_id' => $plan->id,
            'service' => $plan->service,
            'plan_nom' => $plan->nom,
            'statut' => $essaiFin ? 'essai' : 'actif',
            'cycle' => $cycle,
            'prix_unitaire' => $prix,
            'devise' => $plan->devise,
            'tca_taux' => $plan->tca_taux,
            // Gelé à la souscription, comme le prix.
            'taux_change_htg' => TauxChange::actuel($plan->devise, 'HTG'),

            'date_debut' => $debut,
            'essai_fin' => $essaiFin,
            'periode_debut' => $debut,
            'periode_fin' => $essaiFin ?: null,
            'date_prochaine_facture' => $premiereFacture,
            'renouvellement_auto' => true,
        ]);

        $this->tracer($abonnement, 'souscription', null, $abonnement->statut, 'systeme');

        return $abonnement;
    }

    /**
     * Émet la facture de la période à venir et avance l'échéance.
     *
     * Idempotent par période : rejouer la tâche le même jour ne produit pas
     * une seconde facture pour la même période.
     */
    public function facturer(Abonnement $abonnement, ?Carbon $aujourdhui = null): ?Invoice
    {
        $aujourdhui = ($aujourdhui ?? now())->startOfDay();

        return DB::transaction(function () use ($abonnement, $aujourdhui) {
            $abonnement = Abonnement::lockForUpdate()->find($abonnement->id);

            if (! $abonnement->estEnService() || ! $abonnement->renouvellement_auto) {
                return null;
            }

            $debut = $abonnement->date_prochaine_facture
                ? $abonnement->date_prochaine_facture->copy()
                : $aujourdhui->copy();

            // Une période qui n'a pas commencé ne se facture pas. Sans ce
            // garde-fou, rappeler la méthode encaisserait mois après mois
            // d'avance, chaque appel avançant l'échéance d'un cran.
            if ($debut->greaterThan($aujourdhui)) {
                return null;
            }

            $fin = $abonnement->finDePeriode($debut);

            $existante = Invoice::where('abonnement_id', $abonnement->id)
                ->whereDate('periode_debut', $debut)
                ->first();

            if ($existante) {
                return $existante;
            }

            $ht = $abonnement->montant_ht;
            $taxe = $abonnement->montant_taxe;
            $ttc = $abonnement->montant_ttc;

            // Le taux est gelé ici : le client règle ce qui est écrit sur la
            // facture, même s'il paie trois jours plus tard.
            $taux = TauxChange::actuel($abonnement->devise, 'HTG');

            $facture = Invoice::create([
                'reference' => $this->referenceFacture(),
                'client_id' => $abonnement->client_id,
                'abonnement_id' => $abonnement->id,
                'subtotal' => $ht,
                'tax_rate' => $abonnement->tca_taux,
                'tax_amount' => $taxe,
                'discount' => 0,
                'total' => $ttc,
                'status' => 'sent',
                'issued_date' => $aujourdhui,
                'due_date' => $aujourdhui->copy()->addDays(7),
                'periode_debut' => $debut,
                'periode_fin' => $fin,
                'devise' => $abonnement->devise,
                'taux_change' => $taux,
                'montant_converti' => $taux ? round($ttc * $taux, 2) : null,
                'devise_convertie' => $taux && $abonnement->devise !== 'HTG' ? 'HTG' : null,
            ]);

            InvoiceItem::create([
                'invoice_id' => $facture->id,
                'description' => sprintf(
                    '%s — %s, du %s au %s',
                    $abonnement->plan_nom,
                    $abonnement->cycle_libelle,
                    $debut->format('d/m/Y'),
                    $fin->format('d/m/Y')
                ),
                'quantity' => 1,
                'unit_price' => $ht,
                'total' => $ht,
            ]);

            $abonnement->forceFill([
                'statut' => $abonnement->statut === 'essai' ? 'actif' : $abonnement->statut,
                'periode_debut' => $debut,
                'periode_fin' => $fin,
                'date_prochaine_facture' => $fin->copy()->addDay(),
            ])->save();

            $this->tracer($abonnement, 'facture_emise', null, $abonnement->statut, 'systeme', null, [
                'facture' => $facture->reference,
                'periode' => $debut->toDateString().' → '.$fin->toDateString(),
            ]);

            return $facture;
        });
    }

    /**
     * Constate le règlement d'une facture d'abonnement.
     *
     * Un abonnement en retard redevient actif ; un abonnement suspendu ne se
     * réactive pas tout seul — c'est une décision d'exploitation.
     */
    public function encaisser(Invoice $facture): void
    {
        $facture->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        $abonnement = $facture->abonnement_id ? Abonnement::find($facture->abonnement_id) : null;

        if ($abonnement && $abonnement->statut === 'en_retard') {
            $avant = $abonnement->statut;
            $abonnement->forceFill(['statut' => 'actif'])->save();
            $this->tracer($abonnement, 'regularisation', $avant, 'actif', 'systeme');
        }
    }

    /**
     * Passe en retard les abonnements dont la facture a dépassé l'échéance,
     * puis suspend ceux qui dépassent le délai de grâce.
     *
     * Deux temps volontairement : couper le service d'un client sans l'avoir
     * relancé se paie en litige, parfois en perte de données.
     */
    public function releverLesImpayes(int $joursDeGrace = 14, ?Carbon $aujourdhui = null): array
    {
        $aujourdhui = ($aujourdhui ?? now())->startOfDay();
        $enRetard = 0;
        $suspendus = 0;

        $impayees = Invoice::whereNotNull('abonnement_id')
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->whereDate('due_date', '<', $aujourdhui)
            ->get();

        foreach ($impayees as $facture) {
            $abonnement = Abonnement::find($facture->abonnement_id);

            if (! $abonnement || ! $abonnement->estEnService()) {
                continue;
            }

            $joursDeRetard = $facture->due_date->diffInDays($aujourdhui);

            if ($joursDeRetard >= $joursDeGrace && $abonnement->statut !== 'suspendu') {
                $avant = $abonnement->statut;
                $abonnement->forceFill(['statut' => 'suspendu'])->save();
                $this->tracer($abonnement, 'suspension', $avant, 'suspendu', 'systeme', null, [
                    'facture' => $facture->reference,
                    'jours_de_retard' => $joursDeRetard,
                ]);
                $suspendus++;
            } elseif ($abonnement->statut === 'actif') {
                $abonnement->forceFill(['statut' => 'en_retard'])->save();
                $this->tracer($abonnement, 'retard_constate', 'actif', 'en_retard', 'systeme', null, [
                    'facture' => $facture->reference,
                ]);
                $enRetard++;
            }
        }

        return ['en_retard' => $enRetard, 'suspendus' => $suspendus];
    }

    /**
     * Résilier n'est pas couper : le service va au bout de la période déjà
     * payée, et le renouvellement s'arrête.
     */
    public function resilier(Abonnement $abonnement, string $motif, ?int $userId = null): Abonnement
    {
        $avant = $abonnement->statut;

        $abonnement->forceFill([
            'renouvellement_auto' => false,
            'resilie_le' => now(),
            'resiliation_effective_le' => $abonnement->periode_fin ?: now()->toDateString(),
            'motif_resiliation' => $motif,
            'date_prochaine_facture' => null,
        ])->save();

        $this->tracer($abonnement, 'resiliation', $avant, $abonnement->statut, $userId ? 'agent' : 'systeme', $userId, [
            'motif' => $motif,
        ]);

        return $abonnement;
    }

    public function changerStatut(Abonnement $abonnement, string $statut, ?int $userId = null): Abonnement
    {
        $avant = $abonnement->statut;
        $abonnement->forceFill(['statut' => $statut])->save();

        $this->tracer($abonnement, 'changement_statut', $avant, $statut, $userId ? 'agent' : 'systeme', $userId);

        return $abonnement;
    }

    private function referenceFacture(): string
    {
        do {
            $reference = 'FA-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (Invoice::where('reference', $reference)->exists());

        return $reference;
    }

    private function tracer(
        Abonnement $abonnement,
        string $type,
        ?string $avant,
        ?string $apres,
        string $source,
        ?int $userId = null,
        array $donnees = [],
    ): void {
        EvenementAbonnement::create([
            'abonnement_id' => $abonnement->id,
            'type' => $type,
            'statut_avant' => $avant,
            'statut_apres' => $apres,
            'source' => $source,
            'user_id' => $userId,
            'donnees' => $donnees ?: null,
        ]);
    }
}
