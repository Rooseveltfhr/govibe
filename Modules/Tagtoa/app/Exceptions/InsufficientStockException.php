<?php

namespace Modules\Tagtoa\App\Exceptions;

/**
 * TAGTOA INVENTORY — un mouvement de stock ferait passer un article suivi
 * sous zéro.
 *
 * Le message reste EXACTEMENT `out_of_stock` : Menu\PublicController et
 * Store\PublicController interceptent déjà cette chaîne précise (posée par
 * leur propre vérification, plus ancienne et plus faible car lue hors
 * verrou) pour afficher un message au client. La garde posée dans
 * StockLedger::apply() — sous verrou de ligne — ferme la vraie faille :
 * deux commandes simultanées qui liraient toutes les deux « assez de
 * stock » avant que l'une des deux n'écrive.
 *
 * C'est une OrderRuleException, pas un \RuntimeException nu : ces deux
 * contrôleurs n'attrapent plus que les règles métier (une panne de base ne
 * doit pas se faire passer pour « panier vide »). « Plus de stock » EN EST
 * une, et la plus ordinaire : deux clients qui prennent la dernière unité en
 * même temps — le second doit lire « rupture de stock » (422), pas recevoir
 * une erreur serveur.
 */
class InsufficientStockException extends OrderRuleException
{
    public function __construct(
        public readonly ?string $productName = null,
        public readonly ?float $available = null,
    ) {
        parent::__construct('out_of_stock');
    }
}
