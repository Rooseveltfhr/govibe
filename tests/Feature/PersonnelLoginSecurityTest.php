<?php

namespace Tests\Feature;

use App\Models\ConnexionAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| /admin/login et /erp/login — même compte, même garde
|--------------------------------------------------------------------------
| Les deux écrans n'avaient ni limite de tentatives ni journal — le seul
| compte qui ouvre l'Academy ET l'ERP pouvait être attaqué par force brute
| sans qu'aucune trace n'en reste. Mirroré sur le même schéma que
| Portail\AuthController (voir PortailClientTest), avec une différence
| voulue : la clé de limitation est PARTAGÉE entre les deux écrans, puisque
| c'est le même compte des deux côtés.
*/
class PersonnelLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'Roosevelt', 'email' => 'roosevelt@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => true,
        ], $extra));
    }

    protected function tearDown(): void
    {
        RateLimiter::clear('personnel:roosevelt@govibeht.com|127.0.0.1');
        parent::tearDown();
    }

    // ── Connexion réussie ─────────────────────────────────

    public function test_connexion_admin_reussie_et_journalisee(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => 'roosevelt@govibeht.com', 'password' => 'MotDePasse2026',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $trace = ConnexionAdmin::sole();
        $this->assertTrue($trace->reussie);
        $this->assertSame('admin', $trace->surface);
        $this->assertSame($admin->id, $trace->user_id);
    }

    public function test_connexion_erp_reussie_et_journalisee(): void
    {
        $admin = $this->admin();

        $this->post('/erp/login', [
            'email' => 'roosevelt@govibeht.com', 'password' => 'MotDePasse2026',
        ])->assertRedirect(route('erp.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $trace = ConnexionAdmin::sole();
        $this->assertTrue($trace->reussie);
        $this->assertSame('erp', $trace->surface);
    }

    // ── Anti-énumération ──────────────────────────────────

    public function test_le_message_derreur_ne_revele_pas_si_le_compte_existe(): void
    {
        $this->admin();

        // Message strictement identique dans les deux cas : sinon on peut
        // découvrir quels emails ont un compte administrateur.
        $this->post('/admin/login', ['email' => 'personne@exemple.ht', 'password' => 'MotDePasse2026'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'FauxMotDePasse1'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->assertGuest();
    }

    public function test_les_echecs_sont_traces_avec_leur_motif(): void
    {
        $this->admin();

        $this->post('/admin/login', ['email' => 'personne@exemple.ht', 'password' => 'MotDePasse2026']);
        $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'FauxMotDePasse1']);

        $motifs = ConnexionAdmin::pluck('motif')->all();
        $this->assertContains('inconnu', $motifs);
        $this->assertContains('mot_de_passe', $motifs);
    }

    public function test_un_compte_non_administrateur_est_refuse_et_trace(): void
    {
        $employe = $this->admin(['email' => 'stagiaire@govibeht.com', 'is_admin' => false]);

        $this->post('/admin/login', ['email' => 'stagiaire@govibeht.com', 'password' => 'MotDePasse2026'])
            ->assertSessionHasErrors(['email' => 'Accès refusé. Vous n\'êtes pas administrateur.']);

        $this->assertGuest();
        $this->assertSame('pas_admin', ConnexionAdmin::sole()->motif);
    }

    // ── Force brute ───────────────────────────────────────

    public function test_la_force_brute_est_bloquee(): void
    {
        $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux'.$i.'aaaa']);
        }

        // Au sixième essai, même le bon mot de passe est refusé.
        $this->post('/admin/login', [
            'email' => 'roosevelt@govibeht.com', 'password' => 'MotDePasse2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(
            ConnexionAdmin::where('motif', 'trop_essais')->exists(),
            'le blocage doit laisser une trace exploitable'
        );
    }

    public function test_la_limite_est_partagee_entre_admin_et_erp(): void
    {
        $this->admin();

        // Alterner entre les deux portes ne doit pas doubler le débit
        // autorisé : c'est le même compte des deux côtés.
        $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux0aaaa']);
        $this->post('/erp/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux1aaaa']);
        $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux2aaaa']);
        $this->post('/erp/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux3aaaa']);
        $this->post('/admin/login', ['email' => 'roosevelt@govibeht.com', 'password' => 'Faux4aaaa']);

        // Le bon mot de passe, tenté sur l'autre porte, est déjà bloqué.
        $this->post('/erp/login', [
            'email' => 'roosevelt@govibeht.com', 'password' => 'MotDePasse2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
