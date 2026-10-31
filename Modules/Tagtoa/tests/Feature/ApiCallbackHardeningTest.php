<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA API — CS-9 : un callback de paiement ne vise que l'Internet public
|--------------------------------------------------------------------------
| SEC-1 — `callback_url` / `webhook_url` : le serveur POSTait sur n'importe quelle URL
|   (localhost, réseau privé, métadonnées du cloud, redirection vers l'un d'eux). L'appelant
|   ne voit pas la réponse (SSRF « aveugle ») mais déclenche l'action.
| SEC-3 — une clé sans secret envoyait une signature VIDE : callback falsifiable.
| SEC-5 — markPaid() : approbation + passerelle simultanées envoyaient DEUX callbacks.
|
| Le DNS est simulé (SafeUrl::resolveUsing) : aucun test ne dépend du réseau.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Models\Api\ApiPayment;
use Modules\Tagtoa\App\Models\Pay\PaymentLedgerEntry;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Services\Api\ApiPaymentService;
use Modules\Tagtoa\App\Support\Api\ApiToken;
use Modules\Tagtoa\App\Support\Net\SafeUrl;
use Modules\Tagtoa\Tests\TestCase;

/** Le vrai service, dont seul l'envoi HTTP est capturé. */
class CapturingApiPayments extends ApiPaymentService
{
    /** @var array<int,array{url:string,body:string,headers:array,options:array}> */
    public array $sent = [];

    protected function post(string $url, string $body, array $headers, array $options): void
    {
        $this->sent[] = compact('url', 'body', 'headers', 'options');
    }
}

class ApiCallbackHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private ApiKey $key;

    /** @var array<string,array<int,string>> */
    private array $dns = ['shop.example.com' => ['93.184.216.34']];

    protected function setUp(): void
    {
        parent::setUp();
        SafeUrl::resolveUsing(fn (string $h) => $this->dns[$h] ?? []);

        $g = ApiToken::generate();
        $this->key = ApiKey::create(['tenant_id' => 't1', 'name' => 'T', 'key_prefix' => $g['prefix'], 'key_hash' => $g['hash'], 'webhook_secret' => 'sekret-de-test']);
        $this->token = $g['token'];

        PaymentPage::create(['tenant_id' => 't1', 'alias' => 'boutique', 'title' => 'Boutique', 'default_currency' => 'HTG', 'is_active' => true]);
        PaymentMethod::create(['payment_page_id' => app(\Modules\Tagtoa\App\Services\Pay\MerchantMethods::class)->library('t1')->id, 'tenant_id' => 't1',
            'type' => 'moncash', 'account_number' => '+509 3000 0000', 'requires_proof' => true, 'is_active' => true]);
    }

    protected function tearDown(): void
    {
        SafeUrl::resolveUsing(null);
        parent::tearDown();
    }

    private function payment(?string $callback = 'https://shop.example.com/hook'): ApiPayment
    {
        return ApiPayment::create(['tenant_id' => 't1', 'api_key_id' => $this->key->id, 'payment_page_id' => PaymentPage::first()->id,
            'reference' => ApiPayment::generateReference(), 'amount' => 100, 'currency' => 'HTG', 'status' => ApiPayment::STATUS_PENDING, 'callback_url' => $callback]);
    }

    /* ---------- SEC-1 : quelles URL sont refusées ---------- */

    /** @dataProvider forbidden */
    public function test_a_callback_aimed_at_anything_but_the_public_internet_is_refused(string $url, string $reason): void
    {
        $this->dns += ['evil.example.net' => ['10.0.0.5'], 'mixed.example.net' => ['93.184.216.34', '192.168.1.10'], 'v6.example.net' => ['fd00::1']];

        $r = SafeUrl::check($url);

        $this->assertFalse($r['ok'], "$url devrait être refusée");
        $this->assertSame($reason, $r['reason']);
    }

    public static function forbidden(): array
    {
        return [
            'http'             => ['http://shop.example.com/hook', 'scheme'],
            'ftp'              => ['ftp://shop.example.com/x', 'scheme'],
            'file'             => ['file:///etc/passwd', 'invalid'],
            'gopher'           => ['gopher://shop.example.com/', 'scheme'],
            'localhost'        => ['https://localhost/hook', 'private_host'],
            'sub.localhost'    => ['https://a.localhost/hook', 'private_host'],
            '.internal'        => ['https://db.internal/hook', 'private_host'],
            'loopback'         => ['https://127.0.0.1/hook', 'private_address'],
            'loopback range'   => ['https://127.9.9.9/hook', 'private_address'],
            '10/8'             => ['https://10.1.2.3/hook', 'private_address'],
            '172.16/12'        => ['https://172.20.0.1/hook', 'private_address'],
            '192.168/16'       => ['https://192.168.0.1/hook', 'private_address'],
            'metadata'         => ['https://169.254.169.254/latest/meta-data', 'private_address'],
            'cgnat'            => ['https://100.64.0.1/hook', 'private_address'],
            'unspecified'      => ['https://0.0.0.0/hook', 'private_address'],
            'multicast'        => ['https://224.0.0.1/hook', 'private_address'],
            'v6 loopback'      => ['https://[::1]/hook', 'private_address'],
            'v6 ula'           => ['https://[fd00::1]/hook', 'private_address'],
            'v6 link-local'    => ['https://[fe80::1]/hook', 'private_address'],
            'v4-mapped v6'     => ['https://[::ffff:10.0.0.1]/hook', 'private_address'],
            'v4-mapped metadata' => ['https://[::ffff:169.254.169.254]/hook', 'private_address'],
            'dns to private'   => ['https://evil.example.net/hook', 'private_address'],
            'dns mixed'        => ['https://mixed.example.net/hook', 'private_address'],
            'dns to v6 ula'    => ['https://v6.example.net/hook', 'private_address'],
            'unresolvable'     => ['https://nope.example.org/hook', 'unresolvable'],
            'credentials'      => ['https://user:pw@shop.example.com/hook', 'credentials'],
            'bad port'         => ['https://shop.example.com:99999/hook', 'invalid'],
            'empty'            => ['', 'invalid'],
            'garbage'          => ['not a url', 'invalid'],
            'trailing dot'     => ['https://localhost./hook', 'private_host'],
            'backslash host'   => ['https://127.0.0.1\\.evil.example.net/hook', 'invalid'],
            'idn'              => ['https://bücher.example.org/hook', 'invalid'],
            'compat v4 v6'     => ['https://[::7f00:1]/hook', 'private_address'],
            'nat64 local'      => ['https://[64:ff9b:1::7f00:1]/hook', 'private_address'],
            'teredo'           => ['https://[2001:0:4136:e378:8000:63bf:3fff:fdd2]/hook', 'private_address'],
            'discard'          => ['https://[100::1]/hook', 'private_address'],
            'site-local'       => ['https://[fec0::1]/hook', 'private_address'],
            'control chars'    => ["https://shop.example.com/a\r\nHost: x", 'invalid'],
        ];
    }

    public function test_a_public_https_callback_is_accepted_and_http_only_when_explicitly_allowed(): void
    {
        $this->assertTrue(SafeUrl::check('https://shop.example.com/hook')['ok']);
        $this->assertTrue(SafeUrl::check('https://93.184.216.34/hook')['ok']);
        $this->assertFalse(SafeUrl::check('http://shop.example.com/hook')['ok']);

        config(['tagtoa.api.allow_http_callbacks' => true]);   // sandbox : autorisation explicite
        $this->assertTrue(SafeUrl::check('http://shop.example.com/hook')['ok']);
        $this->assertFalse(SafeUrl::check('http://127.0.0.1/hook')['ok'], 'http autorisé ne lève JAMAIS la règle des adresses privées');
    }

    public function test_creating_a_payment_with_an_internal_callback_is_rejected_at_the_api(): void
    {
        $auth = ['Authorization' => 'Bearer '.$this->token];

        $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100, 'callback_url' => 'https://169.254.169.254/latest/meta-data'], $auth)
            ->assertStatus(422)->assertJsonValidationErrors('callback_url');
        $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100, 'callback_url' => 'http://shop.example.com/hook'], $auth)
            ->assertStatus(422)->assertJsonValidationErrors('callback_url');
        $this->assertSame(0, ApiPayment::count());

        $this->postJson('/api/v1/tagtoa/payments', ['amount' => 100, 'callback_url' => 'https://shop.example.com/hook'], $auth)->assertStatus(201);
    }

    public function test_the_developer_dashboard_refuses_an_internal_webhook_url_on_a_new_key(): void
    {
        $this->be(new \Illuminate\Auth\GenericUser(['id' => 1, 'tenant_id' => 't1', 'name' => 'Patron']));

        $this->post(route('tagtoa.developer.store'), ['name' => 'X', 'webhook_url' => 'https://10.0.0.1/hook'])
            ->assertSessionHasErrors('webhook_url');
        $this->assertSame(1, ApiKey::count(), 'Aucune clé créée avec une URL interne.');
    }

    /* ---------- SEC-1 : l'envoi lui-même ---------- */

    public function test_the_callback_is_signed_pinned_to_the_checked_address_and_follows_no_redirect(): void
    {
        $svc = new CapturingApiPayments();

        $this->assertTrue($svc->notifyCallback($this->payment()));

        $sent = $svc->sent[0];
        $this->assertSame('https://shop.example.com/hook', $sent['url']);
        $this->assertSame(hash_hmac('sha256', $sent['body'], 'sekret-de-test'), $sent['headers']['X-Tagtoa-Signature']);
        $this->assertFalse($sent['options']['allow_redirects'], 'Aucune redirection suivie.');
        $this->assertSame('', $sent['options']['proxy'], 'Jamais via un proxy : il ignorerait l\'épinglage et atteindrait le réseau interne.');
        if (defined('CURLOPT_RESOLVE')) {
            $this->assertSame(['shop.example.com:443:93.184.216.34'], $sent['options']['curl'][CURLOPT_RESOLVE], 'Appel épinglé sur l\'adresse vérifiée.');
        }
    }

    public function test_a_callback_whose_dns_now_points_inside_is_never_sent(): void
    {
        $svc = new CapturingApiPayments();
        $p = $this->payment('https://shop.example.com/hook');
        $this->dns['shop.example.com'] = ['10.0.0.5'];          // le DNS a changé depuis la création (rebinding)

        $this->assertFalse($svc->notifyCallback($p));

        $this->assertSame([], $svc->sent);
    }

    /* ---------- SEC-3 : jamais de signature vide ---------- */

    public function test_a_key_without_a_secret_gets_one_and_the_signature_is_never_empty(): void
    {
        $this->key->forceFill(['webhook_secret' => null])->save();
        $svc = new CapturingApiPayments();

        $svc->notifyCallback($this->payment());

        $secret = (string) $this->key->fresh()->webhook_secret;
        $sent = $svc->sent[0];
        $this->assertNotSame('', $secret);
        $this->assertNotSame('', $sent['headers']['X-Tagtoa-Signature']);
        $this->assertSame(hash_hmac('sha256', $sent['body'], $secret), $sent['headers']['X-Tagtoa-Signature']);
    }

    /* ---------- SEC-5 : un seul callback, une seule écriture au grand livre ---------- */

    public function test_two_simultaneous_approvals_send_one_callback_and_write_one_ledger_row(): void
    {
        $svc = new CapturingApiPayments();
        $a = $this->payment();
        $b = ApiPayment::find($a->id);            // la seconde requête a lu le paiement AVANT que la première ne l'ait payé

        $svc->markPaid($a);
        $svc->markPaid($b);

        $this->assertCount(1, $svc->sent);
        $this->assertSame(1, PaymentLedgerEntry::where('payable_id', $a->id)->where('to_status', ApiPayment::STATUS_PAID)->count());
        $this->assertTrue($a->fresh()->isPaid());
    }

    public function test_the_callback_leaves_only_after_the_enclosing_transaction_commits(): void
    {
        $svc = new CapturingApiPayments();
        $p = $this->payment();

        DB::transaction(function () use ($svc, $p) {
            $svc->markPaid($p);
            $this->assertSame([], $svc->sent, 'Pas avant le commit.');
        });

        $this->assertCount(1, $svc->sent);
    }

    public function test_a_payment_already_paid_sends_nothing(): void
    {
        $svc = new CapturingApiPayments();
        $p = $this->payment();
        $p->update(['status' => ApiPayment::STATUS_PAID, 'paid_at' => now()]);

        $svc->markPaid($p);

        $this->assertSame([], $svc->sent);
    }

    public function test_two_concurrent_callbacks_for_a_legacy_key_share_one_generated_secret(): void
    {
        $this->key->forceFill(['webhook_secret' => null])->save();
        $svc = new CapturingApiPayments();

        $svc->notifyCallback($this->payment());
        $first = (string) $this->key->fresh()->webhook_secret;
        $svc->notifyCallback($this->payment());

        $this->assertNotSame('', $first);
        $this->assertSame($first, (string) $this->key->fresh()->webhook_secret, 'Le secret déjà généré n\'est jamais écrasé.');
        $this->assertSame($svc->sent[0]['headers']['X-Tagtoa-Signature'] === hash_hmac('sha256', $svc->sent[0]['body'], $first), true);
    }
}
