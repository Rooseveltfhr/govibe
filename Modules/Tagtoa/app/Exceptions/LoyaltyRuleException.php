<?php

namespace Modules\Tagtoa\App\Exceptions;

/**
 * TAGTOA LOYALTY — règle métier refusée (carte inactive, solde ou points
 * insuffisants). Porte un message DÉJÀ traduit, destiné au marchand.
 *
 * Pourquoi un type à part plutôt que \RuntimeException : en PHP,
 * QueryException hérite de PDOException, qui hérite de RuntimeException.
 * Un écran qui attrapait \RuntimeException pour afficher « Solde
 * insuffisant » attrapait donc AUSSI n'importe quelle erreur de base de
 * données — et en affichait le message, c'est-à-dire la requête SQL
 * complète avec ses valeurs, dans le bandeau d'erreur du marchand. Pire :
 * une écriture qui échoue vraiment passait pour une saisie invalide, et
 * l'argent semblait refusé pour la mauvaise raison.
 */
class LoyaltyRuleException extends \RuntimeException
{
}
