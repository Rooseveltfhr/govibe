<?php

namespace Tests\Feature;

use App\Models\ReservationLandry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandryTest extends TestCase
{
    use RefreshDatabase;

    private function reservation(array $extra = []): array
    {
        return array_merge([
            'nom_complet' => 'Marie Joseph',
            'whatsapp' => '+509 3712 4455',
            'adresse' => 'Rue Égalité, près du marché',
            'point_repere' => 'Maison bleue',

            'mode_service' => 'domicile',
            'instructions_recuperation' => 'Sonner au portail vert.',
            'mode_facturation' => 'unite',
            'quantite_vetements' => 20,
            'frequence' => 'hebdomadaire',

            'mode_paiement' => 'abonnement',
            'frais_inscription' => 'oui',
        ], $extra);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Roosevelt', 'email' => 'admin@govibeht.com',
            'password' => Hash::make('MotDePasse2026'), 'is_admin' => true,
        ]);
    }

    // ── La page ──────────────────────────────────────────

    public function test_la_page_porte_les_informations_de_la_campagne(): void
    {
        Carbon::setTestNow('2026-09-20');

        $this->get('/landry')
            ->assertOk()
            ->assertSee('LANDRY')
            ->assertSee('Service de lavage professionnel')
            ->assertSee('11 octobre 2026')
            ->assertSee('Ruelle Sajous')
            ->assertSee('1 000 HTG')
            ->assertSee('CONFIRMER MA RÉSERVATION');

        Carbon::setTestNow();
    }

    public function test_la_barre_de_progression_est_calculee_cote_serveur(): void
    {
        // À mi-parcours entre le 11 septembre et le 11 octobre.
        Carbon::setTestNow('2026-09-26');
        $contenu = $this->get('/landry')->getContent();

        // Rendue à sa largeur réelle : la barre reste juste sans JavaScript.
        $this->assertMatchesRegularExpression('/id="ldBarre" style="width:4[5-9]%|5[0-5]%/', $contenu);
        $this->assertStringContainsString('Il reste 15 jours', $contenu);

        Carbon::setTestNow();
    }

    public function test_apres_louverture_la_page_ne_promet_plus_une_date_a_venir(): void
    {
        Carbon::setTestNow('2026-10-20');

        $this->get('/landry')
            ->assertOk()
            ->assertSee('Les services sont officiellement ouverts')
            ->assertDontSee('RÉSERVEZ gratuitement avant');

        Carbon::setTestNow();
    }

    public function test_la_banniere_ne_casse_pas_sans_image(): void
    {
        // Aucun fichier d'en-tête n'existe : on affiche un aplat, pas une
        // image brisée.
        $this->assertFileDoesNotExist(public_path('images/landry-header.jpg'));

        $this->get('/landry')
            ->assertOk()
            ->assertSee('ld-banniere-texte', false)
            ->assertDontSee('landry-header.jpg');
    }

    // ── L'enregistrement ─────────────────────────────────

    public function test_enregistre_une_reservation_complete(): void
    {
        $this->post('/landry', $this->reservation())
            ->assertRedirect(route('landry.merci'));

        $r = ReservationLandry::sole();
        $this->assertMatchesRegularExpression('/^LD-\d{8}-[A-Z0-9]{4}$/', $r->reference);
        $this->assertSame('Marie Joseph', $r->nom_complet);
        $this->assertSame('domicile', $r->mode_service);
        $this->assertSame('Sonner au portail vert.', $r->instructions_recuperation);
        $this->assertSame(20, $r->quantite_vetements);
        $this->assertTrue($r->accepte_frais_inscription);
        $this->assertSame(1000.0, (float) $r->frais_inscription);
        $this->assertSame('nouvelle', $r->statut);
    }

    public function test_les_instructions_ne_sont_gardees_quen_domicile(): void
    {
        // Gardées sur une réservation au local, elles induiraient l'équipe en
        // erreur au moment de la tournée.
        $this->post('/landry', $this->reservation([
            'mode_service' => 'local',
            'instructions_recuperation' => 'Sonner au portail vert.',
        ]));

        $this->assertNull(ReservationLandry::sole()->instructions_recuperation);
    }

    public function test_la_quantite_nest_gardee_quau_tarif_a_lunite(): void
    {
        $this->post('/landry', $this->reservation([
            'mode_facturation' => 'poids',
            'quantite_vetements' => 20,
        ]));

        $this->assertNull(ReservationLandry::sole()->quantite_vetements);
    }

    public function test_refuser_les_frais_nempeche_pas_la_reservation(): void
    {
        // Un prospect qui veut d'abord des informations reste un prospect.
        $this->post('/landry', $this->reservation(['frais_inscription' => 'non']))
            ->assertRedirect(route('landry.merci'));

        $r = ReservationLandry::sole();
        $this->assertFalse($r->accepte_frais_inscription);
        $this->assertNull($r->frais_inscription);
    }

    public function test_exige_les_champs_obligatoires(): void
    {
        $this->post('/landry', [])->assertSessionHasErrors([
            'nom_complet', 'whatsapp', 'adresse',
            'mode_service', 'mode_facturation', 'frequence',
            'mode_paiement', 'frais_inscription',
        ]);

        $this->assertSame(0, ReservationLandry::count());
    }

    public function test_refuse_une_valeur_inventee(): void
    {
        foreach ([
            ['mode_service' => 'teleportation'],
            ['mode_facturation' => 'au_kilometre'],
            ['frequence' => 'jamais'],
            ['mode_paiement' => 'troc'],
        ] as $mauvais) {
            $this->post('/landry', $this->reservation($mauvais))
                ->assertSessionHasErrors(array_key_first($mauvais));
        }

        $this->assertSame(0, ReservationLandry::count());
    }

    // ── Confirmation et WhatsApp ─────────────────────────

    public function test_la_confirmation_montre_la_reservation(): void
    {
        $this->post('/landry', $this->reservation());
        $r = ReservationLandry::sole();

        $this->get(route('landry.merci'))
            ->assertOk()
            ->assertSee('Merci pour votre réservation')
            ->assertSee($r->reference)
            ->assertSee('Marie Joseph')
            ->assertSee('11 octobre 2026')
            ->assertSee('wa.me/50933988754', false);
    }

    public function test_la_confirmation_nest_pas_enumerable(): void
    {
        $this->get(route('landry.merci'))->assertRedirect(route('landry.index'));
    }

    public function test_le_message_whatsapp_porte_la_reservation(): void
    {
        $this->post('/landry', $this->reservation());
        $texte = rawurldecode(ReservationLandry::sole()->lien_whatsapp);

        foreach ([
            'Marie Joseph', 'Rue Égalité', 'Maison bleue',
            'À domicile', 'Par unité de vêtement', '20 vêtements',
            'Une fois par semaine', 'Abonnement', '1 000 HTG',
        ] as $attendu) {
            $this->assertStringContainsString($attendu, $texte);
        }
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_lerp_est_protege(): void
    {
        $this->post('/landry', $this->reservation());
        $r = ReservationLandry::sole();

        $this->get('/erp/landry')->assertRedirect(route('erp.login'));
        $this->get("/erp/landry/{$r->id}")->assertRedirect(route('erp.login'));
        $this->get('/erp/landry/export')->assertRedirect(route('erp.login'));
    }

    public function test_les_ecrans_erp_rendent(): void
    {
        $admin = $this->admin();
        $this->post('/landry', $this->reservation());
        $r = ReservationLandry::sole();

        $this->actingAs($admin)->get(route('erp.landry.index'))
            ->assertOk()->assertSee($r->reference)->assertSee('Marie Joseph');

        $this->actingAs($admin)->get(route('erp.landry.show', $r))
            ->assertOk()->assertSee('Rue Égalité')->assertSee('Sonner au portail vert');
    }

    public function test_un_agent_suit_la_reservation(): void
    {
        $admin = $this->admin();
        $this->post('/landry', $this->reservation());
        $r = ReservationLandry::sole();

        $this->actingAs($admin)->patch(route('erp.landry.update', $r), [
            'statut' => 'confirmee',
            'notes_internes' => 'Appelée, elle démarre le 11.',
        ])->assertRedirect();

        $r->refresh();
        $this->assertSame('confirmee', $r->statut);
        $this->assertSame($admin->id, $r->traitee_par);
        $this->assertNotNull($r->traitee_le);
    }

    public function test_lexport_csv_souvre_dans_excel(): void
    {
        $admin = $this->admin();
        $this->post('/landry', $this->reservation());

        $reponse = $this->actingAs($admin)->get(route('erp.landry.export'));
        $reponse->assertOk();

        $csv = $reponse->streamedContent();
        // BOM UTF-8 : sans lui Excel casse les accents.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Marie Joseph', $csv);
        $this->assertStringContainsString('Rue Égalité', $csv);
    }
}
