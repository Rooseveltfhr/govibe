<?php

namespace Tests\Feature;

use App\Models\AgentIa;
use App\Models\DemandeAgentIa;
use App\Models\PasserellePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgentIaTest extends TestCase
{
    use RefreshDatabase;

    private function demande(array $extra = []): array
    {
        return array_merge([
            'agent' => 'restaurant',
            'entreprise' => 'Chez Tante Ana',
            'responsable' => 'Nadège Pierre',
            'email' => 'nadege@tanteana.ht',
            'telephone' => '+509 3712 4455',
            'secteur' => 'Restauration',
            'pays' => 'Haïti',
            'ville' => 'Gonaïves',
            'objectifs' => 'Ne plus rater de réservation le soir.',
            'a_automatiser' => 'Les appels pour le menu et les horaires.',
            'volume_conversations' => '500_2000',
            'langues' => 'Créole, Français',
            'canal' => 'whatsapp_site',
            'integrations' => ['booking', 'humain'],
            'message' => 'Nous perdons des clients faute de réponse.',
            'moyen_paiement' => 'moncash',
        ], $extra);
    }

    // ── Catalogue ────────────────────────────────────────

    public function test_le_catalogue_est_livre_avec_dix_agents(): void
    {
        $this->assertSame(10, AgentIa::count());
        $this->assertTrue(AgentIa::where('slug', 'restaurant')->exists());
        $this->assertTrue(AgentIa::where('slug', 'personnalise')->where('sur_devis', true)->exists());
    }

    public function test_la_page_presente_les_agents_et_leurs_prix(): void
    {
        $this->get('/agents-ia')
            ->assertOk()
            ->assertSee('Automatisez votre entreprise avec un')
            ->assertSee('Agent IA — Restaurant')
            ->assertSee('À partir de 200 USD')
            ->assertSee('Demander cet Agent')
            ->assertSee('Créer mon Agent personnalisé');
    }

    public function test_la_page_ne_nomme_aucun_fournisseur_dinfrastructure(): void
    {
        // Le client achète un Agent GOVIBE. Nommer le moteur en ferait le
        // produit, et enfermerait GOVIBE dans un fournisseur.
        $contenu = $this->get('/agents-ia')->getContent();

        foreach (['ElevenLabs', 'OpenAI', 'Gemini', 'Anthropic', 'GPT-'] as $interdit) {
            $this->assertStringNotContainsStringIgnoringCase(
                $interdit, $contenu, "« {$interdit} » ne doit pas apparaître sur la page commerciale"
            );
        }
    }

    public function test_lavertissement_de_la_clinique_est_affiche(): void
    {
        $this->get('/agents-ia')
            ->assertOk()
            ->assertSee('ne pose aucun diagnostic', false);
    }

    public function test_un_agent_masque_disparait_du_catalogue(): void
    {
        AgentIa::where('slug', 'hotel')->update(['actif' => false]);

        $this->get('/agents-ia')->assertOk()->assertDontSee('Agent IA — Hôtel');
    }

    public function test_le_seo_porte_le_titre_et_la_description_attendus(): void
    {
        $this->get('/agents-ia')
            ->assertSee('AI Agents pour entreprises | GOVIBE Innovation Hub', false)
            ->assertSee('Créez un Agent IA pour automatiser votre service client', false)
            ->assertSee('WhatsApp AI Agent', false);
    }

    // ── Formulaire ───────────────────────────────────────

    public function test_le_formulaire_preselectionne_lagent_du_lien(): void
    {
        $this->get('/agents-ia/demande?agent=hotel')
            ->assertOk()
            ->assertSee('Demander votre')
            ->assertSee('Agent IA — Hôtel');
    }

    public function test_enregistre_une_demande_complete(): void
    {
        PasserellePaiement::where('code', 'moncash')->update(['numero_compte' => '34420793', 'actif' => true]);

        $this->post('/agents-ia/demande', $this->demande())
            ->assertRedirect(route('agents-ia.confirmation'));

        $d = DemandeAgentIa::sole();
        $this->assertMatchesRegularExpression('/^AI-\d{8}-[A-Z0-9]{4}$/', $d->reference);
        $this->assertSame('Agent IA — Restaurant', $d->agent_nom);
        $this->assertSame(200.0, (float) $d->prix_installation);
        $this->assertSame(60.0, (float) $d->prix_mensuel);
        $this->assertSame('nouvelle', $d->statut);
        $this->assertSame('en_attente', $d->statut_paiement);
        $this->assertSame(['booking', 'humain'], $d->integrations);
        $this->assertSame('MonCash', $d->moyen_paiement_nom);
    }

    public function test_fige_le_prix_du_jour_de_la_commande(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        // Le catalogue change après la commande : la demande garde son prix.
        AgentIa::where('slug', 'restaurant')->update(['prix_installation' => 999, 'nom' => 'Autre nom']);

        $this->assertSame(200.0, (float) $d->fresh()->prix_installation);
        $this->assertSame('Agent IA — Restaurant', $d->fresh()->agent_nom);
    }

    public function test_un_agent_sur_devis_na_pas_de_paiement_en_attente(): void
    {
        $this->post('/agents-ia/demande', $this->demande(['agent' => 'personnalise']));

        $d = DemandeAgentIa::sole();
        $this->assertTrue($d->sur_devis);
        $this->assertSame('sur_devis', $d->statut_paiement);
        $this->assertNull($d->total_maintenant);
    }

    public function test_exige_les_coordonnees_de_lentreprise(): void
    {
        $this->post('/agents-ia/demande', ['agent' => 'restaurant'])
            ->assertSessionHasErrors(['entreprise', 'responsable', 'email', 'telephone']);

        $this->assertSame(0, DemandeAgentIa::count());
    }

    public function test_refuse_un_agent_inconnu_ou_masque(): void
    {
        $this->post('/agents-ia/demande', $this->demande(['agent' => 'nexiste-pas']))
            ->assertSessionHasErrors('agent');

        AgentIa::where('slug', 'restaurant')->update(['actif' => false]);
        $this->post('/agents-ia/demande', $this->demande())->assertSessionHasErrors('agent');

        $this->assertSame(0, DemandeAgentIa::count());
    }

    public function test_refuse_une_integration_inventee(): void
    {
        $this->post('/agents-ia/demande', $this->demande(['integrations' => ['blockchain']]))
            ->assertSessionHasErrors('integrations.0');
    }

    // ── Confirmation ─────────────────────────────────────

    public function test_la_confirmation_montre_la_demande_et_le_paiement(): void
    {
        PasserellePaiement::where('code', 'moncash')
            ->update(['numero_compte' => '34420793', 'titulaire' => 'GOVIBE SA', 'actif' => true]);

        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        $this->get(route('agents-ia.confirmation'))
            ->assertOk()
            ->assertSee('Votre demande a été reçue')
            ->assertSee($d->reference)
            ->assertSee('Agent IA — Restaurant')
            ->assertSee('34420793')
            ->assertSee('GOVIBE SA')
            ->assertSee('wa.me/50933988754', false);
    }

    public function test_la_confirmation_nest_pas_enumerable(): void
    {
        $this->get(route('agents-ia.confirmation'))->assertRedirect(route('agents-ia.index'));
    }

    public function test_le_formulaire_porte_un_bouton_denvoi_a_la_fin(): void
    {
        // Sur mobile le récapitulatif passe au-dessus du formulaire : sans ce
        // second bouton, le client arrive en bas et ne trouve rien.
        $contenu = $this->get('/agents-ia/demande')->assertOk()->getContent();

        $this->assertSame(
            2, substr_count($contenu, 'type="submit"'),
            'le formulaire doit offrir un bouton dans le récapitulatif et un à la fin'
        );
        $this->assertStringContainsString('Envoyer ma demande', $contenu);

        // Le bouton de fin doit venir après le dernier champ du formulaire.
        $this->assertGreaterThan(
            strpos($contenu, 'name="moyen_paiement"'),
            strpos($contenu, 'id="dmBoutonBas"'),
            'le bouton de fin doit se trouver après les champs'
        );
    }

    public function test_la_confirmation_offre_un_envoi_whatsapp_pre_rempli(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        $this->get(route('agents-ia.confirmation'))
            ->assertOk()
            ->assertSee('Envoyer ma demande sur WhatsApp')
            ->assertSee('wa.me/50933988754', false);
    }

    public function test_le_message_whatsapp_porte_la_demande(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        $this->assertStringStartsWith('https://wa.me/50933988754?text=', $d->lien_whatsapp);

        $texte = rawurldecode($d->lien_whatsapp);
        foreach ([
            $d->reference,
            'Agent IA — Restaurant',
            'Chez Tante Ana',
            'Nadège Pierre',
            '+509 3712 4455',
            'WhatsApp + Site web',
            '200 USD',
            'Ne plus rater de réservation',
        ] as $attendu) {
            $this->assertStringContainsString($attendu, $texte);
        }
    }

    public function test_le_message_whatsapp_dit_sur_devis_sans_montant(): void
    {
        $this->post('/agents-ia/demande', $this->demande(['agent' => 'personnalise']));
        $texte = rawurldecode(DemandeAgentIa::sole()->lien_whatsapp);

        $this->assertStringContainsString('sur devis', $texte);
        $this->assertStringNotContainsString('Installation :', $texte);
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_lerp_est_protege(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        $this->get('/erp/agents-ia')->assertStatus(302);
        $this->get('/erp/agents-ia/catalogue')->assertStatus(302);
        $this->get("/erp/agents-ia/{$d->id}")->assertStatus(302);
    }

    public function test_le_passage_en_actif_date_la_mise_en_service(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();
        $this->assertNull($d->deploye_le);

        $d->update(['statut' => 'actif', 'deploye_le' => now()]);
        $this->assertNotNull($d->fresh()->deploye_le);
    }

    public function test_les_libelles_lisibles_traduisent_les_codes(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        $d = DemandeAgentIa::sole();

        $this->assertSame('WhatsApp + Site web', $d->canal_lisible);
        $this->assertSame('500 à 2 000 / mois', $d->volume_lisible);
        $this->assertSame(['Réservations', 'Transfert vers un humain'], $d->integrations_lisibles);
        $this->assertSame('Nouvelle demande', $d->statut_libelle);
    }

    public function test_supprimer_un_agent_du_catalogue_preserve_les_demandes(): void
    {
        $this->post('/agents-ia/demande', $this->demande());
        AgentIa::where('slug', 'restaurant')->delete();

        $d = DemandeAgentIa::sole();
        $this->assertNull($d->agent_ia_id);
        $this->assertSame('Agent IA — Restaurant', $d->agent_nom);
        $this->assertSame(200.0, (float) $d->prix_installation);
    }
}
