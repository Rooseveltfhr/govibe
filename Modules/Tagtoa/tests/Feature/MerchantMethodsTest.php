<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Pay\PaymentMethod;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;
use Modules\Tagtoa\App\Models\Pay\PaymentProof;
use Modules\Tagtoa\App\Services\Pay\MerchantMethods;
use Modules\Tagtoa\Tests\TestCase;

/**
 * TAGTOA Pay — les moyens de paiement appartiennent au MARCHAND, pas au lien.
 *
 * Le marchand les configure une seule fois : tous ses liens (actuels et futurs)
 * les proposent, sans jamais ressaisir un numéro de compte. Ces tests vérifient
 * les trois promesses qui comptent : configuration unique, isolation entre
 * marchands, et migration des données existantes SANS perdre d'historique.
 */
class MerchantMethodsTest extends TestCase
{
    use RefreshDatabase;

    private function page(string $tenantId, array $attrs = []): PaymentPage
    {
        return PaymentPage::create(array_merge([
            'tenant_id'        => $tenantId,
            'title'            => 'Lien',
            'type'             => PaymentPage::TYPE_INVOICE,
            'alias'            => 'p-'.uniqid(),
            'default_currency' => 'HTG',
            'is_active'        => true,
        ], $attrs));
    }

    public function test_methods_configured_once_apply_to_every_link_of_the_merchant(): void
    {
        $svc = app(MerchantMethods::class);

        // Le marchand configure MonCash une seule fois.
        $svc->library('t-1')->methods()->create([
            'tenant_id'      => 't-1',
            'type'           => 'moncash',
            'account_holder' => 'Boulangerie Delmas 31',
            'account_number' => '+509 3712 4408',
            'requires_proof' => true,
            'is_active'      => true,
        ]);

        // Deux liens créés APRÈS, sans aucune saisie de coordonnées.
        $this->page('t-1', ['title' => 'Gâteau anniversaire', 'amount' => 4500]);
        $this->page('t-1', ['title' => 'Soutien boulangerie', 'type' => PaymentPage::TYPE_DONATION]);

        $active = $svc->active('t-1');

        $this->assertCount(1, $active);
        $this->assertSame('+509 3712 4408', $active->first()->account_number);
    }

    public function test_the_library_page_is_never_public(): void
    {
        $library = app(MerchantMethods::class)->library('t-1');

        $this->assertTrue($library->is_library);
        $this->assertFalse($library->is_active, 'La bibliothèque ne doit jamais être servie publiquement.');

        // Une seule bibliothèque par marchand, même appelée plusieurs fois.
        $again = app(MerchantMethods::class)->library('t-1');
        $this->assertSame($library->id, $again->id);
        $this->assertSame(1, PaymentPage::where('is_library', true)->where('tenant_id', 't-1')->count());
    }

    public function test_one_merchant_never_sees_another_merchants_methods(): void
    {
        $svc = app(MerchantMethods::class);

        $svc->library('t-1')->methods()->create([
            'tenant_id' => 't-1', 'type' => 'moncash',
            'account_number' => '+509 3712 4408', 'is_active' => true,
        ]);
        $svc->library('t-2')->methods()->create([
            'tenant_id' => 't-2', 'type' => 'sogebank',
            'account_number' => '0311 4402 9917', 'is_active' => true,
        ]);

        $this->assertSame(['moncash'], $svc->active('t-1')->pluck('type')->all());
        $this->assertSame(['sogebank'], $svc->active('t-2')->pluck('type')->all());

        // Une bibliothèque distincte par marchand : aucun partage de ligne.
        $this->assertNotSame($svc->library('t-1')->id, $svc->library('t-2')->id);
    }

