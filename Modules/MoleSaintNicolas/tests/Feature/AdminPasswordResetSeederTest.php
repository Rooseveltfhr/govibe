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
        parent::tearDown();
    }

    public function test_it_resets_the_admin_password_when_the_env_variable_is_set(): void
    {
        $admin = User::factory()->create(['email' => 'admin@molesaintnicolas.com', 'password' => 'ancien-mot-de-passe']);

        putenv('MSN_ADMIN_PASSWORD_RESET=nouveau-mot-de-passe-123');
        $this->seed(AdminPasswordResetSeeder::class);
        putenv('MSN_ADMIN_PASSWORD_RESET');

        $this->assertTrue(Hash::check('nouveau-mot-de-passe-123', $admin->fresh()->password));
        $this->assertFileExists($this->sentinel);
    }

    public function test_it_never_applies_twice_even_if_the_variable_stays_set(): void
    {
        $admin = User::factory()->create(['email' => 'admin@molesaintnicolas.com', 'password' => 'ancien-mot-de-passe']);
        file_put_contents($this->sentinel, 'deja-applique');

        putenv('MSN_ADMIN_PASSWORD_RESET=une-autre-valeur-123');
        $this->seed(AdminPasswordResetSeeder::class);
        putenv('MSN_ADMIN_PASSWORD_RESET');

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
