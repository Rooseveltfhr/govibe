<?php

/*
 * Amorçage de la suite FEATURE du module TAGTOA (Laravel réel via Testbench).
 *
 * À ne pas confondre avec `tests/bootstrap.php` à la RACINE du dépôt, qui sert
 * la suite Unit — logique pure, sans Laravel. Les deux fichiers portent le même
 * nom parce qu'ils jouent le même rôle dans deux contextes différents ; la CI
 * appelle chacun depuis son propre répertoire.
 *
 * Les contrôleurs étendent `App\Http\Controllers\Controller`, une classe du
 * projet hôte que l'autoloader du module ne connaît pas — les tests ne
 * pouvaient donc pas instancier un seul contrôleur, alors qu'ils portent les
 * droits, la validation et le cloisonnement.
 *
 * On la remplace ici par ce qu'elle est réellement : une classe abstraite vide
 * (vérifié dans app/Http/Controllers/Controller.php du projet hôte). Rien n'est
 * simulé — c'est la même chose, simplement rendue accessible aux tests.
 */

require __DIR__.'/../vendor/autoload.php';

if (! class_exists(\App\Http\Controllers\Controller::class, false)) {
    class_alias(\Modules\Tagtoa\Tests\Stubs\HostController::class, \App\Http\Controllers\Controller::class);
}
