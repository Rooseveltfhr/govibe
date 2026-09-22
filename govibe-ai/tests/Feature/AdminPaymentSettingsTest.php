<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Core\Support\Settings;

uses(RefreshDatabase::class);

function paymentAdmin(): User
{
    return User::firstOrCreate(
        ['email' => 'admin@govibe.ht'],
        ['name' => 'Roosevelt', 'password' => 'yon-modpas-long-anpil', 'is_admin' => true],
    );
}

it('keeps the payment page closed to visitors', function () {
    $this->get(route('admin.payments'))->assertRedirect(route('admin.login'));
});

it('shows the payment page to an administrator', function () {
    $this->actingAs(paymentAdmin())
        ->get(route('admin.payments'))
        ->assertOk()
        ->assertSee(__('Paiement'))
        ->assertSee('MonCash')
        ->assertSee(__('Virement bancaire'));
});

it('saves the bank details and the moncash client id in clear, non-secret fields', function () {
    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'moncash_enabled' => '1',
        'moncash_client_id' => 'client-abc',
        'moncash_mode' => 'live',
        'bank_htg' => 'Sogebank — 100-000-000 — LOUVIA SA',
        'bank_usd' => 'Unibank — 200-000-000 — LOUVIA SA',
        'payment_instructions' => 'Acompte de 50% pour démarrer.',
    ])->assertRedirect(route('admin.payments'));

    $settings = app(Settings::class);

    expect($settings->get('moncash_enabled'))->toBe('1')
        ->and($settings->get('moncash_client_id'))->toBe('client-abc')
        ->and($settings->get('moncash_mode'))->toBe('live')
        ->and($settings->get('bank_htg'))->toBe('Sogebank — 100-000-000 — LOUVIA SA')
        ->and($settings->get('bank_usd'))->toBe('Unibank — 200-000-000 — LOUVIA SA')
        ->and($settings->get('payment_instructions'))->toBe('Acompte de 50% pour démarrer.');
});

// Yon sekrè chiffre nan baz la, menm jan ak yon kle founisè — pa yon lòt
// règ pou paj sa a.
it('stores the moncash client secret encrypted, never in clear text', function () {
    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'moncash_client_secret' => 'sekrè-moncash',
    ])->assertRedirect();

    $raw = (string) DB::table('platform_settings')->where('key', 'moncash_client_secret')->value('value');

    expect($raw)->not->toBe('sekrè-moncash')
        ->and($raw)->not->toContain('sekrè')
        ->and(app(Settings::class)->get('moncash_client_secret'))->toBe('sekrè-moncash');
});

it('never shows the moncash secret back on the page', function () {
    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'moncash_client_secret' => 'sekrè-moncash',
    ]);

    $this->actingAs(paymentAdmin())
        ->get(route('admin.payments'))
        ->assertOk()
        ->assertDontSee('sekrè-moncash')
        ->assertSee(__('Posée'));
});

// Yon chan vid pa dwe efase yon sekrè ki deja la: yon moun ki mete ajou
// sèlman kont labank pa dwe pèdi kle MonCash la san l pa vle.
it('keeps an existing moncash secret when the field is left blank', function () {
    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'moncash_client_secret' => 'sekrè-moncash',
    ]);

    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'bank_htg' => 'Sogebank — 100-000-000',
    ]);

    expect(app(Settings::class)->get('moncash_client_secret'))->toBe('sekrè-moncash');
});

it('refuses a moncash mode it does not know', function () {
    $this->actingAs(paymentAdmin())
        ->post(route('admin.payments.update'), ['moncash_mode' => 'production'])
        ->assertSessionHasErrors('moncash_mode');
});

// ── Tablo debò a reflete si peman konfigire ──────────────────────────────

it('points the dashboard setup checklist to the payment page until one is configured', function () {
    $this->actingAs(paymentAdmin())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(__('Configurer le paiement'))
        ->assertSee(route('admin.payments'), false)
        // Twa etap: kle IA, kle vwa, peman — okenn nan yo konfigire nan yon
        // baz done tès ki fenk kreye.
        ->assertSee(__(':n étape(s) avant que la plateforme soit prête pour un vrai client.', ['n' => 3]));
});

// Chèklis la KENBE chak etap vizib (li make yo « Fait » olye li retire yo),
// kidonk sa ki chanje se konte a — pa disparisyon tèks etikèt la.
it('marks the payment step done on the dashboard once a bank account is set', function () {
    $this->actingAs(paymentAdmin())->post(route('admin.payments.update'), [
        'bank_htg' => 'Sogebank — 100-000-000',
    ])->assertRedirect(route('admin.payments'));

    $response = $this->actingAs(paymentAdmin())->get(route('admin.dashboard'))->assertOk();

    $response->assertSee(__(':n étape(s) avant que la plateforme soit prête pour un vrai client.', ['n' => 2]));
});
