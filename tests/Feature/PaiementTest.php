<?php

namespace Tests\Feature;

use App\Models\EvenementPaiement;
use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Models\TauxChange;
use App\Models\User;
use App\Paiement\Pilotes\MonCash;
use App\Paiement\RegistrePilotes;
use App\Services\PaiementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaiementTest extends TestCase
{
    use RefreshDatabase;

    private function moncash(array $extra = []): PasserellePaiement
    {
        $p = PasserellePaiement::firstOrNew(['code' => 'moncash']);
        $p->fill(array_merge([
            'nom' => 'MonCash', 'type' => 'mobile_money', 'actif' => true, 'ordre' => 1,
            'mode' => 'api', 'pilote' => 'moncash', 'environnement' => 'test',
            'identifiants' => ['client_id' => 'id-de-test', 'client_secret' => 'secret-de-test'],
            'disponible_public' => true,
        ], $extra))->save();

        return $p->refresh();
    }

    private function service(): PaiementService
    {
        return app(PaiementService::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Roosevelt', 'email' => 'admin@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => true,
        ]);
    }

    private function reponsesMonCash(string $message = 'successful', $cout = 2500): array
    {
        return [
            '*/Api/oauth/token' => Http::response(['access_token' => 'jeton-oauth', 'expires_in' => 3600]),
            '*/Api/v1/CreatePayment' => Http::response(['payment_token' => ['token' => 'jeton-paiement-abc']]),
            '*/Api/v1/RetrieveOrderPayment' => Http::response(['payment' => [
                'transaction_id' => 'TX-889900', 'message' => $message, 'cost' => $cout,
            ]]),
        ];
    }

    // ── Chiffrement des clés ─────────────────────────────

    public function test_les_cles_api_sont_chiffrees_en_base(): void
    {
        $passerelle = $this->moncash();

        $brut = \DB::table('passerelles_paiement')->where('id', $passerelle->id)->value('identifiants');

        // Une base copiée ne doit pas livrer les clés en clair.
        $this->assertStringNotContainsString('secret-de-test', $brut);
        $this->assertSame('secret-de-test', $passerelle->identifiants['client_secret']);
    }

    public function test_les_cles_ne_sortent_jamais_dans_une_serialisation(): void
    {
        $json = $this->moncash()->toJson();

        $this->assertStringNotContainsString('identifiants', $json);
        $this->assertStringNotContainsString('secret-de-test', $json);
    }

    public function test_une_passerelle_api_sans_cles_est_masquee_du_site(): void
    {
        $sans = $this->moncash(['identifiants' => ['client_id' => 'seul-id']]);

        $this->assertFalse($sans->api_prete);
        $this->assertTrue($sans->est_incomplete, 'une passerelle incomplète ne doit pas être proposée');
    }

    // ── Le parcours MonCash ──────────────────────────────

    public function test_initier_redirige_vers_moncash(): void
    {
        Http::fake($this->reponsesMonCash());
        $passerelle = $this->moncash();

        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');
        $resultat = $this->service()->initier($paiement);

        $this->assertTrue($resultat->reussi);
        $this->assertStringContainsString('Payment/Redirect?token=', $resultat->urlRedirection);
        $this->assertStringContainsString('sandbox', $resultat->urlRedirection);
        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    public function test_le_jeton_de_paiement_nest_pas_stocke_en_clair(): void
    {
        Http::fake($this->reponsesMonCash());
        $passerelle = $this->moncash();

        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');
        $this->service()->initier($paiement);

        $this->assertStringNotContainsString(
            'jeton-paiement-abc',
            json_encode($paiement->fresh()->charge_utile)
        );
    }

    public function test_moncash_refuse_une_devise_autre_que_la_gourde(): void
    {
        Http::fake($this->reponsesMonCash());
        $passerelle = $this->moncash();

        $paiement = $this->service()->ouvrir($passerelle, 100, 'USD');
        $resultat = $this->service()->initier($paiement);

        $this->assertFalse($resultat->reussi);
        $this->assertSame('echoue', $paiement->fresh()->statut);
    }

    public function test_le_verdict_vient_de_moncash_pas_du_navigateur(): void
    {
        Http::fake($this->reponsesMonCash());
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        // Le client revient avec des paramètres qu'il pourrait avoir inventés.
        $this->get(route('paiement.retour', $paiement).'?statut=reussi&montant=999999')
            ->assertOk();

        $paiement->refresh();
        $this->assertSame('reussi', $paiement->statut);
        $this->assertSame('TX-889900', $paiement->reference_externe);
        // Le montant reste celui demandé, pas celui de l'URL.
        $this->assertSame(2500.0, (float) $paiement->montant);
    }

    public function test_un_retour_sur_un_paiement_non_paye_ne_credite_rien(): void
    {
        Http::fake($this->reponsesMonCash('pending'));
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $this->get(route('paiement.retour', $paiement).'?statut=reussi')->assertOk();

        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    public function test_un_montant_discordant_nest_pas_credite(): void
    {
        // MonCash dit « payé », mais 500 gourdes au lieu de 2 500.
        Http::fake($this->reponsesMonCash('successful', 500));
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $this->service()->verifier($paiement);
        $paiement->refresh();

        $this->assertSame('verification', $paiement->statut);
        $this->assertStringContainsString('différent', $paiement->echec_motif);
    }

    public function test_un_retour_rejoue_ne_credite_pas_deux_fois(): void
    {
        Http::fake($this->reponsesMonCash());
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $this->get(route('paiement.retour', $paiement))->assertOk();
        $premier = $paiement->fresh()->paye_le;

        // Rejouer le retour trois fois, comme le ferait un rechargement.
        $this->get(route('paiement.retour', $paiement))->assertOk();
        $this->get(route('paiement.retour', $paiement))->assertOk();
        $this->post(route('paiement.notification', $paiement))->assertOk();

        $paiement->refresh();
        $this->assertSame('reussi', $paiement->statut);
        $this->assertEquals($premier, $paiement->paye_le, 'la date de paiement ne doit pas bouger');
        $this->assertSame(
            1,
            EvenementPaiement::where('type', 'paiement_confirme')->count(),
            'un seul événement de confirmation'
        );
    }

    public function test_moncash_injoignable_laisse_le_paiement_en_attente(): void
    {
        // Injoignable n'est pas « échoué » : le client a peut-être payé.
        Http::fake(['*' => Http::response(null, 500)]);
        $passerelle = $this->moncash();

        $paiement = Paiement::create([
            'reference' => 'PM-20260911-TEST', 'passerelle_id' => $passerelle->id,
            'mode' => 'api', 'pilote' => 'moncash', 'passerelle_nom' => 'MonCash',
            'montant' => 2500, 'devise' => 'HTG', 'statut' => 'en_attente',
            'cle_idempotence' => 'cle-test-1',
        ]);

        $this->service()->verifier($paiement);

        $this->assertSame('en_attente', $paiement->fresh()->statut);
    }

    // ── Manuel, cash et habilitation ─────────────────────

    public function test_approuver_un_paiement_demande_une_habilitation(): void
    {
        $passerelle = $this->moncash(['mode' => 'manuel', 'pilote' => null, 'numero_compte' => '34420793']);
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $simpleEmploye = User::create([
            'name' => 'Employé', 'email' => 'employe@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => false,
        ]);

        // Sans is_admin, le middleware ERP refuse déjà l'entrée.
        $this->actingAs($simpleEmploye)
            ->post(route('erp.transactions.approuver', $paiement))
            ->assertRedirect(route('erp.login'));

        $this->assertSame('initie', $paiement->fresh()->statut);
    }

    public function test_un_agent_habilite_approuve_et_la_trace_reste(): void
    {
        $passerelle = $this->moncash(['mode' => 'manuel', 'pilote' => null, 'numero_compte' => '34420793']);
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('erp.transactions.approuver', $paiement), ['reference_externe' => 'RECU-4455'])
            ->assertRedirect();

        $paiement->refresh();
        $this->assertSame('reussi', $paiement->statut);
        $this->assertSame($admin->id, $paiement->approuve_par);
        $this->assertSame('RECU-4455', $paiement->reference_externe);

        $trace = EvenementPaiement::where('type', 'approbation_manuelle')->sole();
        $this->assertSame('agent', $trace->source);
        $this->assertSame($admin->id, $trace->user_id);
    }

    public function test_approuver_deux_fois_ne_change_rien(): void
    {
        $passerelle = $this->moncash(['mode' => 'manuel', 'pilote' => null, 'numero_compte' => '34420793']);
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');
        $admin = $this->admin();

        $this->service()->approuverManuel($paiement, $admin->id);
        $premier = $paiement->fresh()->paye_le;

        $this->service()->approuverManuel($paiement->fresh(), $admin->id);

        $this->assertEquals($premier, $paiement->fresh()->paye_le);
        $this->assertSame(1, EvenementPaiement::where('type', 'approbation_manuelle')->count());
    }

    public function test_un_paiement_reussi_ne_peut_plus_etre_rejete(): void
    {
        $passerelle = $this->moncash(['mode' => 'manuel', 'pilote' => null, 'numero_compte' => '34420793']);
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');
        $admin = $this->admin();

        $this->service()->approuverManuel($paiement, $admin->id);
        $this->service()->rejeter($paiement->fresh(), $admin->id, 'erreur de saisie');

        $this->assertSame('reussi', $paiement->fresh()->statut);
    }

    public function test_le_cash_nest_pas_propose_au_public(): void
    {
        PasserellePaiement::create([
            'nom' => 'Espèces en caisse', 'code' => 'cash', 'type' => 'transfert',
            'actif' => true, 'mode' => 'manuel', 'ordre' => 99,
            'numero_compte' => 'CAISSE',
            'disponible_public' => false, 'disponible_caisse' => true,
        ]);

        $publics = PasserellePaiement::public()->pluck('code')->all();
        $caisse = PasserellePaiement::caisse()->pluck('code')->all();

        $this->assertNotContains('cash', $publics, 'le cash ne doit jamais être sélectionnable en ligne');
        $this->assertContains('cash', $caisse);
    }

    public function test_encaisser_en_especes_exige_un_employe(): void
    {
        $caisse = PasserellePaiement::create([
            'nom' => 'Espèces en caisse', 'code' => 'cash', 'type' => 'transfert',
            'actif' => true, 'mode' => 'manuel', 'numero_compte' => 'CAISSE',
            'disponible_public' => false, 'disponible_caisse' => true, 'ordre' => 99,
        ]);
        $admin = $this->admin();

        $paiement = $this->service()->encaisserCash($caisse, 2500, 'HTG', $admin->id);

        $this->assertSame('reussi', $paiement->statut);
        $this->assertSame('cash', $paiement->mode);
        $this->assertSame($admin->id, $paiement->approuve_par);
    }

    // ── Devises et taux ──────────────────────────────────

    public function test_sans_taux_regle_aucune_conversion_nest_inventee(): void
    {
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $this->assertNull($paiement->montant_converti);
        $this->assertNull($paiement->taux_change);
    }

    public function test_la_conversion_est_figee_a_louverture(): void
    {
        $admin = $this->admin();
        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 132,
            'defini_par' => $admin->id, 'applique_depuis' => now()->subDay(),
        ]);

        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 13200, 'HTG');

        // Le taux inverse fait foi : saisir USD→HTG suffit.
        $this->assertSame('USD', $paiement->devise_convertie);
        $this->assertEqualsWithDelta(100.0, (float) $paiement->montant_converti, 0.5);

        // Le taux change après coup : le paiement garde le sien.
        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 200,
            'defini_par' => $admin->id, 'applique_depuis' => now(),
        ]);

        $this->assertEqualsWithDelta(100.0, (float) $paiement->fresh()->montant_converti, 0.5);
    }

    public function test_le_super_admin_enregistre_un_taux_et_lhistorique_reste(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('erp.transactions.taux'), [
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => '132.50',
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('erp.transactions.taux'), [
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => '134.00',
        ])->assertRedirect();

        $this->assertSame(2, TauxChange::count(), "l'ancien taux est conservé");
        $this->assertEqualsWithDelta(134.0, TauxChange::actuel('USD', 'HTG'), 0.001);
    }

    // ── Configuration depuis l'ERP ───────────────────────

    public function test_le_mode_test_est_le_defaut_et_la_production_est_explicite(): void
    {
        $passerelle = PasserellePaiement::create([
            'nom' => 'PayPal', 'code' => 'paypal-test', 'type' => 'lien',
            'lien_paiement' => 'https://example.test', 'actif' => true, 'ordre' => 5,
        ]);

        $this->assertSame('test', $passerelle->environnement);
        $this->assertSame('manuel', $passerelle->mode);
    }

    public function test_une_cle_laissee_vide_ne_efface_pas_celle_enregistree(): void
    {
        $passerelle = $this->moncash();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('erp.transactions.configurer', $passerelle), [
            'mode' => 'api', 'pilote' => 'moncash', 'environnement' => 'production',
            'disponible_public' => 1,
            'identifiants' => ['client_id' => '', 'client_secret' => ''],
        ])->assertRedirect();

        $passerelle->refresh();
        $this->assertSame('secret-de-test', $passerelle->identifiants['client_secret']);
        $this->assertSame('production', $passerelle->environnement);
    }

    public function test_la_configuration_est_refusee_sans_habilitation(): void
    {
        $passerelle = $this->moncash();

        $this->post(route('erp.transactions.configurer', $passerelle), [
            'mode' => 'manuel', 'environnement' => 'test',
        ])->assertRedirect(route('erp.login'));

        $this->assertSame('test', $passerelle->fresh()->environnement);
    }

    public function test_lerp_des_transactions_est_protege(): void
    {
        $this->get('/erp/transactions')->assertRedirect(route('erp.login'));
        $this->get('/erp/transactions/configuration')->assertRedirect(route('erp.login'));
    }

    public function test_les_ecrans_erp_rendent(): void
    {
        $admin = $this->admin();
        $passerelle = $this->moncash();
        $paiement = $this->service()->ouvrir($passerelle, 2500, 'HTG');

        $this->actingAs($admin)->get(route('erp.transactions.index'))
            ->assertOk()->assertSee($paiement->reference)->assertSee('MonCash');

        $this->actingAs($admin)->get(route('erp.transactions.show', $paiement))
            ->assertOk()->assertSee('Historique')->assertSee('2 500 HTG');

        $config = $this->actingAs($admin)->get(route('erp.transactions.configuration'))->assertOk();

        // La clé est reconnaissable, jamais relisible.
        $config->assertSee('MonCash')
            ->assertDontSee('secret-de-test')
            ->assertSee('Aucun taux réglé');
    }

    public function test_le_pilote_moncash_est_enregistre(): void
    {
        $registre = app(RegistrePilotes::class);

        $this->assertContains('moncash', $registre->cles());
        $this->assertInstanceOf(MonCash::class, $registre->trouver('moncash'));
        $this->assertSame(['HTG'], $registre->trouver('moncash')->devises());
    }
}
