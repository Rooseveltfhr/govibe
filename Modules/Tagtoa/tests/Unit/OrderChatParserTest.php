<?php

namespace Modules\Tagtoa\Tests\Unit;

/*
|--------------------------------------------------------------------------
| TAGTOA MENU — « Commander via Agent IA » sans LLM : mots-clés + quantités
| en local, aucun appel réseau, aucun coût. Ces tests figent ce que le
| parseur reconnaît et, tout aussi important, ce qu'il refuse de deviner.
|--------------------------------------------------------------------------
*/

use Modules\Tagtoa\App\Support\Menu\OrderChatParser;
use PHPUnit\Framework\TestCase;

class OrderChatParserTest extends TestCase
{
    private function catalogue(): array
    {
        return [
            ['id' => 1, 'name' => 'Griot'],
            ['id' => 2, 'name' => 'Riz ak pwa'],
            ['id' => 3, 'name' => 'Cola'],
            ['id' => 4, 'name' => 'Jus naturel'],
            ['id' => 5, 'name' => 'Riz blanc'],
        ];
    }

    public function test_a_single_item_with_a_digit_quantity(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), '2 griot');

        $this->assertSame([['id' => 1, 'name' => 'Griot', 'qty' => 2]], $out['matches']);
        $this->assertSame([], $out['unmatched']);
    }

    public function test_a_word_number_quantity_in_french(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'trois colas');

        $this->assertSame(3, $out['matches'][0]['qty']);
        $this->assertSame(3, $out['matches'][0]['id']);
    }

    public function test_a_word_number_quantity_in_creole(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'de griot');

        $this->assertSame(2, $out['matches'][0]['qty']);
    }

    public function test_no_quantity_defaults_to_one(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'griot');

        $this->assertSame(1, $out['matches'][0]['qty']);
    }

    public function test_multiple_items_separated_by_comma_and_connectors(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), '2 griot, yon cola ak yon jus naturel');

        $ids = array_column($out['matches'], 'id');
        sort($ids);
        $this->assertSame([1, 3, 4], $ids);

        $parQty = [];
        foreach ($out['matches'] as $m) { $parQty[$m['id']] = $m['qty']; }
        $this->assertSame(2, $parQty[1]);
        $this->assertSame(1, $parQty[3]);
        $this->assertSame(1, $parQty[4]);
    }

    public function test_the_same_item_mentioned_twice_sums_its_quantity(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'un griot et deux griot');

        $this->assertCount(1, $out['matches']);
        $this->assertSame(3, $out['matches'][0]['qty']);
    }

    public function test_a_multi_word_name_needs_at_least_half_its_words_present(): void
    {
        // « pwa » seul ne doit pas suffire à reconnaître « Riz ak pwa »
        // (un seul mot sur trois significatifs) — trop peu spécifique.
        $out = OrderChatParser::parse($this->catalogue(), 'pwa');

        $this->assertSame([], $out['matches']);
        $this->assertSame(['pwa'], $out['unmatched']);
    }

    public function test_a_multi_word_name_matches_when_most_of_its_words_are_present(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'riz ak pwa');

        $this->assertSame(2, $out['matches'][0]['id']);
    }

    public function test_a_short_item_name_never_steals_a_word_from_a_longer_matching_name(): void
    {
        // « Riz » (un seul mot) et « Riz blanc » (deux mots) coexistent : le
        // message « riz blanc » doit résoudre au plat le plus SPÉCIFIQUE,
        // pas au générique qui mangerait le mot « riz » en premier.
        $catalogue = array_merge($this->catalogue(), [['id' => 6, 'name' => 'Riz']]);

        $out = OrderChatParser::parse($catalogue, 'riz blanc');

        $this->assertCount(1, $out['matches']);
        $this->assertSame(5, $out['matches'][0]['id']);
    }

    public function test_two_similar_items_are_told_apart_by_their_distinctive_word(): void
    {
        $blanc = OrderChatParser::parse($this->catalogue(), 'riz blanc');
        $pwa = OrderChatParser::parse($this->catalogue(), 'riz ak pwa');

        $this->assertSame(5, $blanc['matches'][0]['id']);
        $this->assertSame(2, $pwa['matches'][0]['id']);
    }

    public function test_gibberish_is_reported_as_unmatched_rather_than_guessed(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'zzqxwv');

        $this->assertSame([], $out['matches']);
        $this->assertSame(['zzqxwv'], $out['unmatched']);
    }

    public function test_an_empty_catalogue_matches_nothing_without_crashing(): void
    {
        $out = OrderChatParser::parse([], '2 griot');

        $this->assertSame([], $out['matches']);
        $this->assertSame(['2 griot'], $out['unmatched']);
    }

    public function test_accents_and_case_never_prevent_a_match(): void
    {
        $out = OrderChatParser::parse([['id' => 9, 'name' => 'Thé glacé']], 'THÉ GLACÉ');

        $this->assertSame(9, $out['matches'][0]['id']);
    }

    public function test_a_lone_connector_or_number_produces_no_phantom_segment(): void
    {
        $out = OrderChatParser::parse($this->catalogue(), 'griot et');

        $this->assertCount(1, $out['matches']);
        $this->assertSame([], $out['unmatched']);
    }
}
