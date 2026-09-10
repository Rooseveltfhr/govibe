<?php

namespace App\Services;

use App\Models\EvenementPaiement;
use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Models\TauxChange;
use App\Paiement\RegistrePilotes;
use App\Paiement\ResultatInitiation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Point unique par lequel passe tout encaissement GOVIBE.
 *
 * Les quatre règles du dossier d'architecture sont tenues ici, et nulle part
 * ailleurs — c'est ce qui permet de les vérifier une fois pour toutes les
 * unités d'affaires.
 */
class PaiementService
{
    public function __construct(private RegistrePilotes $pilotes) {}

    /**
     * Ouvre un paiement. Ne crédite rien : à ce stade le client n'a pas payé.
     */
    public function ouvrir(
        PasserellePaiement $passerelle,
        float $montant,
        string $devise,
        ?Model $payable = null,
        ?int $clientId = null,
        ?string $ip = null,
    ): Paiement {
        $paiement = new Paiement([
            'reference' => Paiement::genererReference(),
            'client_id' => $clientId,
            'passerelle_id' => $passerelle->id,
            'mode' => $passerelle->mode,
            'pilote' => $passerelle->pilote,
            'passerelle_nom' => $passerelle->nom,
            'montant' => $montant,
            'devise' => $devise,
            'statut' => 'initie',
            'cle_idempotence' => (string) Str::uuid(),
            'ip' => $ip,
        ]);

        if ($payable) {
            $paiement->payable()->associate($payable);
        }

        // Équivalent gelé maintenant : le client règle ce qui était écrit,
        // même si le taux bouge entre l'affichage et le paiement.
        $this->figerConversion($paiement);

        $paiement->save();
        $this->tracer($paiement, 'ouverture', null, 'initie', 'systeme');

        return $paiement;
    }

    /** Demande à la passerelle où envoyer le client. */
    public function initier(Paiement $paiement): ResultatInitiation
    {
        $passerelle = $paiement->passerelle;
        $pilote = $this->pilotes->trouver($paiement->pilote);

        if (! $passerelle || ! $pilote) {
            return ResultatInitiation::echec('Ce moyen de paiement n\'est pas configuré.');
        }

        $resultat = $pilote->initier($paiement, $passerelle);

        $paiement->forceFill([
            'statut' => $resultat->reussi ? 'en_attente' : 'echoue',
            'jeton_externe' => $resultat->jeton,
            'echec_motif' => $resultat->erreur,
            'charge_utile' => $resultat->charge ?: $paiement->charge_utile,
        ])->save();

        $this->tracer(
            $paiement,
            $resultat->reussi ? 'initiation' : 'initiation_echouee',
            'initie',
            $paiement->statut,
            'passerelle'
        );

        return $resultat;
    }

    /**
     * Interroge la passerelle et applique son verdict.
     *
     * Jamais appelé avec un montant venu du client : la somme qui fait foi est
     * celle que la passerelle rapporte, comparée à celle qui a été demandée.
     */
    public function verifier(Paiement $paiement): Paiement
    {
        // Un paiement figé ne se rejoue pas : c'est ce qui empêche un retour
        // rejoué de créditer deux fois la même somme.
        if ($paiement->estFige()) {
            return $paiement;
        }

        $passerelle = $paiement->passerelle;
        $pilote = $this->pilotes->trouver($paiement->pilote);

        if (! $passerelle || ! $pilote) {
            return $paiement;
        }

        $resultat = $pilote->verifier($paiement, $passerelle);
        $avant = $paiement->statut;

        if ($resultat->statut !== 'reussi') {
            $paiement->forceFill([
                'statut' => $resultat->statut === 'echoue' ? 'echoue' : 'en_attente',
                'echec_motif' => $resultat->erreur,
                'charge_utile' => $resultat->charge ?: $paiement->charge_utile,
            ])->save();

            $this->tracer($paiement, 'verification', $avant, $paiement->statut, 'passerelle');

            return $paiement;
        }

        // Montant discordant : la passerelle dit « payé », mais pas la somme
        // attendue. On ne crédite pas — un agent tranche.
        $tolerance = (float) config('paiement.tolerance_montant', 1.0);

        if ($resultat->montant !== null && abs($resultat->montant - (float) $paiement->montant) > $tolerance) {
            $paiement->forceFill([
                'statut' => 'verification',
                'echec_motif' => sprintf(
                    'Montant encaissé (%s) différent du montant demandé (%s).',
                    $resultat->montant,
                    $paiement->montant
                ),
                'reference_externe' => $resultat->referenceExterne,
                'charge_utile' => $resultat->charge,
            ])->save();

            $this->tracer($paiement, 'montant_discordant', $avant, 'verification', 'passerelle');

            return $paiement;
        }

        $paiement->forceFill([
            'statut' => 'reussi',
            'reference_externe' => $resultat->referenceExterne,
            'charge_utile' => $resultat->charge,
            'echec_motif' => null,
            'paye_le' => now(),
        ])->save();

        $this->tracer($paiement, 'paiement_confirme', $avant, 'reussi', 'passerelle');

        return $paiement;
    }

