<?php

namespace Modules\Tagtoa\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Staff\StaffService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;
use Modules\Tagtoa\Tests\TestCase;

/**
 * Les employés du commerce : fiche, code d'accès, isolation.
 *
 * Le patron enregistre Jacqueline sur la caisse 1 et Pierre sur la caisse 2.
 * Leur code à 4–6 chiffres leur sert de mot de passe et de confirmation pour
 * les actions sérieuses.
 */
class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function service(): StaffService
    {
        return app(StaffService::class);
    }

    private function embauche(string $tenantId, string $nom, string $pin, string $role = StaffAccess::ROLE_CASHIER, ?Terminal $caisse = null): Staff
    {
        return $this->service()->save($tenantId, [
            'name' => $nom, 'pin' => $pin, 'role' => $role,
            'terminal_id' => $caisse?->id, 'email' => strtolower($nom).'@boutik.ht',
        ]);
    }

    public function test_the_access_code_is_never_stored_in_clear(): void
    {
        $jacqueline = $this->embauche('t-1', 'Jacqueline', '4821');

        $this->assertNotSame('4821', $jacqueline->pin_hash);
        $this->assertStringNotContainsString('4821', $jacqueline->pin_hash);
        // Et il ne doit pas pouvoir fuir dans une réponse JSON ou un journal.
        $this->assertArrayNotHasKey('pin_hash', $jacqueline->toArray());
        $this->assertStringNotContainsString('4821', json_encode($jacqueline));
    }

    public function test_an_employee_is_recognised_by_her_code(): void
    {
        $this->embauche('t-1', 'Jacqueline', '4821');

        $trouvee = $this->service()->authenticate('t-1', '4821');

        $this->assertNotNull($trouvee);
        $this->assertSame('Jacqueline', $trouvee->name);
        $this->assertNotNull($trouvee->last_login_at, 'La dernière connexion doit être notée.');
    }

    public function test_a_wrong_or_malformed_code_opens_nothing(): void
    {
        $this->embauche('t-1', 'Jacqueline', '4821');

        foreach (['4822', '', '48', 'abcd', '482100000'] as $mauvais) {
            $this->assertNull($this->service()->authenticate('t-1', $mauvais),
                "Le code « $mauvais » ne doit rien ouvrir.");
        }
    }

    public function test_the_code_of_a_neighbouring_shop_opens_nothing_here(): void
    {
        // Deux commerces peuvent très bien avoir chacun un employé en « 1234 ».
        $this->embauche('t-1', 'Jacqueline', '1234');
        $this->embauche('t-2', 'Pierre', '1234');

        $chezUn = $this->service()->authenticate('t-1', '1234');

        $this->assertSame('Jacqueline', $chezUn->name);
        $this->assertSame('t-1', $chezUn->tenant_id);
    }

    public function test_two_employees_of_the_same_shop_may_share_a_code(): void
    {
        // On ne peut pas l'empêcher : le code est court et les gens choisissent
        // les mêmes. Ce qui compte, c'est que la reconnaissance reste correcte
        // et qu'aucune erreur ne soit levée.
        $this->embauche('t-1', 'Jacqueline', '1234');
        $this->embauche('t-1', 'Pierre', '1234');

        $reconnu = $this->service()->authenticate('t-1', '1234');

        $this->assertNotNull($reconnu);
        $this->assertContains($reconnu->name, ['Jacqueline', 'Pierre']);
    }

    public function test_deactivating_someone_closes_the_till_at_once(): void
    {
        $jacqueline = $this->embauche('t-1', 'Jacqueline', '4821');

        $this->service()->save('t-1', ['name' => 'Jacqueline', 'role' => StaffAccess::ROLE_CASHIER,
            'is_active' => false], $jacqueline);

        $this->assertNull($this->service()->authenticate('t-1', '4821'));
        // …et plus aucun droit, même ceux de son rôle.
        $this->assertFalse($jacqueline->fresh()->can('sell'));
    }

    public function test_editing_a_record_does_not_force_a_new_code(): void
    {
        // Corriger le téléphone de Jacqueline ne doit pas lui changer son code.
        $jacqueline = $this->embauche('t-1', 'Jacqueline', '4821');
        $avant = $jacqueline->pin_hash;

        $this->service()->save('t-1', [
            'name' => 'Jacqueline Pierre-Louis', 'phone' => '+509 3712 4408',
            'role' => StaffAccess::ROLE_CASHIER,
        ], $jacqueline);

        $this->assertSame($avant, $jacqueline->fresh()->pin_hash);
        $this->assertNotNull($this->service()->authenticate('t-1', '4821'));
    }

    public function test_an_unknown_role_falls_back_to_the_least_open_one(): void
    {
        $staff = $this->service()->save('t-1', ['name' => 'X', 'pin' => '1111', 'role' => 'super-patron']);

        $this->assertSame(StaffAccess::ROLE_CASHIER, $staff->role);
        $this->assertFalse($staff->can('catalog.delete'));
    }

    public function test_a_serious_action_asks_this_persons_code_not_anyones(): void
    {
        $jacqueline = $this->embauche('t-1', 'Jacqueline', '4821');
        $pierre     = $this->embauche('t-1', 'Pierre', '9002');

        $this->assertTrue($this->service()->confirms($jacqueline, '4821'));
        // Le code de Pierre ne confirme pas une action de Jacqueline.
        $this->assertFalse($this->service()->confirms($jacqueline, '9002'));
        $this->assertTrue($this->service()->confirms($pierre, '9002'));
    }

    public function test_removing_a_till_keeps_the_employee(): void
    {
        // Une caisse qui disparaît ne doit pas effacer la fiche de l'employé —
        // ni son historique, ni son code.
        $caisse = Terminal::create(['tenant_id' => 't-1', 'name' => 'Caisse 1', 'currency' => 'HTG', 'is_active' => true]);
        $jacqueline = $this->embauche('t-1', 'Jacqueline', '4821', StaffAccess::ROLE_CASHIER, $caisse);

        $caisse->delete();

        $fiche = $jacqueline->fresh();
        $this->assertNotNull($fiche, 'L\'employée ne doit pas partir avec la caisse.');
        $this->assertNull($fiche->terminal_id);
        $this->assertNotNull($this->service()->authenticate('t-1', '4821'));
    }

    public function test_the_roster_belongs_to_the_shop_that_asks_for_it(): void
    {
        $this->embauche('t-1', 'Jacqueline', '4821');
        $this->embauche('t-1', 'Pierre', '9002');
        $this->embauche('t-2', 'Marie', '7777');

        $this->assertSame(['Jacqueline', 'Pierre'], $this->service()->roster('t-1')->pluck('name')->all());
        $this->assertSame(['Marie'], $this->service()->roster('t-2')->pluck('name')->all());
    }

    public function test_initials_are_shown_even_for_an_odd_name(): void
    {
        $this->assertSame('JP', $this->embauche('t-1', 'Jacqueline Pierre-Louis', '1000')->initials);
        $this->assertSame('M',  $this->embauche('t-1', 'Marie', '1001')->initials);
        $this->assertSame('?',  $this->embauche('t-1', '  ', '1002')->initials);
    }
}
