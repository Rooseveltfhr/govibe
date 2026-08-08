<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Menu\ItemOptionPricing;
use PHPUnit\Framework\TestCase;

/**
 * Calcul du supplément de prix des options d'un plat (taille, extras…) —
 * logique pure, imposée côté serveur (jamais le prix envoyé par le client).
 */
class ItemOptionPricingTest extends TestCase
{
    private function sizeGroup(): array
    {
        return [
            'id' => 1, 'name' => 'Taille', 'required' => true, 'multiple' => false,
            'choices' => [
                ['id' => 10, 'label' => 'Petit', 'price_delta' => 0],
                ['id' => 11, 'label' => 'Grand', 'price_delta' => 50],
            ],
        ];
    }

    private function extrasGroup(): array
    {
        return [
            'id' => 2, 'name' => 'Suppléments', 'required' => false, 'multiple' => true,
            'choices' => [
                ['id' => 20, 'label' => 'Fromage', 'price_delta' => 25],
                ['id' => 21, 'label' => 'Bacon', 'price_delta' => 35],
            ],
        ];
    }

    public function test_no_groups_means_no_extra(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([], []);
        $this->assertSame(0.0, $extra);
        $this->assertSame([], $snapshot);
    }

    public function test_required_group_without_selection_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(ItemOptionPricing::ERR_MISSING_REQUIRED);
        ItemOptionPricing::resolve([$this->sizeGroup()], []);
    }

    public function test_optional_group_without_selection_is_fine(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->extrasGroup()], []);
        $this->assertSame(0.0, $extra);
        $this->assertSame([], $snapshot);
    }

    public function test_single_select_valid_choice_adds_delta(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->sizeGroup()], [11]);
        $this->assertSame(50.0, $extra);
        $this->assertSame([['group' => 'Taille', 'label' => 'Grand', 'price_delta' => 50.0]], $snapshot);
    }

    public function test_single_select_ignores_extra_ids_beyond_first(): void
    {
        // Le client ne peut pas tricher en envoyant les deux choix d'un groupe non-multiple.
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->sizeGroup()], [10, 11]);
        $this->assertSame(0.0, $extra); // garde uniquement le premier envoyé (Petit, +0)
        $this->assertCount(1, $snapshot);
    }

    public function test_multiple_select_sums_all_chosen_deltas(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->extrasGroup()], [20, 21]);
        $this->assertSame(60.0, $extra);
        $this->assertCount(2, $snapshot);
    }

    public function test_unknown_choice_id_is_ignored(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->extrasGroup()], [999]);
        $this->assertSame(0.0, $extra);
        $this->assertSame([], $snapshot);
    }

    public function test_combines_multiple_groups(): void
    {
        [$extra, $snapshot] = ItemOptionPricing::resolve([$this->sizeGroup(), $this->extrasGroup()], [11, 20]);
        $this->assertSame(75.0, $extra);
        $this->assertCount(2, $snapshot);
    }
}
