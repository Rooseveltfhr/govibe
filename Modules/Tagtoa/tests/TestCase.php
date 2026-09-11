<?php

namespace Modules\Tagtoa\Tests;

use Modules\Tagtoa\Tests\Stubs\PassThroughMiddleware;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * TAGTOA — TestCase pour les tests Feature (DB réelle, framework Laravel complet).
 *
 * Utilise Orchestra Testbench : boot un hôte Laravel minimal ISOLÉ (SQLite en
 * mémoire), sans dépendre de Biztap (absent de ce dépôt). Charge uniquement les
 * migrations TAGTOA — elles sont autonomes (aucune clé étrangère vers une table
 * du cœur Biztap comme `users`/`vcards`/`tenants`), donc testables seules.
 */
abstract class TestCase extends BaseTestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
            // SQLite ignore les clés étrangères par défaut : sans cette ligne,
            // un test « la suppression n'emporte pas les données liées »
            // passerait alors que la production, elle, supprimerait en cascade.
            'foreign_key_constraints' => true,
        ]);
        // Clé fixe (déterministe) : Crypt/Hash exigent une clé d'app valide.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('t', 32)));

        // Charge la config du module comme le fait TagtoaServiceProvider en
        // production (mergeConfigFrom). Sans ça, config('tagtoa.*') serait vide
        // en test et certaines vérifications (passerelles, forfaits) passeraient
        // pour de mauvaises raisons.
        $app['config']->set('tagtoa', require __DIR__.'/../config/config.php');
    }

    /**
     * Middlewares du projet hôte, neutralisés pour les tests.
     *
     * `valid.user`, `role` et `multi_tenant` appartiennent au cœur hôte, absent
     * d'ici. Les remplacer par des passe-plats permet de charger les VRAIES
     * routes du module : les tests attrapent donc aussi un nom de route erroné,
     * qui produirait une erreur 500 en production.
     */
    protected function defineEnvironment($app): void
    {
        $router = $app['router'];
        foreach (['valid.user', 'role', 'multi_tenant'] as $alias) {
            $router->aliasMiddleware($alias, PassThroughMiddleware::class);
        }
    }

    protected function defineRoutes($router): void
    {
        require __DIR__.'/../routes/web.php';
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Tenant::id() garde le commerce courant en cache pour la durée d'une
        // requête. Sans ce vidage, le premier test fixerait le commerce de tous
        // les suivants et la suite passerait pour de mauvaises raisons.
        \Modules\Tagtoa\App\Support\Tenant::flush();
    }

    protected function tearDown(): void
    {
        \Modules\Tagtoa\App\Support\Tenant::flush();
        parent::tearDown();
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/migrations');
    }
}
