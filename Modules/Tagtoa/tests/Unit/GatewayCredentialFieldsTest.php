<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Pay\GatewayCredentialFields as F;
use PHPUnit\Framework\TestCase;

/**
 * Identifiants des passerelles : description des champs déduite de la config,
 * fusion base/.env, et surtout la règle « un champ vide ne détruit rien ».
 * Logique pure et sensible : une erreur ici couperait les encaissements.
 */
class GatewayCredentialFieldsTest extends TestCase
{
    private function moncash(): array
    {
        return [
            'label'       => 'MonCash',
            'mode'        => 'sandbox',
            'credentials' => ['client_id' => null, 'secret' => null],
        ];
    }

    private function stripe(): array
    {
        return [
            'label'          => 'Stripe',
            'credentials'    => ['key' => 'pk_env', 'secret' => 'sk_env'],
            'webhook_secret' => 'whsec_env',
        ];
    }

    public function test_describe_lists_credentials_mode_and_extra_secrets(): void
    {
        $d = F::describe($this->moncash());
        $this->assertSame(['client_id', 'secret'], $d['credentials']);
        $this->assertTrue($d['has_mode']);
        $this->assertSame([], $d['extras']);

        $s = F::describe($this->stripe());
        $this->assertSame(['key', 'secret'], $s['credentials']);
        $this->assertSame(['webhook_secret'], $s['extras']);
        $this->assertFalse($s['has_mode']); // Stripe n'a pas de mode en config
    }

    public function test_describe_never_exposes_the_label_as_an_editable_field(): void
    {
        $this->assertNotContains('label', F::describe($this->stripe())['extras']);
    }

    public function test_stored_values_override_the_env_config(): void
    {
        $merged = F::merge($this->stripe(), [
            'credentials'    => ['secret' => 'sk_depuis_la_base'],
            'webhook_secret' => 'whsec_base',
        ]);

        $this->assertSame('sk_depuis_la_base', $merged['credentials']['secret']);
        $this->assertSame('whsec_base', $merged['webhook_secret']);
        // Ce qui n'est pas surchargé garde la valeur du .env.
        $this->assertSame('pk_env', $merged['credentials']['key']);
    }

    public function test_empty_stored_values_never_erase_the_env_config(): void
    {
        // Cas critique : une ligne en base avec des champs vides ne doit pas
        // débrancher une passerelle déjà configurée côté serveur.
        $merged = F::merge($this->stripe(), ['credentials' => ['secret' => '', 'key' => null]]);

        $this->assertSame('sk_env', $merged['credentials']['secret']);
        $this->assertSame('pk_env', $merged['credentials']['key']);
    }

    public function test_merging_nothing_returns_the_config_untouched(): void
    {
        $this->assertSame($this->stripe(), F::merge($this->stripe(), null));
        $this->assertSame($this->stripe(), F::merge($this->stripe(), []));
    }

    public function test_submitting_a_blank_field_keeps_the_existing_secret(): void
    {
        // L'utilisateur ne revoit jamais le secret : soumettre le formulaire
        // sans le retaper doit le conserver.
        $existing = ['credentials' => ['client_id' => 'abc', 'secret' => 'xyz']];

        $out = F::applySubmission($existing, ['credentials' => ['client_id' => '', 'secret' => '']]);

        $this->assertSame('abc', $out['credentials']['client_id']);
        $this->assertSame('xyz', $out['credentials']['secret']);
    }

    public function test_submitting_a_new_value_replaces_only_that_field(): void
    {
        $existing = ['credentials' => ['client_id' => 'abc', 'secret' => 'xyz']];

        $out = F::applySubmission($existing, ['credentials' => ['secret' => 'nouveau']]);

        $this->assertSame('abc', $out['credentials']['client_id']);
        $this->assertSame('nouveau', $out['credentials']['secret']);
    }

    public function test_submitted_values_are_trimmed(): void
    {
        // Un copier-coller ramène souvent une espace : elle casserait l'auth API.
        $out = F::applySubmission([], ['credentials' => ['secret' => '  sk_test  '], 'mode' => ' live ']);

        $this->assertSame('sk_test', $out['credentials']['secret']);
        $this->assertSame('live', $out['mode']);
    }

    public function test_clearing_wipes_everything(): void
    {
        $existing = ['credentials' => ['secret' => 'xyz'], 'mode' => 'live'];

        $this->assertSame([], F::applySubmission($existing, ['credentials' => ['secret' => 'peu importe']], true));
    }

    public function test_sources_report_where_each_value_comes_from_without_revealing_it(): void
    {
        $sources = F::sources($this->stripe(), ['credentials' => ['secret' => 'sk_base']]);

        $this->assertSame('db', $sources['credentials.secret']);   // saisi dans l'interface
        $this->assertSame('env', $sources['credentials.key']);     // vient du .env
        $this->assertSame('env', $sources['webhook_secret']);

        // Aucune valeur réelle ne doit transiter dans ce tableau.
        $this->assertStringNotContainsString('sk_base', json_encode($sources));
        $this->assertStringNotContainsString('pk_env', json_encode($sources));
    }

    public function test_sources_flag_missing_values(): void
    {
        $sources = F::sources($this->moncash(), null);

        $this->assertSame('empty', $sources['credentials.client_id']);
        $this->assertSame('empty', $sources['credentials.secret']);
    }

    public function test_label_humanises_field_names(): void
    {
        $this->assertSame('Client Id', F::label('client_id'));
        $this->assertSame('Webhook Secret', F::label('webhook_secret'));
    }
}
