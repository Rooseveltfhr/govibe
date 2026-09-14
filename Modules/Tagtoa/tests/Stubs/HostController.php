<?php

namespace Modules\Tagtoa\Tests\Stubs;

/**
 * Doublure de `App\Http\Controllers\Controller` pour les tests du module.
 *
 * Dans le projet hôte, cette classe est littéralement vide :
 *
 *     abstract class Controller { }
 *
 * On reproduit donc l'original à l'identique. Le jour où l'hôte y ajoute
 * quelque chose, les tests le signaleront en échouant — ce qui est le
 * comportement souhaité.
 */
abstract class HostController
{
}
