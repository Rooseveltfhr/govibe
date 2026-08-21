<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Api\ApiToken;
use PHPUnit\Framework\TestCase;

/**
 * Jetons de l'API développeur : génération, hachage, extraction du préfixe et
 * vérification en temps constant. Logique pure et sensible côté sécurité.
 */
class ApiTokenTest extends TestCase
{
    public function test_generated_token_starts_with_its_own_prefix(): void
    {
        $g = ApiToken::generate();

        $this->assertStringStartsWith($g['prefix'].'_', $g['token']);
        $this->assertStringStartsWith('tag_live_', $g['prefix']);
    }

    public function test_test_mode_uses_a_distinct_prefix(): void
    {
        $this->assertStringStartsWith('tag_test_', ApiToken::generate(false)['prefix']);
    }

    public function test_stored_hash_matches_the_token_and_is_not_the_token(): void
    {
        $g = ApiToken::generate();

        $this->assertTrue(ApiToken::matches($g['token'], $g['hash']));
        // Le secret ne doit jamais être déductible du hash stocké.
        $this->assertNotSame($g['token'], $g['hash']);
        $this->assertSame(64, strlen($g['hash'])); // sha256 hex
        $this->assertStringNotContainsString($g['hash'], $g['token']);
    }

    public function test_two_generated_tokens_are_never_identical(): void
    {
        $a = ApiToken::generate();
        $b = ApiToken::generate();

        $this->assertNotSame($a['token'], $b['token']);
        $this->assertNotSame($a['prefix'], $b['prefix']);
    }

    public function test_a_wrong_token_does_not_match(): void
    {
        $g = ApiToken::generate();

        $this->assertFalse(ApiToken::matches($g['prefix'].'_mauvais_secret', $g['hash']));
        $this->assertFalse(ApiToken::matches(null, $g['hash']));
        $this->assertFalse(ApiToken::matches($g['token'], null));
        $this->assertFalse(ApiToken::matches($g['token'], ''));
    }

    public function test_prefix_is_extracted_from_a_valid_token(): void
    {
        $g = ApiToken::generate();

        $this->assertSame($g['prefix'], ApiToken::prefixOf($g['token']));
    }

    public function test_malformed_tokens_yield_no_prefix(): void
    {
        // Aucune de ces entrées ne doit déclencher une recherche en base.
        foreach ([null, '', 'nimportequoi', 'tag_live_only', 'tag_live_a_b_c', 'autre_live_aaa_bbb', 'tag_live__secret'] as $bad) {
            $this->assertNull(ApiToken::prefixOf($bad), 'devrait être rejeté : '.var_export($bad, true));
        }
    }

    public function test_bearer_header_is_parsed_case_insensitively(): void
    {
        $this->assertSame('abc', ApiToken::fromHeader('Bearer abc'));
        $this->assertSame('abc', ApiToken::fromHeader('bearer abc'));
        $this->assertSame('abc', ApiToken::fromHeader('  abc  '));
        $this->assertNull(ApiToken::fromHeader(null));
        $this->assertNull(ApiToken::fromHeader('   '));
    }

    public function test_mask_never_reveals_the_secret(): void
    {
        $g = ApiToken::generate();
        $masked = ApiToken::mask($g['prefix']);

        $this->assertStringStartsWith($g['prefix'], $masked);
        $this->assertStringNotContainsString($g['token'], $masked);
    }
}
