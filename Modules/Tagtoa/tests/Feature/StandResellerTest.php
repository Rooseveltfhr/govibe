<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Stand\Reseller;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Models\Stand\StandEvent;
use Modules\Tagtoa\App\Services\Stand\ResellerService;
use Modules\Tagtoa\App\Services\Stand\StandMinter;
use Modules\Tagtoa\App\Support\Stand\StandState;
use Modules\Tagtoa\Tests\TestCase;

/**
 * M5 — LE REVENDEUR.
 *
 * Une seule règle gouverne ce fichier, et tout le reste en découle :
 *
 *   LE REVENDEUR DÉPLACE L'OBJET. IL NE TOUCHE JAMAIS À L'IDENTITÉ NUMÉRIQUE.
 *
 * S'il pouvait réclamer à la place du marchand, il possèderait le compte : il
 * déciderait de ce que le QR affiche, verrait les commandes, et pourrait rendre
 * le stand inutilisable en partant. Toute la valeur du produit tiendrait entre
 * ses mains plutôt qu'entre celles du commerçant qui l'a payé.
 */
class StandResellerTest extends TestCase
{
    use RefreshDatabase;

    private array $secrets = [];

    private function fabriquer(int $n = 20): void
    {
        $r = app(StandMinter::class)->mint('TAGTOA-2026-001', $n);
        $this->secrets = $r['secrets'];
    }

    private function revendeur(string $businessId = 'biz-rev', string $nom = 'Chez Wilner'): Reseller
    {
        return Reseller::create([
            'business_id' => $businessId, 'name' => $nom,
            'zone' => 'Cap-Haïtien', 'commission_pct' => 15, 'is_active' => true,
        ]);
    }