    /**
     * Validation d'un règlement hors ligne par un agent habilité.
     *
     * Le seul autre chemin vers « réussi ». Un bouton « j'ai payé » côté client
     * n'en est pas un.
     */
    public function approuverManuel(Paiement $paiement, int $userId, ?string $reference = null): Paiement
    {
        return DB::transaction(function () use ($paiement, $userId, $reference) {
            $paiement = Paiement::lockForUpdate()->find($paiement->id);

            if ($paiement->estFige()) {
                return $paiement;
            }

            $avant = $paiement->statut;

            $paiement->forceFill([
                'statut' => 'reussi',
                'reference_externe' => $reference ?: $paiement->reference_externe,
                'approuve_par' => $userId,
                'approuve_le' => now(),
                'paye_le' => now(),
                'echec_motif' => null,
            ])->save();

            $this->tracer($paiement, 'approbation_manuelle', $avant, 'reussi', 'agent', $userId);

            return $paiement;
        });
    }

    public function rejeter(Paiement $paiement, int $userId, string $motif): Paiement
    {
        if ($paiement->estFige()) {
            return $paiement;
        }

        $avant = $paiement->statut;

        $paiement->forceFill([
            'statut' => 'echoue',
            'echec_motif' => $motif,
            'approuve_par' => $userId,
            'approuve_le' => now(),
        ])->save();

        $this->tracer($paiement, 'rejet', $avant, 'echoue', 'agent', $userId);

        return $paiement;
    }

    /**
     * Encaissement en espèces, en caisse.
     *
     * Réservé à un employé identifié : un paiement en espèces déclaré depuis
     * un navigateur public ne se vérifie contre rien.
     */
    public function encaisserCash(
        PasserellePaiement $passerelle,
        float $montant,
        string $devise,
        int $userId,
        ?Model $payable = null,
        ?int $clientId = null,
    ): Paiement {
        $paiement = $this->ouvrir($passerelle, $montant, $devise, $payable, $clientId);
        $paiement->forceFill(['mode' => 'cash'])->save();

        return $this->approuverManuel($paiement, $userId);
    }

    private function figerConversion(Paiement $paiement): void
    {
        $autre = $paiement->devise === 'HTG' ? 'USD' : 'HTG';
        $taux = TauxChange::actuel($paiement->devise, $autre);

        // Sans taux réglé par le super admin, aucune conversion n'est affichée.
        if ($taux === null) {
            return;
        }

        $paiement->montant_converti = round((float) $paiement->montant * $taux, 2);
        $paiement->devise_convertie = $autre;
        $paiement->taux_change = $taux;
    }

    private function tracer(
        Paiement $paiement,
        string $type,
        ?string $avant,
        ?string $apres,
        string $source,
        ?int $userId = null,
    ): void {
        EvenementPaiement::create([
            'paiement_id' => $paiement->id,
            'type' => $type,
            'statut_avant' => $avant,
            'statut_apres' => $apres,
            'source' => $source,
            'user_id' => $userId,
            'donnees' => ['reference' => $paiement->reference],
        ]);
    }
}
