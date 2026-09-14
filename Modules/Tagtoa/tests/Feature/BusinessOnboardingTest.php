<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Tagtoa\App\Http\Controllers\Business\BusinessController;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Déclarer son commerce — le vrai chemin, à travers le contrôleur.
 *
 * Le patron arrive sur TAGTOA, donne le nom de son commerce, son métier, ce
 * qu'il vend, ses catégories et sa devise. Ensuite tout s'adapte à lui.
 */
class BusinessOnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function connecte(string $accountId): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => $accountId]));
        Tenant::flush();
    }

    private function poste(array $champs, array $fichiers = []): Business
    {
        $request = Request::create('/tagtoa/business', 'POST', $champs, [], $fichiers);
        $request->setLaravelSession(app('session.store'));

        app(BusinessController::class)->store($request);

        // Retrouvé par son NOM : deux commerces créés dans la même seconde
        // partagent leur horodatage, et « le dernier créé » désignerait alors
        // l'un ou l'autre au hasard.
        return Business::where('name', $champs['name'])->firstOrFail();
    }

    public function test_a_merchant_declares_his_business_in_one_go(): void
    {
        $this->connecte('compte-1');

        $b = $this->poste([
            'name' => 'Boulangerie Delmas 31',
            'type' => 'cafe',
            'categories' => ['Pains', 'Gâteaux'],
            'sells_products' => '1',
            'sells_services' => '0',
            'address' => 'Delmas 31, Port-au-Prince',
            'phone' => '+509 3712 4408',
            'currency' => 'HTG',
        ]);

        $this->assertSame('Boulangerie Delmas 31', $b->name);
        $this->assertSame('cafe', $b->type);
        $this->assertSame(['Pains', 'Gâteaux'], $b->categories);
        $this->assertTrue($b->sells_products);
        $this->assertFalse($b->sells_services);
        $this->assertSame('HTG', $b->currency);

        // Et il travaille dessus immédiatement, sans avoir à basculer.
        $this->assertSame($b->id, Tenant::id());
    }

    public function test_a_hotel_may_sell_both_nights_and_drinks(): void
    {
        $this->connecte('compte-1');

        $b = $this->poste([
            'name' => 'Hôtel Cap', 'type' => 'hotel', 'currency' => 'USD',
            'sells_products' => '1', 'sells_services' => '1',
        ]);

        $this->assertTrue($b->sells_products);
        $this->assertTrue($b->sells_services);
        $this->assertSame('Produits et services', $b->sells_label);
    }

    public function test_an_invented_trade_is_refused(): void
    {
        // Un type hors catalogue priverait le marchand des champs de son métier
        // sans qu'il comprenne pourquoi.
        $this->connecte('compte-1');

        $this->expectException(ValidationException::class);
        $this->poste(['name' => 'X', 'type' => 'spatioport', 'currency' => 'HTG']);
    }

    public function test_a_business_without_a_name_is_refused(): void
    {
        $this->connecte('compte-1');

        $this->expectException(ValidationException::class);
        $this->poste(['name' => '', 'type' => 'cafe', 'currency' => 'HTG']);
    }

    public function test_the_logo_is_kept_when_the_merchant_uploads_one(): void
    {
        Storage::fake('public');
        $this->connecte('compte-1');

        $b = $this->poste(
            ['name' => 'Avec logo', 'type' => 'cafe', 'currency' => 'HTG'],
            ['logo' => UploadedFile::fake()->image('enseigne.png')]
        );

        $this->assertNotNull($b->logo_path);
        Storage::disk('public')->assertExists($b->logo_path);
    }

    public function test_correcting_an_address_never_erases_the_shop_sign(): void
    {
        Storage::fake('public');
        $this->connecte('compte-1');

        $b = $this->poste(
            ['name' => 'Boulangerie', 'type' => 'cafe', 'currency' => 'HTG'],
            ['logo' => UploadedFile::fake()->image('enseigne.png')]
        );
        $logo = $b->logo_path;

        // Modification SANS renvoyer le logo.
        $request = Request::create('/x', 'PUT', [
            'name' => 'Boulangerie', 'type' => 'cafe', 'currency' => 'HTG',
            'address' => 'Nouvelle adresse',
        ]);
        $request->setLaravelSession(app('session.store'));
        app(BusinessController::class)->update($request, $b->id);

        $this->assertSame($logo, $b->fresh()->logo_path, 'Le logo doit survivre à une correction d\'adresse.');
        $this->assertSame('Nouvelle adresse', $b->fresh()->address);
    }

    public function test_a_merchant_never_edits_a_business_that_is_not_his(): void
    {
        Business::create(['id' => 'chez-le-voisin', 'account_id' => 'compte-2', 'name' => 'Voisin']);
        $this->connecte('compte-1');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        app(BusinessController::class)->edit('chez-le-voisin');
    }

    public function test_a_second_business_starts_empty_and_leaves_the_first_alone(): void
    {
        $this->connecte('compte-1');

        $boulangerie = $this->poste(['name' => 'Boulangerie', 'type' => 'cafe', 'currency' => 'HTG']);
        Menu::create(['name' => 'Pains', 'alias' => 'pains', 'currency' => 'HTG']);

        $bar = $this->poste(['name' => 'Bar Lakay', 'type' => 'bar', 'currency' => 'HTG']);

        // Le nouveau commerce n'hérite de rien.
        $this->assertSame($bar->id, Tenant::id());
        $this->assertSame([], Menu::pluck('name')->all());

        // Et le premier n'a pas bougé.
        Tenant::switchTo($boulangerie->id);
        $this->assertSame(['Pains'], Menu::pluck('name')->all());
    }

    public function test_a_currency_we_do_not_list_is_accepted(): void
    {
        $this->connecte('compte-1');

        $b = $this->poste(['name' => 'Diaspora', 'type' => 'other', 'currency' => 'brl']);

        $this->assertSame('BRL', $b->currency);
    }
}
