<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Registration;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function category(array $attributes = []): TicketCategory
    {
        return TicketCategory::create($attributes + [
            'name'     => 'Professionnel',
            'slug'     => 'professionnel',
            'audience' => 'professional',
            'price'    => 5000,
            'currency' => 'HTG',
            'active'   => true,
        ]);
    }

    public function test_public_pages_render(): void
    {
        $this->category();

        foreach (['/', '/inscription', '/a-propos', '/programme', '/contact'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_visitor_can_register_and_gets_a_ticket(): void
    {
        $category = $this->category();

        $response = $this->post('/inscription/'.$category->slug, [
            'first_name'     => 'Marie',
            'last_name'      => 'Joseph',
            'email'          => 'marie@example.com',
            'country'        => 'Haïti',
            'payment_method' => 'moncash',
        ]);

        $registration = Registration::first();
        $this->assertNotNull($registration);
        $this->assertSame(5000, (int) $registration->amount);
        $this->assertSame('pending', $registration->payment_status);
        $this->assertStringStartsWith('FINPO26-', $registration->number);

        $response->assertRedirect(route('ticket.show', $registration->qr_token));
        $this->get(route('ticket.show', $registration->qr_token))->assertOk()->assertSee($registration->number);
        $this->get(route('badge.show', $registration->qr_token))->assertOk();
    }

    public function test_coupon_reduces_server_side_price(): void
    {
        $category = $this->category();
        Coupon::create(['code' => 'PROMO50', 'type' => 'percent', 'value' => 50, 'active' => true]);

        $this->post('/inscription/'.$category->slug, [
            'first_name'     => 'Jean',
            'last_name'      => 'Pierre',
            'email'          => 'jean@example.com',
            'country'        => 'Haïti',
            'payment_method' => 'card',
            'coupon'         => 'promo50',
        ]);

        $this->assertSame(2500, (int) Registration::first()->amount);
        $this->assertSame(1, (int) Coupon::first()->used);
    }

    public function test_free_category_skips_payment(): void
    {
        $category = $this->category(['name' => 'Presse', 'slug' => 'presse', 'price' => 0, 'audience' => 'press']);

        $this->post('/inscription/'.$category->slug, [
            'first_name'     => 'Ana',
            'last_name'      => 'Media',
            'email'          => 'ana@example.com',
            'country'        => 'Haïti',
            'payment_method' => 'free',
        ]);

        $this->assertSame('free', Registration::first()->payment_status);
    }

    public function test_admin_area_requires_admin_role(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));

        $attendee = User::create(['name' => 'A', 'email' => 'a@x.ht', 'password' => bcrypt('secret'), 'role' => 'attendee']);
        $this->actingAs($attendee)->get('/admin')->assertForbidden();

        $admin = User::create(['name' => 'B', 'email' => 'b@x.ht', 'password' => bcrypt('secret'), 'role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_admin_can_checkin_a_ticket_once(): void
    {
        $category = $this->category();
        $admin = User::create(['name' => 'B', 'email' => 'b@x.ht', 'password' => bcrypt('secret'), 'role' => 'admin']);

        $registration = Registration::create([
            'number'             => Registration::nextNumber(),
            'qr_token'           => Registration::newQrToken(),
            'ticket_category_id' => $category->id,
            'first_name'         => 'Paul',
            'last_name'          => 'Henri',
            'email'              => 'paul@example.com',
            'country'            => 'Haïti',
            'audience'           => 'professional',
            'amount'             => 5000,
            'payment_status'     => 'paid',
        ]);

        $first = $this->actingAs($admin)->postJson('/admin/checkin/scan', ['code' => $registration->qr_token]);
        $first->assertOk()->assertJsonPath('status', 'ok');

        $second = $this->actingAs($admin)->postJson('/admin/checkin/scan', ['code' => $registration->qr_token]);
        $second->assertOk()->assertJsonPath('status', 'already');
    }
}
