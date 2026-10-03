<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Recharge config/session.php (le fichier a aussi besoin de storage_path(),
 * donc d'une application démarrée — d'où Tests\TestCase et non un TestCase
 * PHPUnit nu) pour vérifier que le cookie "Secure" suit APP_URL et non
 * APP_ENV : un site "production" pas encore servi en HTTPS ne doit pas
 * recevoir un cookie que le navigateur refuserait de garder, sous peine de
 * casser toutes les sessions (419 "Page expired" sur chaque formulaire,
 * connexion incluse).
 */
class SessionConfigSecureCookieTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->unsetVar('APP_URL');
        $this->unsetVar('SESSION_SECURE_COOKIE');
        parent::tearDown();
    }

    /**
     * .env a déjà peuplé $_ENV/$_SERVER au démarrage des tests : un simple
     * putenv() ne suffit pas à changer ce que env() voit ensuite, il faut
     * écraser les trois pour que la ré-évaluation du fichier de config lise
     * la nouvelle valeur.
     */
    private function setVar(string $key, string $value): void
    {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function unsetVar(string $key): void
    {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    private function secure(): bool
    {
        $config = require __DIR__.'/../../config/session.php';

        return $config['secure'];
    }

    public function test_cookie_is_not_secure_when_the_site_is_served_over_plain_http(): void
    {
        $this->setVar('APP_URL', 'http://molesaintnicolas.com');

        $this->assertFalse($this->secure());
    }

    public function test_cookie_is_secure_when_the_site_is_served_over_https(): void
    {
        $this->setVar('APP_URL', 'https://molesaintnicolas.com');

        $this->assertTrue($this->secure());
    }

    public function test_explicit_session_secure_cookie_variable_still_wins(): void
    {
        $this->setVar('APP_URL', 'http://molesaintnicolas.com');
        $this->setVar('SESSION_SECURE_COOKIE', 'true');

        $this->assertTrue($this->secure());
    }
}
