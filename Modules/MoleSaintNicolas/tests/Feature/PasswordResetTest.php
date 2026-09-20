<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_login_page_links_to_the_forgot_password_form(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.password.request'), false);
    }

    public function test_admin_can_request_a_reset_link_and_reset_their_password(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['password' => 'ancien-mot-de-passe']);
        $admin->assignRole('admin');

        $this->post(route('admin.password.email'), ['email' => $admin->email])
            ->assertRedirect();

        $token = null;
        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;

            return true;
        });
        $this->assertNotNull($token);

        $this->get(route('admin.password.reset', ['token' => $token, 'email' => $admin->email]))
            ->assertOk()
            ->assertSee($admin->email);

        $this->post(route('admin.password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertRedirect(route('admin.login'));

        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $admin->fresh()->password));

        $this->post(route('admin.login'), [
            'email' => $admin->email,
            'password' => 'nouveau-mot-de-passe',
        ])->assertRedirect(route('admin.dashboard'));
    }

    public function test_reset_fails_with_an_invalid_token(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->post(route('admin.password.update'), [
            'token' => 'jeton-invalide',
            'email' => $admin->email,
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertSessionHasErrors('email');
    }

    public function test_reset_link_request_does_not_reveal_whether_the_email_exists(): void
    {
        $this->post(route('admin.password.email'), ['email' => 'inconnu@example.com'])
            ->assertRedirect()
            ->assertSessionHas('status');
    }
}
