<?php

namespace Modules\Tagtoa\App\Exceptions;

/**
 * TAGTOA — règle métier refusée à la prise de commande (MENU, STORE,
 * BOOKING). Porte un CODE court ('out_of_stock', 'closed', 'invalid_slot'…)
 * que l'écran traduit, jamais un message déjà rédigé.
 *
 * Pourquoi un type à part : QueryException hérite de PDOException, qui
 * hérite de RuntimeException. Les trois écrans publics attrapaient
 * \RuntimeException et faisaient passer le message dans un `match` à codes
 * connus — donc aucune fuite SQL, mais une panne de base tombait dans la
 * branche par défaut et s'affichait « Votre panier est vide. »
 *
 * Le client lisait donc que SON panier avait un problème, alors que
 * l'écriture venait d'échouer côté serveur. Il recommence, relit la même
 * phrase, et s'en va — pendant qu'aucune alerte ne part, puisque la
 * réponse était un 422 (erreur du client) et non un 500.
 */
class OrderRuleException extends \RuntimeException
{
    /** 'rate_changed' : le total que le serveur calcule maintenant. */
    public ?float $newTotal = null;
}
