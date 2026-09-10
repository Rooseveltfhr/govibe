<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\EvenementAbonnement;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\TauxChange;
use App\Models\User;
use App\Services\AbonnementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbonnementTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::create([
            'reference_number' => 'CL-TEST-0001',
            'name' => 'Boulangerie Sainte-Anne', 'type' => 'company',
            'email' => 'contact@sainteanne.ht', 'phone' => '+509 3712 4455',
        ]);
    }

    private function plan(array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'slug' => 'vitrine', 'service' => 'site_web', 'nom' => 'Vitrine',
            'prix_mensuel' => 29.80, 'prix_annuel' => 298, 'devise' => 'USD',
            'tca_taux' => 0, 'essai_jours' => 0, 'actif' => true, 'ordre' => 1,
        ], $extra));
    }

    private function service(): AbonnementService
    {
        return app(AbonnementService::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Roosevelt', 'email' => 'admin@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => true,
        ]);
    }

    // ── Souscription ─────────────────────────────────────

    public function test_souscrire_fige_le_prix_et_la_taxe(): void
    {
        $plan = $this->plan(['tca_taux' => 10]);
        $abonnement = $this->service()->souscrire($this->client(), $plan, 'mensuel');

        $this->assertMatchesRegularExpression('/^AB-\d{8}-[A-Z0-9]{4}$/', $abonnement->reference);
        $this->assertSame(29.80, (float) $abonnement->prix_unitaire);
        $this->assertSame('USD', $abonnement->devise);
        $this->assertSame(10.0, (float) $abonnement->tca_taux);
        $this->assertSame('actif', $abonnement->statut);

        // Le catalogue change : le contrat garde ses conditions.
        $plan->update(['prix_mensuel' => 99, 'tca_taux' => 25, 'nom' => 'Autre nom']);

        $abonnement->refresh();
        $this->assertSame(29.80, (float) $abonnement->prix_unitaire);
        $this->assertSame(10.0, (float) $abonnement->tca_taux);
        $this->assertSame('Vitrine', $abonnement->plan_nom);
    }

    public function test_le_cycle_annuel_prend_le_tarif_annuel(): void
    {
        $abonnement = $this->service()->souscrire($this->client(), $this->plan(), 'annuel');

        $this->assertSame(298.0, (float) $abonnement->prix_unitaire);
        $this->assertSame('annuel', $abonnement->cycle);
    }

    public function test_un_plan_sur_devis_ne_se_souscrit_pas_tout_seul(): void
    {
        $plan = $this->plan(['slug' => 'sur-mesure', 'sur_devis' => true, 'prix_mensuel' => null]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service()->souscrire($this->client(), $plan, 'mensuel');
    }

    public function test_lessai_repousse_la_premiere_facture(): void
    {
        Carbon::setTestNow('2026-09-12');
        $plan = $this->plan(['essai_jours' => 14]);

        $abonnement = $this->service()->souscrire($this->client(), $plan, 'mensuel');

        $this->assertSame('essai', $abonnement->statut);
        $this->assertSame('2026-09-25', $abonnement->essai_fin->toDateString());
        // Rien n'est facturé pendant l'essai.
        $this->assertSame('2026-09-26', $abonnement->date_prochaine_facture->toDateString());
        Carbon::setTestNow();
    }

    // ── Facturation ──────────────────────────────────────

    public function test_facturer_emet_une_facture_avec_sa_periode(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan(['tca_taux' => 10]));

        $facture = $this->service()->facturer($abonnement);

        $this->assertNotNull($facture);
        $this->assertSame(29.80, (float) $facture->subtotal);
        $this->assertSame(2.98, (float) $facture->tax_amount);
        $this->assertSame(32.78, (float) $facture->total);
        $this->assertSame('USD', $facture->devise);
        $this->assertSame('2026-09-12', $facture->periode_debut->toDateString());
        $this->assertSame('2026-10-11', $facture->periode_fin->toDateString());
        $this->assertSame(1, $facture->items()->count());

        // L'échéance suivante suit immédiatement la période facturée.
        $this->assertSame('2026-10-12', $abonnement->fresh()->date_prochaine_facture->toDateString());
        Carbon::setTestNow();
    }

    public function test_facturer_deux_fois_la_meme_periode_ne_double_pas(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());

        $premiere = $this->service()->facturer($abonnement);
        // La tâche rejouée le même jour, ou lancée deux fois : une seule facture.
        $seconde = $this->service()->facturer($abonnement->fresh());

        $this->assertSame(1, Invoice::count());
        $this->assertSame($premiere->id, $seconde?->id ?? $premiere->id);
        Carbon::setTestNow();
    }

    public function test_lessai_bascule_en_actif_a_la_premiere_facture(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan(['essai_jours' => 7]));
        $this->assertSame('essai', $abonnement->statut);

        Carbon::setTestNow('2026-09-20');
        $this->service()->facturer($abonnement);

        $this->assertSame('actif', $abonnement->fresh()->statut);
        Carbon::setTestNow();
    }

    public function test_un_abonnement_resilie_nest_plus_facture(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        $this->service()->resilier($abonnement->fresh(), 'Le client arrête.');

        Carbon::setTestNow('2026-10-15');
        $this->service()->facturer($abonnement->fresh());

        $this->assertSame(1, Invoice::count(), 'aucune facture après résiliation');
        Carbon::setTestNow();
    }

    public function test_la_commande_ne_facture_que_les_echeances_atteintes(): void
    {
        Carbon::setTestNow('2026-09-12');
        $client = $this->client();
        $du = $this->service()->souscrire($client, $this->plan());
        $pasEncore = $this->service()->souscrire(
            $client,
            $this->plan(['slug' => 'business', 'nom' => 'Business', 'prix_mensuel' => 49.80]),
            'mensuel',
            Carbon::parse('2026-10-01')
        );

        $this->artisan('abonnements:facturer')->assertSuccessful();

        $this->assertSame(1, Invoice::count());
        $this->assertSame($du->id, Invoice::sole()->abonnement_id);
        $this->assertSame(0, Invoice::where('abonnement_id', $pasEncore->id)->count());
        Carbon::setTestNow();
    }

    public function test_la_simulation_necrit_rien(): void
    {
        Carbon::setTestNow('2026-09-12');
        $this->service()->souscrire($this->client(), $this->plan());

        $this->artisan('abonnements:facturer', ['--essai' => true])->assertSuccessful();

        $this->assertSame(0, Invoice::count());
        Carbon::setTestNow();
    }

    public function test_une_periode_non_commencee_nest_jamais_facturee(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        // Rappeler la méthode dix fois ne doit pas encaisser dix mois d'avance.
        for ($i = 0; $i < 10; $i++) {
            $this->service()->facturer($abonnement->fresh());
        }

        $this->assertSame(1, Invoice::count());
        $this->assertSame('2026-10-12', $abonnement->fresh()->date_prochaine_facture->toDateString());

        // À l'échéance, la période suivante part normalement.
        Carbon::setTestNow('2026-10-12');
        $this->service()->facturer($abonnement->fresh());
        $this->assertSame(2, Invoice::count());
        Carbon::setTestNow();
    }

    public function test_un_essai_de_quatorze_jours_dure_quatorze_jours(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan(['essai_jours' => 14]));

        // Du 12 au 25 inclus : quatorze jours, pas quinze.
        $this->assertSame(
            14,
            (int) $abonnement->date_debut->diffInDays($abonnement->essai_fin) + 1
        );
        Carbon::setTestNow();
    }

    public function test_les_ecrans_erp_rendent(): void
    {
        Carbon::setTestNow('2026-09-12');
        $admin = $this->admin();
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        $this->actingAs($admin)->get(route('erp.abonnements.index'))
            ->assertOk()->assertSee($abonnement->reference)->assertSee('Vitrine');

        $this->actingAs($admin)->get(route('erp.abonnements.show', $abonnement))
            ->assertOk()->assertSee('Historique')->assertSee('Factures');

        $this->actingAs($admin)->get(route('erp.abonnements.plans'))
            ->assertOk()->assertSee('Vitrine')->assertSee('29,8');
        Carbon::setTestNow();
    }

    public function test_un_agent_cree_un_plan_depuis_lerp(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('erp.abonnements.plans.store'), [
            'nom' => 'Business', 'service' => 'site_web', 'devise' => 'USD',
            'prix_mensuel' => '49.80', 'prix_annuel' => '498', 'tca_taux' => '10',
            'quotas' => "stockage_mo: 15000\nboites_email: 5",
            'fonctionnalites' => "Pages illimitées\nSauvegarde quotidienne",
            'essai_jours' => 0, 'actif' => 1, 'ordre' => 2,
        ])->assertRedirect(route('erp.abonnements.plans'));

        $plan = Plan::where('slug', 'business')->sole();
        $this->assertSame(49.80, (float) $plan->prix_mensuel);
        $this->assertSame(10.0, (float) $plan->tca_taux);
        $this->assertSame(['stockage_mo' => 15000, 'boites_email' => 5], $plan->quotas);
        $this->assertSame(['Pages illimitées', 'Sauvegarde quotidienne'], $plan->fonctionnalites);
    }

    public function test_retirer_un_plan_preserve_les_abonnements(): void
    {
        $plan = $this->plan();
        $abonnement = $this->service()->souscrire($this->client(), $plan);

        $plan->delete();

        $abonnement->refresh();
        $this->assertNull($abonnement->plan_id);
        $this->assertSame('Vitrine', $abonnement->plan_nom);
        $this->assertSame(29.80, (float) $abonnement->prix_unitaire);
    }

    // ── Impayés : relance avant coupure ──────────────────

    public function test_une_facture_echue_passe_labonnement_en_retard(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        // Échéance à 7 jours : dépassée le 22, mais dans le délai de grâce.
        Carbon::setTestNow('2026-09-22');
        $bilan = $this->service()->releverLesImpayes(14);

        $this->assertSame(1, $bilan['en_retard']);
        $this->assertSame(0, $bilan['suspendus'], 'on relance avant de couper');
        $this->assertSame('en_retard', $abonnement->fresh()->statut);
        Carbon::setTestNow();
    }

    public function test_la_suspension_narrive_quapres_le_delai_de_grace(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        Carbon::setTestNow('2026-10-10');
        $bilan = $this->service()->releverLesImpayes(14);

        $this->assertSame(1, $bilan['suspendus']);
        $this->assertSame('suspendu', $abonnement->fresh()->statut);
        Carbon::setTestNow();
    }

    public function test_le_reglement_ramene_labonnement_en_actif(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $facture = $this->service()->facturer($abonnement);

        Carbon::setTestNow('2026-09-22');
        $this->service()->releverLesImpayes(14);
        $this->assertSame('en_retard', $abonnement->fresh()->statut);

        $this->service()->encaisser($facture->fresh());

        $this->assertSame('actif', $abonnement->fresh()->statut);
        $this->assertSame('paid', $facture->fresh()->status);
        Carbon::setTestNow();
    }

    public function test_un_abonnement_suspendu_ne_se_reactive_pas_tout_seul(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $facture = $this->service()->facturer($abonnement);

        Carbon::setTestNow('2026-10-10');
        $this->service()->releverLesImpayes(14);
        $this->service()->encaisser($facture->fresh());

        // Remettre un service en ligne est une décision d'exploitation.
        $this->assertSame('suspendu', $abonnement->fresh()->statut);
        Carbon::setTestNow();
    }

    // ── Résiliation ──────────────────────────────────────

    public function test_resilier_laisse_courir_la_periode_payee(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);

        $this->service()->resilier($abonnement->fresh(), 'Trop cher');
        $abonnement->refresh();

        $this->assertFalse($abonnement->renouvellement_auto);
        $this->assertNull($abonnement->date_prochaine_facture);
        // Le service va au bout de ce qui a été payé.
        $this->assertSame('2026-10-11', $abonnement->resiliation_effective_le->toDateString());
        $this->assertSame('Trop cher', $abonnement->motif_resiliation);
        Carbon::setTestNow();
    }

    // ── Devises ──────────────────────────────────────────

    public function test_la_facture_porte_les_deux_montants(): void
    {
        Carbon::setTestNow('2026-09-12');
        $admin = $this->admin();
        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 132,
            'defini_par' => $admin->id, 'applique_depuis' => now()->subDay(),
        ]);

        $facture = $this->service()->facturer(
            $this->service()->souscrire($this->client(), $this->plan())
        );

        $this->assertSame('USD', $facture->devise);
        $this->assertSame('HTG', $facture->devise_convertie);
        $this->assertEqualsWithDelta(29.80 * 132, (float) $facture->montant_converti, 0.5);

        // Le taux bouge : la facture émise garde le sien.
        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 200,
            'defini_par' => $admin->id, 'applique_depuis' => now(),
        ]);
        $this->assertEqualsWithDelta(29.80 * 132, (float) $facture->fresh()->montant_converti, 0.5);
        Carbon::setTestNow();
    }

    public function test_sans_taux_aucune_conversion_nest_inventee(): void
    {
        $facture = $this->service()->facturer(
            $this->service()->souscrire($this->client(), $this->plan())
        );

        $this->assertNull($facture->taux_change);
        $this->assertNull($facture->montant_converti);
    }

    // ── Traces ───────────────────────────────────────────

    public function test_chaque_etape_laisse_une_trace(): void
    {
        Carbon::setTestNow('2026-09-12');
        $abonnement = $this->service()->souscrire($this->client(), $this->plan());
        $this->service()->facturer($abonnement);
        $this->service()->resilier($abonnement->fresh(), 'Fin de contrat');

        $types = EvenementAbonnement::where('abonnement_id', $abonnement->id)->pluck('type')->all();

        $this->assertContains('souscription', $types);
        $this->assertContains('facture_emise', $types);
        $this->assertContains('resiliation', $types);
        Carbon::setTestNow();
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_lerp_des_abonnements_est_protege(): void
    {
        $this->get('/erp/abonnements')->assertRedirect(route('erp.login'));
        $this->get('/erp/abonnements/plans')->assertRedirect(route('erp.login'));
    }
}
