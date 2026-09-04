<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_homepage_is_accessible(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_guest_is_redirected_from_dashboard_to_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_in_and_reach_dashboard(): void
    {
        $admin = User::factory()->create(['password' => 'correct-password']);
        $admin->assignRole('admin');

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);

        $this->get('/admin')->assertOk()->assertSee($admin->name);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $admin = User::factory()->create(['password' => 'correct-password']);
        $admin->assignRole('admin');

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_without_admin_role_cannot_log_into_admin(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $response = $this->post('/admin/login', [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
