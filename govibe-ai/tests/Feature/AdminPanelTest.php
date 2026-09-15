<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Agents\Models\AgentOrder;
use Modules\Agents\Models\AgentPayment;
use Modules\AIProvider\Models\AiProviderRecord;
use Modules\AIProvider\Registry\CredentialStore;
use Modules\Core\Models\PlatformSetting;
use Modules\Core\Support\Settings;

uses(RefreshDatabase::class);

// Idanpotan: yon tès ki rele l de fwa dwe jwenn menm kont lan, pa yon
// vyolasyon kontrent sou imèl la.
function admin(): User
{
    return User::firstOrCreate(
        ['email' => 'admin@govibe.ht'],
        ['name' => 'Roosevelt', 'password' => 'yon-modpas-long-anpil', 'is_admin' => true],
    );
}

function plainUser(): User
{
    return User::firstOrCreate(
        ['email' => 'kliyan@example.com'],
        ['name' => 'Kliyan', 'password' => 'yon-modpas-long-anpil', 'is_admin' => false],
    );
}

function anOrder(array $overrides = []): AgentOrder
{
    return AgentOrder::create(array_merge([
        'reference' => AgentOrder::newReference(),
        'sector' => 'restaurant',
        'business_name' => 'Chita & Manje',
        'whatsapp' => '+509 3398 8754',
        'mode' => 'expert',
        'channels' => ['whatsapp'],
        'status' => 'nouvo',
    ], $overrides));
}

// ── Pòt la ───────────────────────────────────────────────────────────────

// Sa a se tès ki konte plis pase tout lòt yo: panèl la montre non kliyan,
// nimewo WhatsApp ak peman. Li PA dwe louvri.
it('keeps every admin page closed to visitors', function () {
    $order = anOrder();

    foreach ([
        route('admin.dashboard'),
        route('admin.orders.index'),
        route('admin.orders.show', $order),
        route('admin.settings'),
    ] as $url) {
        $this->get($url)->assertRedirect(route('admin.login'));
    }
});

// Yon kont ki konekte men ki pa administratè pa dwe wè kòmand yo non plis.
it('keeps it closed to a signed-in account that is not an admin', function () {
    $this->actingAs(plainUser())
        ->get(route('admin.orders.index'))
        ->assertRedirect(route('admin.login'));
});

it('lets an administrator in', function () {
    $this->actingAs(admin())->get(route('admin.dashboard'))->assertOk()->assertSee(__('Tableau de bord'));
});

it('signs an administrator in with the form', function () {
    $user = admin();
    $user->update(['password' => Hash::make('yon-modpas-long-anpil')]);

    $this->post(route('admin.login.attempt'), [
        'email' => 'admin@govibe.ht',
        'password' => 'yon-modpas-long-anpil',
    ])->assertRedirect(route('admin.dashboard'));

    expect(auth()->id())->toBe($user->id);
});

// Yon moun ki pa administratè pa dwe ka konekte nan panèl la, menm ak yon
// bon modpas: sinon nenpòt kont vin yon pòt.
it('refuses a correct password from a non-admin account', function () {
    $user = plainUser();
    $user->update(['password' => Hash::make('yon-modpas-long-anpil')]);

    $this->post(route('admin.login.attempt'), [
        'email' => $user->email,
        'password' => 'yon-modpas-long-anpil',
    ])->assertSessionHasErrors('email');

    expect(auth()->check())->toBeFalse();
});

// Menm mesaj pou yon imèl ki pa egziste ak pou yon move modpas: sinon fòm
// nan di ki imèl ki gen yon kont.
it('gives the same message whether the account exists or not', function () {
    admin()->update(['password' => Hash::make('yon-modpas-long-anpil')]);

    $wrongPassword = $this->post(route('admin.login.attempt'), [
        'email' => 'admin@govibe.ht', 'password' => 'move-modpas-la',
    ])->assertSessionHasErrors('email');

    $noAccount = $this->post(route('admin.login.attempt'), [
        'email' => 'pa-egziste@example.com', 'password' => 'move-modpas-la',
    ])->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toBe(__('Identifiants incorrects.'));
});

it('locks the form after repeated attempts', function () {
    RateLimiter::clear('admin@govibe.ht|127.0.0.1');
    admin();

    foreach (range(1, 5) as $_) {
        $this->post(route('admin.login.attempt'), ['email' => 'admin@govibe.ht', 'password' => 'move']);
    }

    $this->post(route('admin.login.attempt'), ['email' => 'admin@govibe.ht', 'password' => 'move'])
        ->assertSessionHasErrors('email');

    // Konpare ak mesaj tradui a, pa ak yon mo franse: tès yo kouri nan yon
    // lòt lang epi fraz la deja tradui.
    expect(session('errors')->first('email'))
        ->toStartWith(mb_substr(__('Trop de tentatives. Réessayez dans :seconds secondes.'), 0, 12));
});

