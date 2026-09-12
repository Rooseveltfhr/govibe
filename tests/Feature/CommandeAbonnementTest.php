<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Client;
use App\Models\CommandeAbonnement;
use App\Models\Paiement;
use App\Models\PasserellePaiement;
use App\Models\Plan;
use App\Models\PreuvePaiement;
use App\Models\TauxChange;
use App\Models\User;
use App\Services\AbonnementService;
use App\Services\PaiementService;
use App\Services\TarifPasserelle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommandeAbonnementTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $extra = []): Plan
    {
        return Plan::create(array_merge([
            'slug' => 'vitrine', 'service' => 'site_web', 'nom' => 'Vitrine',
            'description' => 'Un site de présentation entretenu par GOVIBE.',
            'prix_mensuel' => 29.80, 'prix_annuel' => 298, 'devise' => 'USD',
            'tca_taux' => 0, 'essai_jours' => 0, 'actif' => true, 'ordre' => 1,
            'fonctionnalites' => ['Certificat SSL', 'Sauvegardes hebdomadaires'],
        ], $extra));
    }

    /** NatCash est semée par la migration, en mode manuel avec son numéro. */
    private function natcash(array $extra = []): PasserellePaiement
    {
        $p = PasserellePaiement::where('code', 'natcash')->firstOrFail();
        if ($extra) {
            $p->update($extra);
        }

        return $p->refresh();
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@govibeht.com'],
            ['name' => 'Roosevelt', 'password' => Hash::make('MotDePasse2026'), 'is_admin' => true],
        );
    }

    /** @return array<string, mixed> */
    private function formulaire(array $extra = []): array
    {
        return array_merge([
            'nom_complet' => 'Marie Joseph',
            'entreprise' => 'Boulangerie Sainte-Anne',
            'email' => 'marie@sainteanne.ht',
            'whatsapp' => '+509 3712 4455',
            'domaine_origine' => 'a_enregistrer',
            'domaine' => 'SainteAnne.HT',
            'moyen_paiement' => 'natcash',
            'preuve' => UploadedFile::fake()->image('capture.jpg'),
        ], $extra);
    }

    // ── Pages publiques ──────────────────────────────────

    public function test_la_page_d_un_service_affiche_ses_offres(): void
    {
        $this->plan();
        $this->plan(['slug' => 'heberge-pro', 'service' => 'hebergement', 'nom' => 'Hébergement Pro']);

        $this->get('/sites-web')
            ->assertOk()
            ->assertSee('Vitrine')
            ->assertSee('29,80')
            ->assertSee('Certificat SSL')
            // L'offre d'un autre service n'a rien à faire sur cette page.
            ->assertDontSee('Hébergement Pro');

        $this->get('/hebergement')->assertOk()->assertSee('Hébergement Pro')->assertDontSee('Vitrine');
        $this->get('/domaines')->assertOk();
    }

    /**
     * La route des offres vit à la racine (/sites-web) pour être diffusable en
     * publicité. Sa contrainte ne doit accepter que ces trois segments — sinon
     * elle capterait les autres pages du site, et une page ajoutée demain
     * tomberait sur l'offre au lieu d'elle-même.
     */
    public function test_la_route_des_offres_ne_capte_aucune_autre_page(): void
    {
        foreach (['/', '/about', '/programmes', '/partenaires', '/fiche-technique',
            '/paiement', '/agents-ia', '/landry', '/tarifs', '/services'] as $chemin) {
            $reponse = $this->get($chemin);

            $this->assertTrue(
                $reponse->isOk() || $reponse->isRedirect(),
                "{$chemin} répond ".$reponse->status().' : la route des offres l\'a capté.'
            );
            $reponse->assertDontSee('Les autres services par abonnement');
        }

        // Un segment inconnu reste une page introuvable, pas une offre vide.
        $this->get('/nimporte-quoi')->assertNotFound();
    }

    public function test_sans_offre_publiee_la_page_propose_un_devis_au_lieu_d_un_prix(): void
    {
        // Aucun tarif n'est inventé quand le catalogue est vide.
        $this->get('/sites-web')
            ->assertOk()
            ->assertSee('en cours de publication')
            ->assertSee('Demander un devis');
    }

    public function test_un_plan_inactif_ne_se_commande_pas(): void
    {
        $plan = $this->plan(['actif' => false]);

        $this->get(route('abonnements.commande', $plan))->assertNotFound();
        $this->post(route('abonnements.store', $plan), $this->formulaire())->assertNotFound();
    }

    public function test_un_plan_sur_devis_renvoie_vers_l_offre(): void
    {
        $plan = $this->plan(['sur_devis' => true, 'prix_mensuel' => null, 'prix_annuel' => null]);

        $this->get(route('abonnements.commande', $plan))
            ->assertRedirect(route('abonnements.service', 'sites-web'));
    }

    public function test_le_formulaire_affiche_le_total_de_chaque_moyen(): void
    {
        $plan = $this->plan();

        $this->get(route('abonnements.commande', $plan))
            ->assertOk()
            ->assertSee('Vitrine')
            ->assertSee('NatCash')
            ->assertSee('29,80');
    }

    // ── Enregistrement d'une commande ────────────────────

    public function test_une_commande_manuelle_enregistre_la_commande_la_preuve_et_le_paiement(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        $reponse = $this->post(route('abonnements.store', $plan), $this->formulaire());
        $reponse->assertRedirect(route('abonnements.merci'));

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertMatchesRegularExpression('/^CM-\d{8}-[A-Z0-9]{4}$/', $commande->reference);
        $this->assertSame('site_web', $commande->service);
        $this->assertSame('Vitrine', $commande->plan_nom);
        $this->assertSame('paiement_attente', $commande->statut);
        $this->assertSame('manuel', $commande->mode_paiement);
        // Le domaine est normalisé : « SainteAnne.HT » et « sainteanne.ht » sont
        // le même nom, et l'équipe doit pouvoir le retrouver en cherchant.
        $this->assertSame('sainteanne.ht', $commande->domaine);

        $this->assertNotNull($commande->preuve_paiement_id);
        $this->assertSame(1, PreuvePaiement::count());
        Storage::disk('local')->assertExists(PreuvePaiement::first()->fichier);

        $paiement = Paiement::firstOrFail();
        $this->assertSame(CommandeAbonnement::class, $paiement->payable_type);
        $this->assertSame($commande->id, $paiement->payable_id);
        $this->assertSame(29.80, (float) $paiement->montant);
        // La preuve est rattachée au paiement aussi : c'est là que l'agent tranche.
        $this->assertSame($commande->preuve_paiement_id, $paiement->preuve_paiement_id);
    }

    public function test_le_prix_vient_du_catalogue_pas_du_formulaire(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        // Un visiteur qui retape le formulaire pour payer un dollar.
        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'montant_a_payer' => 1,
            'montant_ttc' => 1,
            'prix_unitaire' => 1,
            'devise' => 'HTG',
        ]));

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertSame(29.80, (float) $commande->montant_a_payer);
        $this->assertSame(29.80, (float) $commande->montant_ttc);
        $this->assertSame('USD', $commande->devise);
    }

    public function test_la_taxe_est_ajoutee_au_total(): void
    {
        Storage::fake('local');
        $plan = $this->plan(['tca_taux' => 10]);

        $this->post(route('abonnements.store', $plan), $this->formulaire());

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertSame(29.80, (float) $commande->prix_unitaire);
        $this->assertSame(32.78, (float) $commande->montant_ttc);
        $this->assertSame(32.78, (float) $commande->montant_a_payer);
    }

    public function test_un_moyen_inconnu_est_refuse(): void
    {
        $plan = $this->plan();

        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'moyen_paiement' => 'banque-inventee',
        ]))->assertSessionHasErrors('moyen_paiement');

        $this->assertSame(0, CommandeAbonnement::count());
    }

    public function test_un_moyen_manuel_exige_la_preuve(): void
    {
        $plan = $this->plan();

        $this->post(route('abonnements.store', $plan), $this->formulaire(['preuve' => null]))
            ->assertSessionHasErrors('preuve');

        $this->assertSame(0, CommandeAbonnement::count());
    }

    public function test_un_moyen_automatique_n_exige_pas_de_preuve(): void
    {
        $plan = $this->plan();

        // Une passerelle API sans clés ne serait pas proposable : on la configure
        // comme un lien, qui n'exige ni clés ni capture.
        PasserellePaiement::where('code', 'paypal')->update(['mode' => 'manuel']);

        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'moyen_paiement' => 'paypal',
            'preuve' => null,
        ]))->assertSessionHasErrors('preuve');

        // En revanche la même commande avec une vraie passerelle API passe sans
        // capture : c'est la passerelle qui confirme. Le passage par le modèle
        // est nécessaire — une mise à jour par le constructeur de requêtes
        // n'applique pas le chiffrement des clés, et la passerelle serait alors
        // considérée comme non configurée.
        Http::fake([
            '*/Api/oauth/token' => Http::response(['access_token' => 'jeton', 'expires_in' => 3600]),
            '*/Api/v1/CreatePayment' => Http::response(['payment_token' => ['token' => 'jeton-abc']]),
        ]);

        $moncash = PasserellePaiement::where('code', 'moncash')->firstOrFail();
        $moncash->fill([
            'mode' => 'api', 'pilote' => 'moncash', 'environnement' => 'test',
            'identifiants' => ['client_id' => 'id', 'client_secret' => 'secret'],
        ])->save();

        $this->assertTrue($moncash->refresh()->api_prete);

        // Le client part payer chez la passerelle : la redirection sort du site.
        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'moyen_paiement' => 'moncash',
            'preuve' => null,
        ]))->assertRedirect();

        $this->assertSame(1, CommandeAbonnement::count());
        $this->assertNull(CommandeAbonnement::first()->preuve_paiement_id);
    }

    public function test_le_domaine_n_est_pas_garde_quand_le_client_n_en_a_pas(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'domaine_origine' => 'aucun',
            'domaine' => 'jeNaiPasDeDomaine.com',
        ]));

        $this->assertNull(CommandeAbonnement::firstOrFail()->domaine);
    }

    public function test_la_preuve_porte_la_reference_de_la_commande(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        $this->post(route('abonnements.store', $plan), $this->formulaire());

        $commande = CommandeAbonnement::firstOrFail();
        // Sans la référence dans le motif, l'agent qui ouvre la liste des preuves
        // ne peut pas savoir à quelle commande la capture appartient.
        $this->assertStringContainsString($commande->reference, PreuvePaiement::firstOrFail()->motif);
    }

    public function test_un_domaine_colle_avec_son_protocole_est_normalise(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        // Gardé tel quel, ce nom ne ressortirait sur aucune recherche de l'équipe.
        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'domaine' => 'HTTPS://Www.SainteAnne.ht/contact',
        ]));

        $this->assertSame('www.sainteanne.ht', CommandeAbonnement::firstOrFail()->domaine);
    }

    public function test_une_passerelle_par_lien_affiche_son_lien_de_paiement(): void
    {
        $plan = $this->plan();

        // PayPal est semée avec un lien et sans numéro de compte : sans ce lien
        // affiché, le client n'aurait aucun moyen de payer.
        $this->get(route('abonnements.commande', $plan))
            ->assertOk()
            ->assertSee('PayPal')
            ->assertSee(PasserellePaiement::where('code', 'paypal')->value('lien_paiement'), false);
    }

    public function test_la_duree_n_est_gardee_que_pour_un_nom_de_domaine(): void
    {
        Storage::fake('local');

        $site = $this->plan();
        $this->post(route('abonnements.store', $site), $this->formulaire(['duree_annees' => 5]));
        $this->assertNull(CommandeAbonnement::firstOrFail()->duree_annees);

        CommandeAbonnement::query()->delete();

        $domaine = $this->plan([
            'slug' => 'domaine-com', 'service' => 'domaine', 'nom' => 'Domaine .com',
        ]);
        $this->post(route('abonnements.store', $domaine), $this->formulaire([
            'domaine' => 'sainteanne.com',
            'domaine_origine' => 'a_enregistrer',
            'duree_annees' => 5,
        ]));
        $this->assertSame(5, CommandeAbonnement::firstOrFail()->duree_annees);
    }

    public function test_la_duree_n_est_pas_exigee_en_ligne(): void
    {
        Storage::fake('local');

        // Le moteur facture au mois ou à l'année : vendre « 5 ans » au prix d'un
        // an promettrait ce qui ne serait pas livré. Une commande sans durée
        // passe, et la période vendue est celle de l'offre.
        $domaine = $this->plan([
            'slug' => 'domaine-com', 'service' => 'domaine', 'nom' => 'Domaine .com',
            'prix_mensuel' => null, 'prix_annuel' => 18,
        ]);

        $this->post(route('abonnements.store', $domaine), $this->formulaire([
            'domaine' => 'sainteanne.com',
        ]))->assertSessionHasNoErrors();

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertNull($commande->duree_annees);
        // Le plan n'a pas de tarif mensuel : le cycle retenu est l'annuel.
        $this->assertSame('annuel', $commande->cycle);
        $this->assertSame(18.0, (float) $commande->montant_ttc);
    }

    public function test_un_nom_de_domaine_est_obligatoire_pour_l_hebergement(): void
    {
        $plan = $this->plan(['slug' => 'heberge', 'service' => 'hebergement', 'nom' => 'Hébergement']);

        $this->post(route('abonnements.store', $plan), $this->formulaire([
            'domaine' => null,
            'domaine_origine' => null,
        ]))->assertSessionHasErrors('domaine');
    }

    public function test_la_confirmation_passe_par_la_session(): void
    {
        Storage::fake('local');
        $plan = $this->plan();

        $this->post(route('abonnements.store', $plan), $this->formulaire());

        $commande = CommandeAbonnement::firstOrFail();

        $this->withSession(['commande_id' => $commande->id])
            ->get(route('abonnements.merci'))
            ->assertOk()
            ->assertSee($commande->reference)
            ->assertSee('Marie Joseph')
            ->assertSee('wa.me/50933988754', false);

        // Sans la session, aucune commande n'est affichée : une URL numérotée
        // se parcourrait de 1 à N.
        $this->flushSession();

        $this->get(route('abonnements.merci'))
            ->assertRedirect(route('abonnements.service', 'sites-web'));
    }

    // ── Devise de la passerelle ──────────────────────────

    public function test_le_total_est_converti_dans_la_devise_de_la_passerelle(): void
    {
        Storage::fake('local');
        $plan = $this->plan();
        $this->natcash(['devises_supportees' => ['HTG']]);

        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 132.5,
            'applique_depuis' => now()->subDay(),
        ]);

        $this->post(route('abonnements.store', $plan), $this->formulaire());

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertSame('HTG', $commande->devise_paiement);
        $this->assertSame(132.5, (float) $commande->taux_change);
        $this->assertSame(3948.50, (float) $commande->montant_a_payer);
        // Le montant facturé reste celui du plan.
        $this->assertSame('USD', $commande->devise);
        $this->assertSame(29.80, (float) $commande->montant_ttc);

        // Le paiement ouvert demande bien des gourdes.
        $paiement = Paiement::firstOrFail();
        $this->assertSame('HTG', $paiement->devise);
        $this->assertSame(3948.50, (float) $paiement->montant);
    }

    public function test_une_passerelle_en_gourdes_est_masquee_sans_taux_regle(): void
    {
        $plan = $this->plan();
        $this->natcash(['devises_supportees' => ['HTG']]);

        // Aucun taux réglé par le super admin : convertir reviendrait à inventer
        // un montant que la comptabilité ne retrouverait pas.
        $this->get(route('abonnements.commande', $plan))
            ->assertOk()
            ->assertDontSee('NatCash');

        $this->post(route('abonnements.store', $plan), $this->formulaire())
            ->assertSessionHasErrors('moyen_paiement');
    }

    public function test_une_passerelle_qui_accepte_la_devise_du_plan_ne_convertit_pas(): void
    {
        $plan = $this->plan();
        $passerelle = $this->natcash(['devises_supportees' => ['USD', 'HTG']]);

        TauxChange::create([
            'devise_source' => 'USD', 'devise_cible' => 'HTG', 'taux' => 132.5,
            'applique_depuis' => now()->subDay(),
        ]);

        $tarif = app(TarifPasserelle::class)->pour($passerelle, 29.80, 'USD');

        $this->assertSame('USD', $tarif['devise']);
        $this->assertNull($tarif['taux']);
        $this->assertSame(29.80, $tarif['montant']);
    }

    public function test_une_passerelle_incomplete_n_est_pas_proposee(): void
    {
        $plan = $this->plan();

        // Unibank est semée sans numéro de compte : la proposer mènerait le
        // client jusqu'au paiement sans savoir où envoyer l'argent.
        $this->get(route('abonnements.commande', $plan))
            ->assertOk()
            ->assertDontSee('Unibank');
    }

    // ── Le verdict du paiement remonte à la commande ─────

    public function test_approuver_le_paiement_fait_passer_la_commande_en_paiement_recu(): void
    {
        Storage::fake('local');
        $plan = $this->plan();
        $admin = $this->admin();

        $this->post(route('abonnements.store', $plan), $this->formulaire());

        $commande = CommandeAbonnement::firstOrFail();
        $this->assertSame('paiement_attente', $commande->statut);

        app(PaiementService::class)->approuverManuel(Paiement::firstOrFail(), $admin->id);

        $this->assertSame('paiement_recu', $commande->refresh()->statut);
        $this->assertTrue($commande->estPayee());
    }

    public function test_rejeter_le_paiement_laisse_la_commande_ouverte(): void
    {
        Storage::fake('local');
        $plan = $this->plan();
        $admin = $this->admin();

        $this->post(route('abonnements.store', $plan), $this->formulaire());
        $commande = CommandeAbonnement::firstOrFail();

        app(PaiementService::class)->rejeter(Paiement::firstOrFail(), $admin->id, 'Capture illisible');

        // Annuler la commande priverait l'équipe d'un client qui va repayer.
        $this->assertSame('paiement_attente', $commande->refresh()->statut);
        $this->assertFalse($commande->estPayee());
    }

    public function test_un_retour_rejoue_ne_fait_pas_reculer_une_commande_livree(): void
    {
        $commande = $this->commandeDeTest(['statut' => 'livree']);
        $paiement = new Paiement(['reference' => 'PM-TEST-0001']);

        $commande->paiementReussi($paiement);
        $commande->paiementRejete($paiement);

        $this->assertSame('livree', $commande->refresh()->statut);
    }

    // ── Activation : commande → abonnement ───────────────

    public function test_activer_une_commande_payee_ouvre_l_abonnement_au_tarif_fige(): void
    {
        $plan = $this->plan(['tca_taux' => 10]);
        $commande = $this->commandeDeTest([
            'plan_id' => $plan->id, 'statut' => 'paiement_recu',
            'prix_unitaire' => 29.80, 'tca_taux' => 10,
        ]);

        // Le catalogue est révisé entre la commande et la mise en service.
        $plan->update(['prix_mensuel' => 99, 'tca_taux' => 25]);

        $abonnement = app(AbonnementService::class)->activerCommande($commande, $this->admin()->id);

        $this->assertSame(29.80, (float) $abonnement->prix_unitaire);
        $this->assertSame(10.0, (float) $abonnement->tca_taux);
        $this->assertSame('USD', $abonnement->devise);

        $commande->refresh();
        $this->assertSame('livree', $commande->statut);
        $this->assertSame($abonnement->id, $commande->abonnement_id);
        $this->assertNotNull($commande->client_id);
    }

    public function test_activer_deux_fois_ne_cree_qu_un_abonnement(): void
    {
        $plan = $this->plan();
        $commande = $this->commandeDeTest(['plan_id' => $plan->id, 'statut' => 'paiement_recu']);
        $service = app(AbonnementService::class);

        $premier = $service->activerCommande($commande, $this->admin()->id);
        $second = $service->activerCommande($commande->refresh(), $this->admin()->id);

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, Abonnement::count());
    }

    public function test_une_commande_impayee_n_active_rien(): void
    {
        $plan = $this->plan();
        $commande = $this->commandeDeTest(['plan_id' => $plan->id, 'statut' => 'paiement_attente']);

        $this->expectException(\DomainException::class);
        app(AbonnementService::class)->activerCommande($commande, $this->admin()->id);
    }

    public function test_une_offre_avec_essai_s_active_avant_le_paiement(): void
    {
        // L'essai est précisément le service rendu avant d'être payé.
        $plan = $this->plan(['essai_jours' => 14]);
        $commande = $this->commandeDeTest(['plan_id' => $plan->id, 'statut' => 'paiement_attente']);

        $abonnement = app(AbonnementService::class)->activerCommande($commande, $this->admin()->id);

        $this->assertSame('essai', $abonnement->statut);
    }

    public function test_une_commande_dont_le_plan_a_disparu_est_refusee_avec_un_motif(): void
    {
        $commande = $this->commandeDeTest(['plan_id' => null, 'statut' => 'paiement_recu']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/catalogue/');
        app(AbonnementService::class)->activerCommande($commande, $this->admin()->id);
    }

    public function test_le_client_existant_est_retrouve_par_son_adresse(): void
    {
        $plan = $this->plan();
        $client = Client::create([
            'name' => 'Boulangerie Sainte-Anne', 'type' => 'company',
            'email' => 'Marie@SainteAnne.ht', 'phone' => '+509 3712 4455',
        ]);

        $commande = $this->commandeDeTest([
            'plan_id' => $plan->id, 'statut' => 'paiement_recu',
            'email' => 'marie@sainteanne.ht',
        ]);

        $abonnement = app(AbonnementService::class)->activerCommande($commande, $this->admin()->id);

        // La casse de l'adresse ne doit pas créer un doublon de fiche client.
        $this->assertSame($client->id, $abonnement->client_id);
        $this->assertSame(1, Client::count());
    }

    public function test_un_client_cree_depuis_une_commande_respecte_l_enumeration_du_type(): void
    {
        $plan = $this->plan();

        $particulier = $this->commandeDeTest([
            'plan_id' => $plan->id, 'statut' => 'paiement_recu',
            'entreprise' => null, 'email' => 'jean@exemple.ht',
        ]);
        app(AbonnementService::class)->activerCommande($particulier, $this->admin()->id);
        $this->assertSame('individual', Client::where('email', 'jean@exemple.ht')->value('type'));

        $entreprise = $this->commandeDeTest([
            'plan_id' => $plan->id, 'statut' => 'paiement_recu',
            'entreprise' => 'Sainte-Anne SA', 'email' => 'contact@sainteanne.ht',
        ]);
        app(AbonnementService::class)->activerCommande($entreprise, $this->admin()->id);
        $this->assertSame('company', Client::where('email', 'contact@sainteanne.ht')->value('type'));
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_l_erp_des_commandes_est_protege(): void
    {
        $this->get(route('erp.commandes.index'))->assertRedirect();
        $this->get(route('erp.commandes.export'))->assertRedirect();
    }

    public function test_l_erp_liste_les_commandes_et_les_exporte(): void
    {
        $plan = $this->plan();
        $commande = $this->commandeDeTest(['plan_id' => $plan->id]);

        $this->actingAs($this->admin())
            ->get(route('erp.commandes.index'))
            ->assertOk()
            ->assertSee($commande->reference)
            ->assertSee('Boulangerie Sainte-Anne');

        $this->actingAs($this->admin())
            ->get(route('erp.commandes.show', $commande))
            ->assertOk()
            ->assertSee('sainteanne.ht');

        $reponse = $this->actingAs($this->admin())->get(route('erp.commandes.export'));
        $reponse->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($commande->reference, $reponse->streamedContent());
    }

    public function test_l_erp_refuse_de_marquer_livree_une_commande_sans_abonnement(): void
    {
        $commande = $this->commandeDeTest(['statut' => 'paiement_recu']);

        $this->actingAs($this->admin())
            ->patch(route('erp.commandes.update', $commande), ['statut' => 'livree'])
            ->assertSessionHasErrors('statut');

        $this->assertSame('paiement_recu', $commande->refresh()->statut);
    }

    public function test_le_bouton_d_activation_de_l_erp_ouvre_l_abonnement(): void
    {
        $plan = $this->plan();
        $commande = $this->commandeDeTest(['plan_id' => $plan->id, 'statut' => 'paiement_recu']);

        $this->actingAs($this->admin())
            ->post(route('erp.commandes.activer', $commande))
            ->assertRedirect();

        $this->assertSame(1, Abonnement::count());
        $this->assertNotNull($commande->refresh()->abonnement_id);
    }

    // ── WhatsApp ─────────────────────────────────────────

    public function test_le_message_whatsapp_porte_la_reference_et_le_montant(): void
    {
        $commande = $this->commandeDeTest();

        $this->assertStringContainsString($commande->reference, $commande->message_whatsapp);
        $this->assertStringContainsString('29,80 USD', $commande->message_whatsapp);
        $this->assertStringContainsString('sainteanne.ht', $commande->message_whatsapp);
        $this->assertStringStartsWith('https://wa.me/50933988754?text=', $commande->lien_whatsapp);
    }

    // ── Fabrique ─────────────────────────────────────────

    private function commandeDeTest(array $extra = []): CommandeAbonnement
    {
        return CommandeAbonnement::create(array_merge([
            'reference' => CommandeAbonnement::genererReference(),
            'service' => 'site_web', 'plan_nom' => 'Vitrine', 'cycle' => 'mensuel',
            'prix_unitaire' => 29.80, 'tca_taux' => 0, 'montant_ttc' => 29.80, 'devise' => 'USD',
            'devise_paiement' => 'USD', 'taux_change' => null, 'montant_a_payer' => 29.80,
            'nom_complet' => 'Marie Joseph', 'entreprise' => 'Boulangerie Sainte-Anne',
            'email' => 'marie@sainteanne.ht', 'whatsapp' => '+509 3712 4455',
            'domaine' => 'sainteanne.ht', 'domaine_origine' => 'a_enregistrer',
            'passerelle_nom' => 'NatCash', 'mode_paiement' => 'manuel',
            'statut' => 'nouvelle',
        ], $extra));
    }
}
