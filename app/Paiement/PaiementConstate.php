<?php

namespace App\Paiement;

use App\Models\Paiement;

/**
 * Ce qu'une chose payable veut savoir du sort de son paiement.
 *
 * Un paiement devient « réussi » par quatre chemins : le retour du navigateur,
 * la notification de la passerelle, l'approbation d'un agent, l'encaissement en
 * espèces. Si chaque chemin mettait la commande à jour de son côté, il en
 * manquerait un — et une commande réglée resterait en attente, sans que
 * personne ne sache pourquoi.
 *
 * Le PaiementService prévient donc le payable une seule fois, au moment où le
 * statut bascule, quel que soit le chemin emprunté.
 */
interface PaiementConstate
{
    public function paiementReussi(Paiement $paiement): void;

    public function paiementRejete(Paiement $paiement): void;
}
