<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_profile_page(): void
    {
        $this->get(route('admin.profile.edit'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_change_their_own_password(): void
    {
        $admin = User::factory()->create(['password' => 'old-password']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'current_password' => 'old-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('brand-new-password', $admin->refresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $admin = User::factory()->create(['password' => 'old-password']);
        $admin->assignRole('admin');

        $this->actingAs($admin)->put(route('admin.profile.update'), [
            'current_password' => 'wrong-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertSessionHasErrors('current_password');
    }
}
