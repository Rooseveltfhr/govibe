<?php

namespace Tests\Feature;

use App\Mail\NewBookingReceived;
use App\Models\Etablissements\Booking;
use App\Models\Etablissements\Establishment;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EstablishmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_hotels_index_lists_only_hotels(): void
    {
        Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);
        Establishment::create(['type' => 'restaurant', 'name' => 'Chez Ti Marie']);

        $response = $this->get('/hotels');

        $response->assertOk()->assertSee('Boukan Guinguette')->assertDontSee('Chez Ti Marie');
    }

    public function test_restaurants_index_lists_restaurants_and_bars(): void
    {
        Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);
        Establishment::create(['type' => 'restaurant', 'name' => 'Chez Ti Marie']);
        Establishment::create(['type' => 'bar', 'name' => 'Le Ponton']);

        $response = $this->get('/restaurants');

        $response->assertOk()
            ->assertSee('Chez Ti Marie')
            ->assertSee('Le Ponton')
            ->assertDontSee('Boukan Guinguette');
    }

    public function test_establishment_show_page_renders(): void
    {
        $hotel = Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);

        $this->get(route('hotels.show', $hotel->slug))
            ->assertOk()
            ->assertSee('Boukan Guinguette')
            ->assertSee('[Information à compléter]');
    }

    public function test_visitor_can_submit_a_booking_request_and_admin_is_notified(): void
    {
        Mail::fake();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $hotel = Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);

        $response = $this->post(route('bookings.store', $hotel), [
            'guest_name' => 'Jean Dupont',
            'guest_phone' => '+509 1234 5678',
            'starts_on' => now()->addDays(3)->toDateString(),
            'ends_on' => now()->addDays(5)->toDateString(),
            'party_size' => 2,
        ]);

        $response->assertRedirect(route('hotels.show', $hotel->slug));
        $this->assertDatabaseHas('bookings', [
            'establishment_id' => $hotel->id,
            'guest_name' => 'Jean Dupont',
            'status' => 'pending',
        ]);
        Mail::assertSent(NewBookingReceived::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_booking_request_requires_a_future_date(): void
    {
        $hotel = Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);

        $response = $this->post(route('bookings.store', $hotel), [
            'guest_name' => 'Jean Dupont',
            'guest_phone' => '+509 1234 5678',
            'starts_on' => now()->subDay()->toDateString(),
        ]);

        $response->assertSessionHasErrors('starts_on');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_guest_cannot_manage_establishments(): void
    {
        $this->get(route('admin.etablissements.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_and_delete_an_establishment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post(route('admin.etablissements.store'), [
            'type' => 'restaurant',
            'name' => 'Chez Ti Marie',
            'content_status' => 'needs_review',
        ]);

        $response->assertRedirect(route('admin.etablissements.index'));
        $created = Establishment::where('name', 'Chez Ti Marie')->firstOrFail();
        $this->assertSame('chez-ti-marie', $created->slug);

        $this->actingAs($admin)
            ->delete(route('admin.etablissements.destroy', $created))
            ->assertRedirect(route('admin.etablissements.index'));

        $this->assertDatabaseMissing('establishments', ['id' => $created->id]);
    }

    public function test_admin_can_confirm_a_reservation(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $hotel = Establishment::create(['type' => 'hotel', 'name' => 'Boukan Guinguette']);
        $booking = Booking::create([
            'establishment_id' => $hotel->id,
            'guest_name' => 'Jean Dupont',
            'guest_phone' => '+509 1234 5678',
            'starts_on' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->put(route('admin.reservations.update', $booking), ['status' => 'confirmed'])
            ->assertRedirect(route('admin.reservations.index'));

        $this->assertSame('confirmed', $booking->fresh()->status);
    }
}
