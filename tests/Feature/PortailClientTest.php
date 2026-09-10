<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ComptePortail;
use App\Models\ConnexionPortail;
use App\Models\DemandeAgentIa;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PortailClientTest extends TestCase
{
    use RefreshDatabase;

    private function client(array $extra = []): Client
    {
        return Client::create(array_merge([
            'reference_number' => 'CL-TEST-0001',
            'name' => 'Boulangerie Sainte-Anne',
            'type' => 'company',
            'email' => 'contact@sainteanne.ht',
            'phone' => '+509 3712 4455',
        ], $extra));
    }

    private function compte(Client $client, array $extra = []): ComptePortail
    {
        return ComptePortail::create(array_merge([
            'client_id' => $client->id,
            'nom' => 'Nadège Pierre',
            'email' => 'contact@sainteanne.ht',
            'telephone' => '+509 3712 4455',
            'password' => 'MotDePasse2026',
            'email_verifie_le' => now(),
            'actif' => true,
        ], $extra));
    }

    private function inscription(array $extra = []): array
    {
        return array_merge([
            'nom' => 'Nadège Pierre',
            'entreprise' => 'Boulangerie Sainte-Anne',
            'email' => 'contact@sainteanne.ht',
            'telephone' => '+509 3712 4455',
            'password' => 'MotDePasse2026',
            'password_confirmation' => 'MotDePasse2026',
        ], $extra);
    }

    // ── Séparation des surfaces ──────────────────────────

    public function test_le_garde_client_est_distinct_du_garde_personnel(): void
    {
        $this->assertArrayHasKey('client', config('auth.guards'));
        $this->assertSame('comptes_portail', config('auth.guards.client.provider'));
        $this->assertSame('users', config('auth.guards.web.provider'));
    }

    public function test_un_client_connecte_natteint_pas_lerp(): void
    {
        $compte = $this->compte($this->client());

        // Le cœur de la décision : deux surfaces d'authentification, pas deux rôles.
        $this->actingAs($compte, 'client')->get('/erp')->assertStatus(302);
        $this->actingAs($compte, 'client')->get('/erp/fiches')->assertStatus(302);
        $this->actingAs($compte, 'client')->get('/erp/paiements')->assertStatus(302);
    }

    public function test_un_employe_natteint_pas_le_portail_client(): void
    {
        $employe = User::create([
            'name' => 'Agent GOVIBE', 'email' => 'agent@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => true,
        ]);

        $this->actingAs($employe, 'web')->get('/portail')
            ->assertRedirect(route('portail.connexion'));
    }

    public function test_le_portail_exige_une_connexion(): void
    {
        foreach (['/portail', '/portail/services', '/portail/factures'] as $url) {
            $this->get($url)->assertRedirect(route('portail.connexion'));
        }
    }

    // ── Création de compte ───────────────────────────────

    public function test_creer_un_compte_reutilise_le_client_crm_existant(): void
    {
        $client = $this->client();

        $this->post('/portail/inscription', $this->inscription())
            ->assertRedirect(route('portail.verification.attente'));

        // Pas de doublon : l'équipe suit déjà ce client.
        $this->assertSame(1, Client::count());
        $this->assertSame($client->id, ComptePortail::sole()->client_id);
    }

    public function test_creer_un_compte_sans_client_existant_en_cree_un(): void
    {
        $this->post('/portail/inscription', $this->inscription(['email' => 'nouveau@exemple.ht']));

        $this->assertSame(1, Client::count());
        $this->assertSame('Boulangerie Sainte-Anne', Client::sole()->name);
    }

    public function test_le_mot_de_passe_est_hache(): void
    {
        $this->post('/portail/inscription', $this->inscription());
        $compte = ComptePortail::sole();

        $this->assertNotSame('MotDePasse2026', $compte->password);
        $this->assertTrue(Hash::check('MotDePasse2026', $compte->password));
    }

    public function test_refuse_un_mot_de_passe_faible_ou_non_confirme(): void
    {
        $this->post('/portail/inscription', $this->inscription(['password' => 'abc', 'password_confirmation' => 'abc']))
            ->assertSessionHasErrors('password');

        $this->post('/portail/inscription', $this->inscription(['password_confirmation' => 'AutreChose2026']))
            ->assertSessionHasErrors('password');

        $this->assertSame(0, ComptePortail::count());
    }

    public function test_refuse_une_adresse_deja_inscrite(): void
    {
        $this->compte($this->client());

        $this->post('/portail/inscription', $this->inscription())
            ->assertSessionHasErrors('email');

        $this->assertSame(1, ComptePortail::count());
    }

    // ── Vérification avant tout accès aux données ────────

    public function test_un_compte_non_verifie_ne_voit_aucune_donnee(): void
    {
        $compte = $this->compte($this->client(), ['email_verifie_le' => null]);

        // Sans ce verrou, s'inscrire avec l'email d'un tiers ouvrirait ses factures.
        $this->actingAs($compte, 'client')->get('/portail')
            ->assertRedirect(route('portail.verification.attente'));
        $this->actingAs($compte, 'client')->get('/portail/factures')
            ->assertRedirect(route('portail.verification.attente'));
    }

    public function test_la_verification_rattache_lhistorique(): void
    {
        $client = $this->client();
        $compte = $this->compte($client, [
            'email_verifie_le' => null,
            'jeton_verification' => 'jeton-de-test-1234567890',
        ]);

        DemandeAgentIa::create([
            'reference' => 'AI-20260101-AAAA', 'agent_nom' => 'Agent IA — Restaurant',
            'entreprise' => 'Boulangerie Sainte-Anne', 'responsable' => 'Nadège Pierre',
            'email' => 'CONTACT@SainteAnne.ht', 'telephone' => '+509 3712 4455',
            'statut' => 'nouvelle', 'statut_paiement' => 'en_attente', 'devise' => 'USD',
        ]);

        $this->get('/portail/verification/jeton-de-test-1234567890')
            ->assertRedirect(route('portail.tableau-bord'));

        $compte->refresh();
        $this->assertNotNull($compte->email_verifie_le);
        $this->assertNull($compte->jeton_verification, 'le jeton doit être consommé');
        // Rapprochement insensible à la casse de l'adresse.
        $this->assertSame($client->id, DemandeAgentIa::sole()->client_id);
    }

    public function test_un_jeton_invalide_ne_verifie_rien(): void
    {
        $compte = $this->compte($this->client(), ['email_verifie_le' => null, 'jeton_verification' => 'le-vrai-jeton']);

        $this->get('/portail/verification/un-autre-jeton')
            ->assertRedirect(route('portail.connexion'))
            ->assertSessionHasErrors();

        $this->assertNull($compte->fresh()->email_verifie_le);
    }

    // ── Connexion ────────────────────────────────────────

    public function test_connexion_reussie_et_journalisee(): void
    {
        $compte = $this->compte($this->client());

        $this->post('/portail/connexion', [
            'email' => 'contact@sainteanne.ht', 'password' => 'MotDePasse2026',
        ])->assertRedirect(route('portail.tableau-bord'));

        $this->assertAuthenticatedAs($compte, 'client');
        $this->assertNotNull($compte->fresh()->dernier_login_le);

        $trace = ConnexionPortail::sole();
        $this->assertTrue($trace->reussie);
        $this->assertSame($compte->id, $trace->compte_portail_id);
    }

    public function test_le_message_derreur_ne_revele_pas_si_le_compte_existe(): void
    {
        $this->compte($this->client());

        // Message strictement identique dans les deux cas : sinon on peut
        // énumérer les clients de GOVIBE en essayant des adresses.
        $this->post('/portail/connexion', ['email' => 'personne@exemple.ht', 'password' => 'MotDePasse2026'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->post('/portail/connexion', ['email' => 'contact@sainteanne.ht', 'password' => 'FauxMotDePasse1'])
            ->assertSessionHasErrors(['email' => 'Identifiants incorrects.']);

        $this->assertGuest('client');
    }

    public function test_les_echecs_sont_traces_avec_leur_motif(): void
    {
        $this->compte($this->client());

        $this->post('/portail/connexion', ['email' => 'personne@exemple.ht', 'password' => 'MotDePasse2026']);
        $this->post('/portail/connexion', ['email' => 'contact@sainteanne.ht', 'password' => 'FauxMotDePasse1']);

        $motifs = ConnexionPortail::pluck('motif')->all();
        $this->assertContains('inconnu', $motifs);
        $this->assertContains('mot_de_passe', $motifs);
    }

    public function test_la_force_brute_est_bloquee(): void
    {
        RateLimiter::clear('portail:contact@sainteanne.ht|127.0.0.1');
        $this->compte($this->client());

        for ($i = 0; $i < 5; $i++) {
            $this->post('/portail/connexion', ['email' => 'contact@sainteanne.ht', 'password' => 'Faux'.$i.'aaaa']);
        }

        // Au sixième essai, même le bon mot de passe est refusé.
        $this->post('/portail/connexion', [
            'email' => 'contact@sainteanne.ht', 'password' => 'MotDePasse2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('client');
        $this->assertTrue(
            ConnexionPortail::where('motif', 'trop_essais')->exists(),
            'le blocage doit laisser une trace exploitable'
        );

        RateLimiter::clear('portail:contact@sainteanne.ht|127.0.0.1');
    }

    public function test_un_compte_desactive_ne_peut_pas_entrer(): void
    {
        RateLimiter::clear('portail:contact@sainteanne.ht|127.0.0.1');
        $this->compte($this->client(), ['actif' => false]);

        $this->post('/portail/connexion', ['email' => 'contact@sainteanne.ht', 'password' => 'MotDePasse2026'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('client');
    }

    // ── Cloisonnement des données ────────────────────────

    public function test_un_client_ne_voit_que_ses_propres_factures(): void
    {
        $sien = $this->client();
        $autre = $this->client(['reference_number' => 'CL-TEST-0002', 'name' => 'Hôtel Cormier', 'email' => 'autre@exemple.ht']);

        Invoice::create([
            'reference' => 'FA-SIENNE', 'client_id' => $sien->id,
            'subtotal' => 1000, 'tax_rate' => 0, 'tax_amount' => 0, 'discount' => 0,
            'total' => 1000, 'status' => 'sent', 'issued_date' => now(), 'due_date' => now()->addDays(30),
        ]);
        Invoice::create([
            'reference' => 'FA-AUTRUI', 'client_id' => $autre->id,
            'subtotal' => 9999, 'tax_rate' => 0, 'tax_amount' => 0, 'discount' => 0,
            'total' => 9999, 'status' => 'sent', 'issued_date' => now(), 'due_date' => now()->addDays(30),
        ]);

        $this->actingAs($this->compte($sien), 'client')->get('/portail/factures')
            ->assertOk()
            ->assertSee('FA-SIENNE')
            ->assertDontSee('FA-AUTRUI');
    }

    public function test_le_tableau_de_bord_rassemble_les_unites_daffaires(): void
    {
        $client = $this->client();

        DemandeAgentIa::create([
            'reference' => 'AI-20260101-BBBB', 'client_id' => $client->id,
            'agent_nom' => 'Agent IA — Restaurant', 'entreprise' => 'Boulangerie Sainte-Anne',
            'responsable' => 'Nadège Pierre', 'email' => 'contact@sainteanne.ht',
            'telephone' => '+509 3712 4455', 'statut' => 'actif',
            'statut_paiement' => 'recu', 'devise' => 'USD',
        ]);

        $this->actingAs($this->compte($client), 'client')->get('/portail')
            ->assertOk()
            ->assertSee('Boulangerie Sainte-Anne')
            ->assertSee('Agent IA — Restaurant')
            ->assertSee('AI-20260101-BBBB');
    }

    // ── Isolation par domaine ────────────────────────────

    public function test_sans_domaine_configure_rien_ne_change(): void
    {
        config(['govibe.domaine_app' => null]);

        $this->get('/portail/connexion')->assertOk();
        // L'ERP reste joignable : une isolation posée trop tôt enfermerait
        // l'équipe dehors de son propre outil.
        $this->get('/erp/login')->assertOk();
    }

    public function test_avec_domaine_configure_le_trafic_est_redirige(): void
    {
        config(['govibe.domaine_app' => 'app.govibeht.com']);

        $reponse = $this->get('http://govibeht.com/portail/connexion');
        $reponse->assertStatus(301);
        $this->assertStringStartsWith('https://app.govibeht.com/', $reponse->headers->get('Location'));

        // Sur le bon domaine, la page répond normalement.
        $this->get('http://app.govibeht.com/portail/connexion')->assertOk();
    }
}
