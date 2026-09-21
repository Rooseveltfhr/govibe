<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA POS — alignement avec l'offline du menu public (MENU-F5).
|
| La caisse avait déjà l'essentiel — file d'attente des ventes hors ligne
| (q/setQ/flush), retour automatique à la reconnexion (`online`), et un
| client_uuid idempotent côté serveur (PosIdempotencyTest) — mais deux
| écarts subsistaient face à ce que le menu public a maintenant :
|
|   1. Aucun filet de rattrapage : seul l'événement `online` déclenchait
|      flush() ; un navigateur qui ne le déclenche pas fiablement laissait
|      une vente en attente bloquée bien après le retour réel du réseau.
|   2. Le panier EN COURS (pas encore encaissé) vivait en mémoire seule :
|      un rechargement ou une coupure de courant — fréquente là où cette
|      caisse tourne — perdait tout ce qui avait été sonné.
|--------------------------------------------------------------------------
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Services\Pos\PosCatalog;
use Modules\Tagtoa\Tests\TestCase;

class PosOfflineResilienceTest extends TestCase
{
    use RefreshDatabase;

    private function ecran(): string
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
        $caisse = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse 1'],
            ['currency' => 'HTG', 'is_active' => true]);
        app(PosCatalog::class)->save($caisse, ['name' => 'Prestige', 'price' => 250, 'stock' => 10, 'is_active' => true]);

        return $this->get(route('tagtoa.pos.register', $caisse->id))->assertOk()->getContent();
    }

    public function test_a_periodic_safety_net_flushes_the_pending_sale_queue(): void
    {
        $html = $this->ecran();

        $this->assertMatchesRegularExpression('/setInterval\(\s*flush\s*,\s*20000\s*\)/', $html);
    }

    public function test_the_in_progress_cart_is_persisted_and_restored_per_terminal(): void
    {
        $html = $this->ecran();

        $this->assertStringContainsString("var CARTKEY='tagtoa_pos_cart_'+T", $html);
        $this->assertStringContainsString('localStorage.getItem(CARTKEY', $html);
        $this->assertStringContainsString('function sauvegarderPanier()', $html);
        $this->assertStringContainsString('sauvegarderPanier();', $html);
    }

    public function test_two_terminals_never_share_the_same_cart_storage_key(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-1', 'name' => 'Roosevelt']));
        \Modules\Tagtoa\App\Support\Tenant::flush();
        $a = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse A'], ['currency' => 'HTG', 'is_active' => true]);
        $b = Terminal::firstOrCreate(['tenant_id' => 't-1', 'name' => 'Caisse B'], ['currency' => 'HTG', 'is_active' => true]);

        $htmlA = $this->get(route('tagtoa.pos.register', $a->id))->assertOk()->getContent();
        $htmlB = $this->get(route('tagtoa.pos.register', $b->id))->assertOk()->getContent();

        preg_match("/data-terminal=\"(\d+)\"/", $htmlA, $mA);
        preg_match("/data-terminal=\"(\d+)\"/", $htmlB, $mB);
        $this->assertNotSame($mA[1] ?? null, $mB[1] ?? null);
    }
}