    public function test_inactive_methods_are_not_offered(): void
    {
        $svc = app(MerchantMethods::class);
        $library = $svc->library('t-1');

        $library->methods()->create(['tenant_id' => 't-1', 'type' => 'moncash', 'is_active' => true, 'sort' => 1]);
        $library->methods()->create(['tenant_id' => 't-1', 'type' => 'zelle', 'is_active' => false, 'sort' => 0]);

        $this->assertSame(['moncash'], $svc->active('t-1')->pluck('type')->all());
        // …mais elles restent visibles dans l'écran de configuration.
        $this->assertSame(['moncash', 'zelle'], $svc->allByType('t-1')->keys()->sort()->values()->all());
    }

    /**
     * Le point critique de la reprise de données : les preuves de paiement sont
     * supprimées en cascade depuis les méthodes. La consolidation doit donc
     * RATTACHER les lignes existantes, jamais les supprimer.
     */
    public function test_consolidation_migration_keeps_existing_payment_history(): void
    {
        // Données « d'avant » : deux liens du même marchand, chacun avec son
        // propre MonCash ressaisi à la main, et une preuve reçue sur chacun.
        $old1 = $this->page('t-1', ['title' => 'Facture 001']);
        $old2 = $this->page('t-1', ['title' => 'Facture 002']);

        $m1 = PaymentMethod::create([
            'payment_page_id' => $old1->id, 'type' => 'moncash',
            'account_number' => '+509 3712 4408', 'is_active' => true,
        ]);
        $m2 = PaymentMethod::create([
            'payment_page_id' => $old2->id, 'type' => 'moncash',
            'account_number' => '+509 3712 4408', 'is_active' => true,
        ]);

        foreach ([[$old1, $m1, 4500], [$old2, $m2, 12000]] as [$page, $method, $amount]) {
            PaymentProof::create([
                'payment_page_id'   => $page->id,
                'payment_method_id' => $method->id,
                'payer_name'        => 'Client',
                'amount'            => $amount,
                'currency'          => 'HTG',
                'status'            => PaymentProof::STATUS_APPROVED,
            ]);
        }

        // On rejoue la consolidation sur ces données existantes.
        $this->runConsolidationMigration();

        // Aucune preuve perdue : c'est l'historique financier du marchand.
        $this->assertSame(2, PaymentProof::count());
        $this->assertSame(2, PaymentMethod::count());

        // La ligne retenue est désormais portée par la bibliothèque du marchand.
        $libraryId = app(MerchantMethods::class)->library('t-1')->id;
        $this->assertSame($libraryId, (int) $m1->fresh()->payment_page_id);

        // Le doublon survit (ses preuves y sont rattachées) mais n'est plus proposé.
        $this->assertFalse($m2->fresh()->is_active);
        $this->assertSame(['moncash'], app(MerchantMethods::class)->active('t-1')->pluck('type')->all());
    }

    public function test_consolidation_migration_does_not_mix_merchants(): void
    {
        $a = $this->page('t-1');
        $b = $this->page('t-2');

        PaymentMethod::create(['payment_page_id' => $a->id, 'type' => 'moncash', 'account_number' => 'A', 'is_active' => true]);
        PaymentMethod::create(['payment_page_id' => $b->id, 'type' => 'moncash', 'account_number' => 'B', 'is_active' => true]);

        $this->runConsolidationMigration();

        $svc = app(MerchantMethods::class);
        $this->assertSame('A', $svc->active('t-1')->first()->account_number);
        $this->assertSame('B', $svc->active('t-2')->first()->account_number);
    }

    /**
     * Rejoue la migration de consolidation sur les données du test. Les
     * migrations sont déjà appliquées (schéma), mais leur logique de reprise
     * n'a rien eu à traiter : on la relance ici sur un jeu réaliste.
     */
    private function runConsolidationMigration(): void
    {
        $file = __DIR__.'/../../Database/migrations/2026_09_02_000113_consolidate_payment_methods_per_merchant.php';
        $this->assertFileExists($file);
        (require $file)->up();

        // Sanity : la migration a bien renseigné tenant_id sur les méthodes.
        $this->assertSame(0, DB::table('tagtoa_payment_methods')->whereNull('tenant_id')->count());
    }
}
