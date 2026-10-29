<?php

namespace Modules\Tagtoa\App\Exceptions;

/**
 * TAGTOA EVENT — règle métier refusée à la billetterie (billet non
 * disponible, stock insuffisant, aucune sélection). Porte un message DÉJÀ
 * traduit, destiné à l'acheteur ou à l'organisateur.
 *
 * Même raison d'être que LoyaltyRuleException : QueryException hérite de
 * PDOException, qui hérite de RuntimeException. Les écrans attrapaient
 * \RuntimeException pour afficher « Stock insuffisant », donc attrapaient
 * aussi les pannes de base — et en affichaient le message, requête SQL et
 * valeurs comprises. Ici la page est PUBLIQUE, et les écritures (commande,
 * billets, compteur de ventes) se font DANS le try : c'est donc l'achat de
 * l'acheteur qui s'affichait, et un achat réellement planté passait pour un
 * mauvais choix de quantité.
 */
class TicketRuleException extends \RuntimeException
{
}
