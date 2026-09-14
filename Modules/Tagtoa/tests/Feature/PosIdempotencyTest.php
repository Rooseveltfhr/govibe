<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Sale;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosService;
use Modules\Tagtoa\Tests\TestCase;

/**
 * POS — la clé d'idempotence hors-ligne appartient à UNE caisse.
 *
 * La caisse envoie un `client_uuid` pour pouvoir rejouer une vente sans la
 * compter deux fois. Ce champ accepte n'importe quelle chaîne : une caisse qui
 * numérote « 1 » ou « vente-42 » entrait en collision avec un autre commerce.
 */
class PosIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function terminal(string $tenantId, string $name): Terminal
    {
        return Terminal::create([
            'tenant_id' => $tenantId, 'name' => $name, 'currency' => 'HTG', 'is_active' => true,
        ]);
    }

    private function sale(Terminal $t, string $uuid, float $amount): Sale
    {
        return app(PosService::class)->recordSale($t, [
            'client_uuid' => $uuid,
            'items'       => [['name' => 'Article', 'price' => $amount, 'qty' => 1]],
        ]);
    }

    public function test_replaying_the_same_sale_on_the_same_till_does_not_count_it_twice(): void
    {
        $till = $this->terminal('t-1', 'Caisse 1');

        $first  = $this->sale($till, 'vente-42', 500);
        $second = $this->sale($till, 'vente-42', 500);

        $this->assertSame($first->id, $second->id, 'La reprise hors-ligne doit être idempotente.');
        $this->assertSame(1, Sale::count());
    }

    public function test_two_merchants_using_the_same_key_each_keep_their_own_sale(): void
    {
        // Le cœur du correctif. Deux caisses qui numérotent « vente-42 » chacune
        // de leur côté : avant, la seconde vente n'était JAMAIS enregistrée et
        // l'API renvoyait la référence du premier commerce.
        $chezA = $this->terminal('t-1', 'Boulangerie');
        $chezB = $this->terminal('t-2', 'Bar');

        $venteA = $this->sale($chezA, 'vente-42', 500);
        $venteB = $this->sale($chezB, 'vente-42', 1200);

        $this->assertNotSame($venteA->id, $venteB->id, 'Chaque commerce doit garder sa propre vente.');
        $this->assertSame(2, Sale::count(), 'Aucune recette ne doit être perdue.');

        // Aucune référence ne fuit d'un commerce à l'autre.
        $this->assertNotSame($venteA->reference, $venteB->reference);
        $this->assertSame($chezA->id, $venteA->terminal_id);
        $this->assertSame($chezB->id, $venteB->terminal_id);

        // Et les montants ne sont pas mélangés.
        $this->assertEquals(500.0, (float) $venteA->total);
        $this->assertEquals(1200.0, (float) $venteB->total);
    }

    public function test_two_tills_of_the_same_merchant_do_not_collide_either(): void
    {
        // Deux caisses d'un même restaurant peuvent numéroter pareil.
        $salle    = $this->terminal('t-1', 'Salle');
        $terrasse = $this->terminal('t-1', 'Terrasse');

        $this->sale($salle, 'vente-1', 300);
        $this->sale($terrasse, 'vente-1', 750);

        $this->assertSame(2, Sale::count());
    }

    public function test_sales_without_a_key_are_never_merged(): void
    {
        // Sans clé d'idempotence, chaque envoi est une vente distincte : deux
        // clients peuvent commander exactement la même chose.
        $till = $this->terminal('t-1', 'Caisse 1');

        app(PosService::class)->recordSale($till, ['items' => [['name' => 'Café', 'price' => 100, 'qty' => 1]]]);
        app(PosService::class)->recordSale($till, ['items' => [['name' => 'Café', 'price' => 100, 'qty' => 1]]]);

        $this->assertSame(2, Sale::count());
    }
}
