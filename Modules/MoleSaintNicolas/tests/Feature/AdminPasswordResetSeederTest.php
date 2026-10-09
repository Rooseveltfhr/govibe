<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminPasswordResetSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPasswordResetSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $sentinel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sentinel = storage_path('app/.admin-password-reset-applied');
        @unlink($this->sentinel);
    }

    protected function tearDown(): void
    {
        @unlink($this->sentinel);
        $this->unsetVar('MSN_ADMIN_PASSWORD_RESET');
        parent::tearDown();
    }

    /**
     * Un .env local définit déjà MSN_ADMIN_PASSWORD_RESET (vide, par défaut
     * — voir .env.example) : Dotenv l'a alors chargé dans $_ENV/$_SERVER au
     * démarrage, et env() lit ces tableaux avant getenv(), donc un simple
     * putenv() dans le test ne suffit pas à le faire changer d'avis.
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

    public function test_it_resets_the_admin_password_when_the_env_variable_is_set(): void
    {
        $admin = User::factory()->create(['email' => 'admin@molesaintnicolas.com', 'password' => 'ancien-mot-de-passe']);

        $this->setVar('MSN_ADMIN_PASSWORD_RESET', 'nouveau-mot-de-passe-123');
        $this->seed(AdminPasswordResetSeeder::class);
        $this->unsetVar('MSN_ADMIN_PASSWORD_RESET');

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-123', $admin->fresh()->password));
        $this->assertFileExists($this->sentinel);
    }

    public function test_it_never_applies_twice_even_if_the_variable_stays_set(): void
    {
        $admin = User::factory()->create(['email' => 'admin@molesaintnicolas.com', 'password' => 'ancien-mot-de-passe']);
        file_put_contents($this->sentinel, 'deja-applique');

        $this->setVar('MSN_ADMIN_PASSWORD_RESET', 'une-autre-valeur-123');
        $this->seed(AdminPasswordResetSeeder::class);
        $this->unsetVar('MSN_ADMIN_PASSWORD_RESET');

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
    }

    public function test_it_does_nothing_when_the_variable_is_empty(): void
    {
        $admin = User::factory()->create(['email' => 'admin@molesaintnicolas.com', 'password' => 'ancien-mot-de-passe']);

        $this->seed(AdminPasswordResetSeeder::class);

        $this->assertTrue(Hash::check('ancien-mot-de-passe', $admin->fresh()->password));
        $this->assertFileDoesNotExist($this->sentinel);
    }
}