    private function connecte(string $tenantId): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $tenantId, 'name' => 'Wilner']));
    }

    private function service(): ResellerService
    {
        return app(ResellerService::class);
    }

    private function lot(): int
    {
        return (int) Stand::first()->batch_id;
    }

    /* ==================================================================
       LA RÈGLE : l'objet bouge, l'identité reste.
       ================================================================== */

    public function test_declaring_a_sale_never_claims_the_stand(): void
    {
        // LE test du module. Après la vente, le stand est physiquement parti
        // mais reste NON RÉCLAMÉ : c'est le marchand qui grattera et réclamera,
        // avec un code que le revendeur n'a jamais vu.
        $this->fabriquer(5);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 5);

        $stand = Stand::orderBy('serial')->first();
        $r = $this->service()->declareSale($rev, $stand->public_id);

        $this->assertSame(ResellerService::OK, $r['result']);

        $stand->refresh();
        $this->assertSame(StandState::SOLD, $stand->physical_state, 'L\'objet est parti.');
        $this->assertSame(StandState::UNCLAIMED, $stand->digital_state, 'L\'identité reste libre.');
        $this->assertNull($stand->tenant_id, 'Aucun commerce ne possède encore ce stand.');
    }

    public function test_the_merchant_can_still_claim_a_stand_the_reseller_sold(): void
    {
        // La vérification que la chaîne tient de bout en bout : le revendeur
        // vend, puis le commerçant active avec son code gratté.
        $this->fabriquer(3);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 3);

        $stand = Stand::orderBy('serial')->first();
        $this->service()->declareSale($rev, $stand->public_id);

        $activateur = app(\Modules\Tagtoa\App\Services\Stand\StandActivator::class);
        $charge = \Modules\Tagtoa\App\Support\Stand\StandScratch::payload(
            $stand->public_id, $this->secrets[$stand->public_id]
        );

        $r = $activateur->activate($charge, 'biz-client');

        $this->assertSame(\Modules\Tagtoa\App\Services\Stand\StandActivator::OK, $r['result']);
        $this->assertSame('biz-client', $stand->refresh()->tenant_id);
    }

    public function test_the_console_never_renders_a_secret(): void
    {
        // Aucun code d'activation, nulle part. Un revendeur qui verrait les
        // codes de son stock pourrait réclamer cinquante stands à son nom, ou
        // vendre le même stand deux fois.
        $this->fabriquer(5);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 5);
        $this->connecte('biz-rev');

        $html = $this->get(route('tagtoa.reseller.index'))->assertOk()->getContent();

        foreach (Stand::all() as $s) {
            $this->assertStringNotContainsString($this->secrets[$s->public_id], $html,
                "Le code de {$s->public_id} apparaît dans la console du revendeur.");
            $this->assertStringNotContainsString((string) $s->secret_hash, $html,
                'Le haché du secret ne doit pas non plus sortir.');
        }
        $this->assertStringNotContainsString('secret', $html);
    }

    public function test_the_history_screen_never_renders_a_secret_either(): void
    {
        $this->fabriquer(2);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 2);
        $this->connecte('biz-rev');

        $stand = Stand::first();
        $html = $this->get(route('tagtoa.reseller.history', $stand->id))->assertOk()->getContent();

        $this->assertStringNotContainsString($this->secrets[$stand->public_id], $html);
        $this->assertStringNotContainsString((string) $stand->secret_hash, $html);
    }

    public function test_the_founder_s_screen_never_renders_a_secret(): void
    {
        // Le fondateur les a, mais ailleurs : dans le fichier de frappe, en
        // 0600, détruit après tirage. Une page web les rendrait consultables
        // depuis n'importe quel navigateur resté ouvert.
        $this->fabriquer(3);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $this->connecte('biz-fondateur');

        $html = $this->get(route('tagtoa.superadmin.resellers'))->assertOk()->getContent();

        foreach (Stand::all() as $s) {
            $this->assertStringNotContainsString($this->secrets[$s->public_id], $html);
            $this->assertStringNotContainsString((string) $s->secret_hash, $html);
        }
    }

    /* ==================================================================
       Cloisonnement du réseau.
       ================================================================== */

    public function test_a_reseller_never_sees_another_s_stock(): void
    {
        // Voir le stock des autres, c'est voir la carte complète du réseau de
        // distribution de TAGTOA.
        $this->fabriquer(10);
        $un   = $this->revendeur('biz-un', 'Chez Un');
        $deux = $this->revendeur('biz-deux', 'Chez Deux');

        $this->service()->allocate($un, $this->lot(), 1, 5);
        $this->service()->allocate($deux, $this->lot(), 6, 10);

        $this->connecte('biz-un');
        $html = $this->get(route('tagtoa.reseller.index', ['etat' => 'tous']))->assertOk()->getContent();

        foreach (Stand::whereBetween('serial', [1, 5])->get() as $s) {
            $this->assertStringContainsString($s->public_id, $html);
        }
        foreach (Stand::whereBetween('serial', [6, 10])->get() as $s) {
            $this->assertStringNotContainsString($s->public_id, $html);
        }
    }

    public function test_a_reseller_cannot_declare_another_s_sale(): void
    {
        // Sans ce contrôle, un revendeur déclarerait les ventes d'un autre —
        // et toucherait sa commission.
        $this->fabriquer(10);
        $un   = $this->revendeur('biz-un', 'Chez Un');
        $deux = $this->revendeur('biz-deux', 'Chez Deux');
        $this->service()->allocate($deux, $this->lot(), 6, 10);

        $sien = Stand::where('serial', 6)->first();
        $r = $this->service()->declareSale($un, $sien->public_id);

        $this->assertSame(ResellerService::PAS_A_LUI, $r['result']);
        $this->assertSame(StandState::ALLOCATED, $sien->refresh()->physical_state);
    }

    public function test_the_refusal_never_says_who_holds_the_stand(): void
    {
        // Répondre « ce stand est chez un autre revendeur » confirmerait,
        // numéro par numéro, ce que détient le réseau.
        $this->fabriquer(10);
        $this->revendeur('biz-un', 'Chez Un');
        $deux = $this->revendeur('biz-deux', 'Chez Deux');
        $this->service()->allocate($deux, $this->lot(), 6, 10);

        $this->connecte('biz-un');
        $sien = Stand::where('serial', 6)->first();

        $this->post(route('tagtoa.reseller.sell'), ['public_id' => $sien->public_id])
            ->assertRedirect();

        $message = session('error');
        $this->assertNotNull($message);
        $this->assertStringNotContainsString('Chez Deux', $message);
    }

    public function test_a_business_that_is_not_a_reseller_has_no_console(): void
    {
        $this->connecte('biz-ordinaire');

        $this->get(route('tagtoa.reseller.index'))->assertNotFound();
        $this->post(route('tagtoa.reseller.sell'), ['public_id' => 'TG-000001'])->assertNotFound();
    }

    public function test_a_deactivated_reseller_loses_the_console_at_once(): void
    {
        // Un compte désactivé qui garderait l'accès continuerait de déclarer
        // des ventes. La console relit l'état à chaque requête.
        $this->fabriquer(3);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $this->connecte('biz-rev');

        $this->get(route('tagtoa.reseller.index'))->assertOk();

        $rev->update(['is_active' => false]);

        $this->get(route('tagtoa.reseller.index'))->assertNotFound();
    }

    public function test_the_stock_stays_with_a_deactivated_reseller(): void
    {
        // Les cartons sont chez lui. Prétendre le contraire rendrait
        // l'inventaire de TAGTOA faux.
        $this->fabriquer(3);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 3);

        $rev->update(['is_active' => false]);

        $this->assertSame(3, Stand::heldBy($rev->id)->count());
    }

    /* ==================================================================
       L'affectation par carton.
       ================================================================== */

    public function test_a_carton_is_allocated_as_a_range(): void
    {
        // On n'affecte pas trente stands un par un : on envoie le carton 41–80.
        $this->fabriquer(20);
        $rev = $this->revendeur();

        $r = $this->service()->allocate($rev, $this->lot(), 5, 14);

        $this->assertSame(ResellerService::OK, $r['result']);
        $this->assertSame(10, $r['count']);
        $this->assertSame(10, Stand::heldBy($rev->id)->count());
        $this->assertSame(StandState::ALLOCATED, Stand::where('serial', 5)->first()->physical_state);
        $this->assertNull(Stand::where('serial', 4)->first()->holder_id);
    }

    public function test_a_reversed_range_is_understood_rather_than_refused(): void
    {
        // « du 14 au 5 » est une inversion de doigts, pas une intention.
        $this->fabriquer(20);
        $rev = $this->revendeur();

        $this->assertSame(10, $this->service()->allocate($rev, $this->lot(), 14, 5)['count']);
    }

    public function test_a_stand_already_sold_is_never_reallocated(): void
    {
        // Ce serait dire à un revendeur qu'il détient un objet qui est sur la
        // table de quelqu'un d'autre.
        $this->fabriquer(5);
        $un   = $this->revendeur('biz-un', 'Chez Un');
        $deux = $this->revendeur('biz-deux', 'Chez Deux');

        $this->service()->allocate($un, $this->lot(), 1, 5);
        $this->service()->declareSale($un, Stand::where('serial', 1)->first()->public_id);

        $r = $this->service()->allocate($deux, $this->lot(), 1, 5);

        $this->assertSame(0, $r['count'], 'Rien ne doit repartir : tout est déjà affecté ou vendu.');
        $this->assertSame($un->id, (int) Stand::where('serial', 1)->first()->holder_id);
    }

    public function test_a_claimed_stand_is_never_reallocated(): void
    {
        $this->fabriquer(3);
        $rev = $this->revendeur();
        Stand::where('serial', 1)->update([
            'digital_state' => StandState::ACTIVE, 'tenant_id' => 'biz-client',
        ]);

        $this->service()->allocate($rev, $this->lot(), 1, 3);

        $this->assertNull(Stand::where('serial', 1)->first()->holder_id);
        $this->assertSame(2, Stand::heldBy($rev->id)->count());
    }

    public function test_an_absurd_range_is_refused_whole(): void
    {
        // Au-delà, ce n'est plus un carton : c'est une erreur de saisie, et
        // affecter cinquante mille stands par accident est difficile à défaire.
        $this->fabriquer(5);
        $rev = $this->revendeur();

        $r = $this->service()->allocate($rev, $this->lot(), 1, ResellerService::MAX_PLAGE + 2);

        $this->assertSame(ResellerService::RIEN, $r['result']);
        $this->assertSame(0, Stand::heldBy($rev->id)->count());
    }

    /* ==================================================================
       La déclaration de vente.
       ================================================================== */

    public function test_declaring_twice_does_not_write_twice(): void
    {
        // Un revendeur qui hésite ne doit pas créer deux ventes.
        $this->fabriquer(3);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $stand = Stand::first();

        $this->service()->declareSale($rev, $stand->public_id);
        $r = $this->service()->declareSale($rev, $stand->public_id);

        $this->assertSame(ResellerService::DEJA_VENDU, $r['result']);
        $this->assertSame(1, StandEvent::where('stand_id', $stand->id)
            ->where('event', StandEvent::SOLD)->count());
    }

    public function test_the_sale_records_who_bought_it(): void
    {
        // C'est ce qui permet de retrouver à qui un stand a été vendu quand il
        // revient cassé six mois plus tard.
        $this->fabriquer(3);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $stand = Stand::first();

        $this->service()->declareSale($rev, $stand->public_id, [
            'buyer_name' => 'Restaurant Kay Rose', 'buyer_phone' => '50931234567',
        ]);

        $ev = StandEvent::where('stand_id', $stand->id)->where('event', StandEvent::SOLD)->firstOrFail();
        $this->assertSame('Restaurant Kay Rose', $ev->meta['buyer_name'] ?? null);
        $this->assertSame('50931234567', $ev->meta['buyer_phone'] ?? null);
        $this->assertSame('Chez Wilner', $ev->actor_name);
    }

    public function test_a_lost_stand_cannot_be_declared_sold(): void
    {
        $this->fabriquer(3);
        $rev = $this->revendeur();
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $stand = Stand::first();
        $stand->forceFill(['physical_state' => StandState::LOST])->save();

        $this->assertSame(ResellerService::PAS_A_LUI,
            $this->service()->declareSale($rev, $stand->public_id)['result']);
    }

    public function test_an_unknown_number_says_so(): void
    {
        $this->fabriquer(3);
        $rev = $this->revendeur();

        $this->assertSame(ResellerService::INTROUVABLE,
            $this->service()->declareSale($rev, 'TG-999999')['result']);
    }

    /* ==================================================================
       Les écrans.
       ================================================================== */

    public function test_the_console_counts_what_he_holds(): void
    {
        $this->fabriquer(10);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 10);
        $this->service()->declareSale($rev, Stand::where('serial', 1)->first()->public_id);
        $this->service()->declareSale($rev, Stand::where('serial', 2)->first()->public_id);

        $c = $this->service()->inventory($rev);

        $this->assertSame(8, $c['en_stock']);
        $this->assertSame(2, $c['vendus']);
        $this->assertSame(10, $c['total']);
    }

    public function test_the_whole_sale_goes_through_the_form(): void
    {
        $this->fabriquer(3);
        $rev = $this->revendeur('biz-rev');
        $this->service()->allocate($rev, $this->lot(), 1, 3);
        $this->connecte('biz-rev');
        $stand = Stand::first();

        $this->post(route('tagtoa.reseller.sell'), [
            'public_id' => strtolower($stand->public_id), // saisi à la main
            'buyer_phone' => '50931234567',
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(StandState::SOLD, $stand->refresh()->physical_state);
    }

    public function test_the_founder_creates_a_reseller_and_allocates(): void
    {
        $this->fabriquer(20);
        $this->connecte('biz-fondateur');

        $this->post(route('tagtoa.superadmin.resellers.store'), [
            'business_id' => 'biz-nouveau', 'name' => 'Chez Marie',
            'zone' => 'Les Cayes', 'commission_pct' => 12,
        ])->assertRedirect()->assertSessionHas('success');

        $rev = Reseller::firstOrFail();

        $this->post(route('tagtoa.superadmin.resellers.allocate', $rev->id), [
            'batch_id' => $this->lot(), 'from' => 1, 'to' => 10,
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(10, Stand::heldBy($rev->id)->count());
    }

    public function test_a_business_cannot_be_two_resellers(): void
    {
        // Sinon son stock se dédoublerait, et `forBusiness()` en choisirait un
        // au hasard.
        $this->connecte('biz-fondateur');
        $this->revendeur('biz-rev');

        $this->post(route('tagtoa.superadmin.resellers.store'),
            ['business_id' => 'biz-rev', 'name' => 'Doublon'])
            ->assertSessionHasErrors('business_id');
    }

    public function test_the_console_asks_the_database_for_named_columns(): void
    {
        // DÉFENSE EN PROFONDEUR, et elle mérite son propre test.
        //
        // Les tests ci-dessus vérifient que le HTML rendu ne contient aucun
        // secret — c'est la propriété qui compte, et ils la tiennent même si
        // quelqu'un retirait le `$hidden` du modèle (vérifié en le retirant).
        //
        // Mais ils ne diraient rien si la requête RAMENAIT le haché sans
        // l'afficher : il suffirait alors d'un `@json($stands)` ajouté un jour
        // pour le faire sortir. On exige donc que les colonnes soient nommées,
        // pour que le secret ne quitte jamais la base.
        $src = (string) file_get_contents(__DIR__.'/../../app/Http/Controllers/Stand/ResellerController.php');

        $this->assertStringNotContainsString('paginate(50)', $src,
            'La console doit demander des colonnes NOMMÉES, pas tout ramener.');
        $this->assertStringContainsString("'public_id', 'serial', 'physical_state'", $src);

        // Les COMMENTAIRES parlent du secret — c'est leur travail. C'est le
        // CODE qui ne doit pas le nommer. On les retire donc avant de chercher,
        // sinon ce test échouerait sur sa propre explication.
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~//[^\n]*~'], '', $src);

        $this->assertStringNotContainsString('secret', $code,
            'Aucune requête de la console ne doit nommer le secret.');
    }

    public function test_the_founder_s_screens_really_sit_behind_the_role(): void
    {
        // Le harnais remplace `role` par un passe-plat : les tests ci-dessus
        // passeraient même si le réseau de distribution était grand ouvert. On
        // vérifie donc la DÉCLARATION, faute de pouvoir vérifier l'exécution.
        $routes = (string) file_get_contents(__DIR__.'/../../routes/web.php');
        $bloc = strstr($routes, 'role:super_admin');

        $this->assertNotFalse($bloc);
        foreach (['superadmin.resellers', 'superadmin.resellers.store',
                  'superadmin.resellers.update', 'superadmin.resellers.allocate'] as $nom) {
            $this->assertStringContainsString($nom, $bloc, "« $nom » hors du groupe super-admin.");
        }
    }
}
