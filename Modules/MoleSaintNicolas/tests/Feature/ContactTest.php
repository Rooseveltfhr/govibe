<?php

namespace Tests\Feature;

use App\Mail\NewContactMessageReceived;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_contact_page_is_visible(): void
    {
        $this->get(route('contact.show'))
            ->assertOk()
            ->assertSee('Contact');
    }

    public function test_visitor_can_send_a_message_and_admin_is_notified(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->post(route('contact.store'), [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
            'message' => 'Bonjour, une question sur les hébergements.',
        ]);

        $response->assertRedirect(route('contact.show'));
        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Jean Dupont',
            'email' => 'jean@example.com',
        ]);
        Mail::assertSent(NewContactMessageReceived::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_message_requires_name_email_and_message(): void
    {
        $this->post(route('contact.store'), [])
            ->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_guest_cannot_view_messages(): void
    {
        $this->get(route('admin.messages.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_mark_a_message_as_read_and_delete_it(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $message = ContactMessage::create([
            'name' => 'Marie',
            'email' => 'marie@example.com',
            'message' => 'Un message.',
        ]);

        $this->actingAs($admin)->put(route('admin.messages.markRead', $message))
            ->assertRedirect(route('admin.messages.index'));

        $this->assertNotNull($message->refresh()->read_at);

        $this->actingAs($admin)->delete(route('admin.messages.destroy', $message))
            ->assertRedirect(route('admin.messages.index'));

        $this->assertDatabaseMissing('contact_messages', ['id' => $message->id]);
    }
}
