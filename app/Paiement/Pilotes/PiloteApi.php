<?php

namespace App\Paiement\Pilotes;

use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Paiement\ResultatInitiation;
use App\Paiement\ResultatVerification;

/**
 * Contrat que remplit chaque passerelle automatique.
 *
 * C'est la frontière qui rend GOVIBEPAY, Stripe ou PayPal interchangeables :
 * brancher une passerelle de plus, c'est écrire une classe, pas retoucher le
 * module de paiement.
 */
interface PiloteApi
{
    /** Clé stockée dans passerelles_paiement.pilote. */
    public static function cle(): string;

    /** Devises que cette passerelle sait encaisser. */
    public function devises(): array;

    /** Champs de configuration attendus, pour le formulaire du super admin. */
    public function champsRequis(): array;

    /** Ouvre le paiement chez la passerelle et rend où envoyer le client. */
    public function initier(Paiement $paiement, PasserellePaiement $passerelle): ResultatInitiation;

    /**
     * Demande à la passerelle si le paiement a réellement eu lieu.
     *
     * Seule source de vérité : le retour du navigateur ne prouve rien, il se
     * falsifie en modifiant une URL.
     */
    public function verifier(Paiement $paiement, PasserellePaiement $passerelle): ResultatVerification;
}