// ── Kòmand yo ────────────────────────────────────────────────────────────

it('lists the orders, and finds one by reference or WhatsApp', function () {
    $order = anOrder();
    anOrder(['business_name' => 'Lòt Biznis', 'whatsapp' => '+509 1111 1111']);

    $this->actingAs(admin())
        ->get(route('admin.orders.index'))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('Lòt Biznis');

    $this->actingAs(admin())
        ->get(route('admin.orders.index', ['q' => '1111']))
        ->assertOk()
        ->assertSee('Lòt Biznis')
        ->assertDontSee($order->reference);
});

it('filters by status', function () {
    anOrder(['status' => 'nouvo']);
    anOrder(['business_name' => 'Fini', 'status' => 'fèt']);

    $this->actingAs(admin())
        ->get(route('admin.orders.index', ['status' => 'fèt']))
        ->assertOk()
        ->assertSee('Fini')
        ->assertDontSee('Chita &amp; Manje', false);
});

it('shows a file with the customer contact and moves it forward', function () {
    $order = anOrder();

    $this->actingAs(admin())
        ->get(route('admin.orders.show', $order))
        ->assertOk()
        ->assertSee($order->reference)
        ->assertSee('+509 3398 8754');

    $this->actingAs(admin())
        ->post(route('admin.orders.update', $order), [
            'status' => 'an_kou',
            'notes' => 'Kliyan an voye mni an sou WhatsApp.',
        ])->assertRedirect();

    expect($order->fresh()->status)->toBe('an_kou')
        ->and($order->fresh()->notes)->toContain('mni an');
});

it('refuses a status it does not know', function () {
    $order = anOrder();

    $this->actingAs(admin())
        ->post(route('admin.orders.update', $order), ['status' => 'peye'])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe('nouvo');
});

// ── Peman ────────────────────────────────────────────────────────────────

// Montan an sere an inite minè: `49.99 * 100` sou yon flotan bay 4998.99…
// Nou wonn yon sèl fwa epi nou verifye kantite egzat la.
it('records a payment in minor units, without losing a centime', function () {
    $order = anOrder();

    $this->actingAs(admin())->post(route('admin.orders.payments.store', $order), [
        'amount' => '49.99',
        'currency' => 'HTG',
        'method' => 'moncash',
        'received_at' => '2026-09-15',
        'reference' => 'MC-12345',
    ])->assertRedirect();

    $payment = AgentPayment::firstOrFail();

    expect($payment->amount_minor)->toBe(4999)
        ->and($payment->currency)->toBe('HTG')
        ->and($order->fresh()->isPaid())->toBeTrue();
});

// Yon avans an goud ak yon rès an dola pa s ajoute. Yon sèl total ki melanje
// de deviz se yon chif ki bay manti.
it('totals payments by currency, never mixing them', function () {
    $order = anOrder();

    foreach ([['500.00', 'HTG'], ['250.00', 'HTG'], ['20.00', 'USD']] as [$amount, $currency]) {
        $this->actingAs(admin())->post(route('admin.orders.payments.store', $order), [
            'amount' => $amount, 'currency' => $currency,
            'method' => 'kach', 'received_at' => '2026-09-15',
        ]);
    }

    expect($order->fresh()->paidByCurrency())->toBe(['HTG' => 75000, 'USD' => 2000]);
});

it('refuses a payment method it does not know', function () {
    $order = anOrder();

    $this->actingAs(admin())->post(route('admin.orders.payments.store', $order), [
        'amount' => '100', 'currency' => 'HTG', 'method' => 'bitcoin', 'received_at' => '2026-09-15',
    ])->assertSessionHasErrors('method');

    expect(AgentPayment::count())->toBe(0);
});

// Yon peman ki pou yon lòt dosye pa dwe ka efase depi dosye sa a.
it('will not delete a payment that belongs to another order', function () {
    $mine = anOrder();
    $other = anOrder(['business_name' => 'Lòt']);

    $payment = $other->payments()->create([
        'amount_minor' => 5000, 'currency' => 'HTG', 'method' => 'kach', 'received_at' => '2026-09-15',
    ]);

    $this->actingAs(admin())
        ->delete(route('admin.orders.payments.destroy', [$mine, $payment]))
        ->assertNotFound();

    expect(AgentPayment::count())->toBe(1);
});

// ── Konfigirasyon ────────────────────────────────────────────────────────

