<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA TENANT — inventaire de l'isolation par commerce (CS-11a, lecture seule)
|--------------------------------------------------------------------------
| Preuves avant d'oser fermer la portée (CS-11b) : quels modèles ont un
| `tenant_id` sans l'isolation, combien de lignes/comptes sont sans commerce,
| et ce que fait EXACTEMENT la portée aujourd'hui. Rien n'est modifié.
*/

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Tenancy\TenantAudit;
use Modules\Tagtoa\App\Support\Tenant;
use Modules\Tagtoa\Tests\TestCase;

class TenantAuditUnscopedFixture extends Model
{
    protected $table = 'tenant_audit_fixture';
}

class TenantAuditScopedFixture extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_audit_fixture';
}

class TenantAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenant::flush();
    }

    protected function tearDown(): void
    {
        Tenant::flush();
        parent::tearDown();
    }

    public function test_every_model_with_a_tenant_column_is_isolated_or_documented(): void
    {
        $inv = (new TenantAudit)->inventory();
        $bad = array_keys(array_diff_key($inv['unscoped_with_column'], TenantAudit::DOCUMENTED_EXCEPTIONS));

        $this->assertSame([], $bad, "Modèles avec tenant_id mais sans BelongsToTenant (ni exception documentée) :\n".implode("\n", $bad));
        $this->assertSame([], array_keys($inv['scoped_without_column']), 'Trait posé sur une table sans tenant_id.');
        $this->assertGreaterThanOrEqual(40, count($inv['scoped']));
    }

    public function test_documented_exceptions_are_real_and_explained(): void
    {
        $inv = (new TenantAudit)->inventory();
        foreach (TenantAudit::DOCUMENTED_EXCEPTIONS as $class => $why) {
            $this->assertArrayHasKey($class, $inv['unscoped_with_column'], "$class est documenté mais n'est plus une exception : retirez-le.");
            $this->assertGreaterThan(30, strlen($why), "$class : l'exception doit dire POURQUOI.");
        }
    }

    public function test_audit_detects_a_new_unisolated_model(): void
    {
        Schema::create('tenant_audit_fixture', function ($t) { $t->id(); $t->string('tenant_id')->nullable(); });

        $inv = (new TenantAudit)->inventory([TenantAuditUnscopedFixture::class, TenantAuditScopedFixture::class]);

        $this->assertSame([TenantAuditUnscopedFixture::class], array_keys($inv['unscoped_with_column']));
        $this->assertSame([TenantAuditScopedFixture::class], array_keys($inv['scoped']));
    }

    public function test_every_isolated_model_filters_by_the_current_business(): void
    {
        $this->be(new GenericUser(['id' => 1, 'tenant_id' => 't-audit']));
        Tenant::flush();

        foreach ((new TenantAudit)->inventory()['scoped'] as $class => $table) {
            $q = $class::query();
            $col = $q->getQuery()->getGrammar()->wrap("$table.tenant_id");
            $this->assertStringContainsString($col, $q->toSql(), "$class ne filtre pas par commerce.");
            $this->assertContains((string) Tenant::id(), array_map('strval', $q->getBindings()), $class);
        }
    }

    /** Comportement ACTUEL, épinglé : contexte public = pas de filtre (voulu). */
    public function test_public_context_is_deliberately_unfiltered(): void
    {
        Tenant::flush();
        $this->assertNull(Tenant::id());

        foreach ((new TenantAudit)->inventory()['scoped'] as $class => $table) {
            $q = $class::query();
            $this->assertStringNotContainsString($q->getQuery()->getGrammar()->wrap("$table.tenant_id"), $q->toSql(), $class);
        }
    }

    /**
     * ÉCART CONNU (à trancher en CS-11b) : un utilisateur connecté SANS tenant_id
     * obtient Tenant::id() === null, donc aucune portée : il voit TOUS les commerces.
     * Ce test décrit l'état actuel pour que le corriger soit un changement visible.
     */
    public function test_KNOWN_GAP_authenticated_user_without_tenant_is_unfiltered(): void
    {
        $this->be(new GenericUser(['id' => 9, 'tenant_id' => null]));
        Tenant::flush();

        $this->assertNull(Tenant::id());
        $this->assertStringNotContainsString('tenant_id', \Modules\Tagtoa\App\Models\Menu\Menu::query()->toSql());
    }

    public function test_orphan_rows_are_counted_per_table(): void
    {
        Schema::create('tenant_audit_fixture', function ($t) { $t->id(); $t->string('tenant_id')->nullable(); });
        DB::table('tenant_audit_fixture')->insert([['tenant_id' => null], ['tenant_id' => ''], ['tenant_id' => 'a']]);

        $this->assertSame(['tenant_audit_fixture' => 2], (new TenantAudit)->orphanRows(['tenant_audit_fixture', 'tenant_audit_fixture']));
        $this->assertSame(['table_inexistante' => -1], (new TenantAudit)->orphanRows(['table_inexistante']));
    }

    public function test_users_without_tenant_are_counted_or_null_when_not_applicable(): void
    {
        Schema::dropIfExists('users');
        $this->assertNull((new TenantAudit)->usersWithoutTenant());

        Schema::create('users', function ($t) { $t->id(); $t->string('tenant_id')->nullable(); });
        DB::table('users')->insert([['tenant_id' => null], ['tenant_id' => ''], ['tenant_id' => 'x']]);
        $this->assertSame(['total' => 3, 'without_tenant' => 2], (new TenantAudit)->usersWithoutTenant());
    }

    public function test_command_is_read_only_and_strict_mode_gates_unisolated_models(): void
    {
        Schema::create('tenant_audit_fixture', function ($t) { $t->id(); $t->string('tenant_id')->nullable(); });
        DB::table('tenant_audit_fixture')->insert(['tenant_id' => null]);
        $before = DB::table('tenant_audit_fixture')->count();

        $this->artisan('tagtoa:tenant-audit', ['--strict' => true])
            ->expectsOutputToContain('Modèles isolés')
            ->assertExitCode(0);
        $this->assertSame($before, DB::table('tenant_audit_fixture')->count());
    }
}
