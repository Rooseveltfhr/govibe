<?php

namespace App\Services;

use App\Models\PasserellePaiement;
use App\Models\TauxChange;

/**
 * Ce que le client paiera par une passerelle donnée, et dans quelle devise.
 *
 * Règle posée par Roosevelt : le total s'affiche dans la devise de la
 * passerelle choisie. Un plan facturé en dollars réglé par MonCash s'annonce
 * en gourdes ; le même plan réglé par PayPal s'annonce en dollars.
 *
 * Un seul endroit calcule cette conversion : le formulaire l'utilise pour
 * afficher, le contrôleur la recalcule pour enregistrer. Deux calculs séparés
 * finiraient par diverger, et le client paierait autre chose que ce qu'il a vu.
 */
class TarifPasserelle
{
    /**
     * Rend null quand la conversion nécessaire n'a aucun taux réglé : la
     * passerelle n'est alors pas proposable pour ce montant.
     *
     * @return array{devise: string, taux: float|null, montant: float}|null
     */
    public function pour(PasserellePaiement $passerelle, float $montant, string $devise): ?array
    {
        $devisePaiement = $this->devisePour($passerelle, $devise);

        if ($devisePaiement === null) {
            return null;
        }

        if ($devisePaiement === $devise) {
            return ['devise' => $devise, 'taux' => null, 'montant' => round($montant, 2)];
        }

        $taux = TauxChange::actuel($devise, $devisePaiement);

        // Ne devrait pas arriver — devisePour() a déjà vérifié — mais un taux
        // retiré entre les deux appels ne doit pas produire un montant nul.
        if ($taux === null) {
            return null;
        }

        return [
            'devise' => $devisePaiement,
            'taux' => $taux,
            'montant' => round($montant * $taux, 2),
        ];
    }

    /**
     * Devise dans laquelle cette passerelle encaisse le montant demandé.
     *
     * La devise du plan est toujours préférée : ne pas convertir vaut mieux
     * que convertir, il n'y a alors aucun écart possible.
     */
    private function devisePour(PasserellePaiement $passerelle, string $devise): ?string
    {
        $supportees = array_values(array_filter($passerelle->devises_supportees ?? []));

        // Aucune devise déclarée : rien n'autorise à convertir. Le montant
        // reste dans la devise du plan, et l'ERP signale la fiche incomplète.
        if ($supportees === []) {
            return $devise;
        }

        if (in_array($devise, $supportees, true)) {
            return $devise;
        }

        foreach ($supportees as $candidate) {
            if (TauxChange::actuel($devise, $candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Les passerelles publiques capables d'encaisser ce montant, chacune avec
     * le total que le client verra.
     *
     * Une passerelle API sans clés, ou une passerelle dont la devise exige un
     * taux qui n'est pas réglé, est écartée : la proposer mènerait le client
     * jusqu'au paiement pour y échouer.
     *
     * @return array<int, array{passerelle: PasserellePaiement, devise: string, taux: float|null, montant: float}>
     */
    public function passerellesPour(float $montant, string $devise): array
    {
        $resultat = [];

        foreach (PasserellePaiement::public()->get() as $passerelle) {
            if ($passerelle->est_incomplete) {
                continue;
            }

            $tarif = $this->pour($passerelle, $montant, $devise);

            if ($tarif === null) {
                continue;
            }

            $resultat[] = ['passerelle' => $passerelle] + $tarif;
        }

        return $resultat;
    }
}