it('keeps the platform settings', function () {
    $this->actingAs(admin())->post(route('admin.settings.update'), [
        'platform_name' => 'LOUVIA',
        'support_whatsapp' => '+509 0000 0000',
        'default_language' => 'ht',
    ])->assertRedirect();

    expect(app(Settings::class)->get('support_whatsapp'))->toBe('+509 0000 0000');
});

it('refuses a language that is not one of ours', function () {
    $this->actingAs(admin())
        ->post(route('admin.settings.update'), ['default_language' => 'de'])
        ->assertSessionHasErrors('default_language');
});

// Yon kle API ki sere an klè nan yon tab se yon kle ki fin fwit depi yon
// moun li baz la.
it('stores a provider key encrypted, never in clear text', function () {
    $this->actingAs(admin())->post(route('admin.settings.key'), [
        'provider' => 'openai',
        'api_key' => 'sk-sekrè-anpil',
    ])->assertRedirect();

    $raw = (string) DB::table('ai_providers')->where('key', 'openai')->value('api_key');

    expect($raw)->not->toBe('sk-sekrè-anpil')
        ->and($raw)->not->toContain('sekrè')
        ->and(AiProviderRecord::query()->where('key', 'openai')->first()?->api_key)->toBe('sk-sekrè-anpil');
});

it('never shows a stored key back on the page', function () {
    app(CredentialStore::class)->forget();

    $this->actingAs(admin())->post(route('admin.settings.key'), [
        'provider' => 'openai', 'api_key' => 'sk-sekrè-anpil',
    ]);

    $this->actingAs(admin())
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertDontSee('sk-sekrè-anpil');
});

// `.env` pi fò pase baz la: yon kle enfrastrikti pa dwe ranplase depi wèb.
it('refuses to overwrite a key that is fixed in the server .env', function () {
    config(['aiprovider.providers.openai.api_key' => 'sk-depi-env']);

    $this->actingAs(admin())->post(route('admin.settings.key'), [
        'provider' => 'openai', 'api_key' => 'sk-depi-wèb',
    ])->assertSessionHasErrors('api_key');

    expect(AiProviderRecord::query()->where('key', 'openai')->exists())->toBeFalse();
});

it('refuses a provider that is not in the catalogue', function () {
    $this->actingAs(admin())
        ->post(route('admin.settings.key'), ['provider' => 'skynet', 'api_key' => 'x'])
        ->assertSessionHasErrors('provider');
});

// ── Kle ki nan baz la limen founisè a toutbon ────────────────────────────

it('turns a provider on from the database key alone', function () {
    config(['aiprovider.providers.openai.api_key' => '']);

    AiProviderRecord::create(['key' => 'openai', 'name' => 'OpenAI', 'api_key' => 'sk-nan-baz']);

    $merged = app(CredentialStore::class)->merge([
        'openai' => ['api_key' => ''],
        'mistral' => ['api_key' => ''],
    ]);

    expect($merged['openai']['api_key'])->toBe('sk-nan-baz')
        ->and($merged['mistral']['api_key'])->toBe('');
});

it('lets the server .env win over the database', function () {
    AiProviderRecord::create(['key' => 'openai', 'name' => 'OpenAI', 'api_key' => 'sk-nan-baz']);

    $merged = app(CredentialStore::class)->merge(['openai' => ['api_key' => 'sk-depi-env']]);

    expect($merged['openai']['api_key'])->toBe('sk-depi-env');
});

// ── Kreye yon administratè ───────────────────────────────────────────────

it('creates the first administrator from the command line', function () {
    $this->artisan('govibe:admin', [
        'email' => 'founder@govibe.ht',
        '--password' => 'yon-modpas-ki-long',
    ])->assertExitCode(0);

    $user = User::where('email', 'founder@govibe.ht')->firstOrFail();

    expect($user->is_admin)->toBeTrue()
        ->and(Hash::check('yon-modpas-ki-long', $user->password))->toBeTrue();
});

it('refuses a password that is too short to be worth anything', function () {
    $this->artisan('govibe:admin', ['email' => 'x@govibe.ht', '--password' => 'kout'])
        ->assertExitCode(1);

    expect(User::count())->toBe(0);
});

it('does not leave a settings row readable in clear text either', function () {
    app(Settings::class)->set('secret_thing', 'valè-sekrè', secret: true);

    $raw = (string) DB::table('platform_settings')->where('key', 'secret_thing')->value('value');

    expect($raw)->not->toContain('sekrè')
        ->and(PlatformSetting::where('key', 'secret_thing')->first()?->value)->toBe('valè-sekrè');
});
