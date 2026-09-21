<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Support\Catalog\CategoryPresets;
use PHPUnit\Framework\TestCase;

/**
 * Les rayons les plus courants d'un petit commerce haïtien, proposés en un
 * clic dans POS ET dans MENU — LISTE UNIQUE, lue par les deux écrans (voir
 * pos/categories.blade.php et menu/form.blade.php). Ce test protège
 * uniquement le fait qu'elle existe et reste utilisable ; les deux écrans
 * ont chacun leurs propres tests de câblage.
 */
class CategoryPresetsTest extends TestCase
{
    public function test_it_lists_the_categories_a_haitian_shop_actually_needs(): void
    {
        foreach (['Boisson', 'Alimentation', 'Légumes', 'Alcool'] as $attendu) {
            $this->assertContains($attendu, CategoryPresets::COMMON);
        }
    }

    public function test_the_list_has_no_duplicate_and_no_blank_entry(): void
    {
        $this->assertSame(array_unique(CategoryPresets::COMMON), CategoryPresets::COMMON);
        foreach (CategoryPresets::COMMON as $nom) {
            $this->assertNotSame('', trim($nom));
        }
    }
}
