<?php

namespace Tests\Feature;

use App\Models\InscriptionSession;
use App\Models\PasserellePaiement;
use App\Models\SessionFormation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SessionFormationTest extends TestCase
{
    use RefreshDatabase;

    private function formation(): SessionFormation
    {
        return SessionFormation::where('slug', 'formation-ai')->sole();
    }

    private function moncash(): PasserellePaiement
    {
        $p = PasserellePaiement::firstOrNew(['code' => 'moncash']);
        $p->fill([
            'nom' => 'MonCash', 'type' => 'mobile_money',
            'numero_compte' => '34420793', 'actif' => true, 'ordre' => 1,
        ])->save();

        return $p;
    }

    private function inscription(array $extra = []): array
    {
        return array_merge([
            'nom_complet' => 'Jean Baptiste Louis',
            'whatsapp' => '+509 3712 4455',
            'mode' => 'online',
            'moyen_paiement' => 'moncash',
            'preuve' => UploadedFile::fake()->image('recu.png', 300, 500),
        ], $extra);
    }

    // ── La page ──────────────────────────────────────────

    public function test_la_formation_de_laffiche_est_livree(): void
    {
        $f = $this->formation();

        $this->assertSame('Grande Formation AI', $f->titre);
        $this->assertSame(2500.0, (float) $f->prix);
        $this->assertSame('HTG', $f->devise);
        $this->assertSame(['presentiel', 'online'], $f->modes);
        $this->assertCount(5, $f->modules);
        $this->assertContains('Prompt Engineering', $f->modules);
    }

    public function test_la_page_porte_les_informations_de_laffiche(): void
    {
        $this->moncash();

        $this->get('/formation/formation-ai')
            ->assertOk()
            ->assertSee('Grande Formation AI')
            ->assertSee('Ultra-pratique')
            ->assertSee('2 500 HTG')
            ->assertSee('19 septembre')
            ->assertSee('Ruelle Sajous')
            ->assertSee('Prompt Engineering')
            ->assertSee('Générer des Vidéos Virales avec AI')
            ->assertSee('Envoyer mon inscription');
    }

    public function test_le_formulaire_reste_court(): void
    {
        $this->moncash();
        $contenu = $this->get('/formation/formation-ai')->getContent();

        // Quatre réponses : nom, WhatsApp, mode, moyen — plus la capture.
        foreach (['nom_complet', 'whatsapp', 'mode', 'moyen_paiement', 'preuve'] as $champ) {
            $this->assertStringContainsString('name="'.$champ, $contenu);
        }

        // Les champs de l'inscription Academy n'ont rien à faire ici.
        foreach (['date_naissance', 'niveau_etude', 'profession', 'departement', 'objectif', 'attentes'] as $absent) {
            $this->assertStringNotContainsString('name="'.$absent.'"', $contenu);
        }
    }

    public function test_les_coordonnees_de_paiement_sont_dans_la_page(): void
    {
        $this->moncash();

        // Le participant doit pouvoir payer sans quitter le formulaire.
        $this->get('/formation/formation-ai')
            ->assertOk()
            ->assertSee('MonCash')
            ->assertSee('34420793');
    }

    public function test_une_formation_inactive_renvoie_404(): void
    {
        $this->formation()->update(['actif' => false]);
        $this->get('/formation/formation-ai')->assertNotFound();
    }

    public function test_une_formation_fermee_reste_lisible_mais_nnaccepte_plus(): void
    {
        // Une publicité en cours ne doit pas tomber sur une page morte.
        $this->formation()->update(['inscriptions_ouvertes' => false]);

        $this->get('/formation/formation-ai')
            ->assertOk()
            ->assertSee('inscriptions sont closes')
            ->assertDontSee('Envoyer mon inscription');

        $this->post('/formation/formation-ai', $this->inscription())->assertSessionHasErrors();
        $this->assertSame(0, InscriptionSession::count());
    }

    // ── L'inscription ────────────────────────────────────

    public function test_enregistre_une_inscription_complete(): void
    {
        $this->moncash();

        $this->post('/formation/formation-ai', $this->inscription())
            ->assertRedirect(route('formations.merci', $this->formation()));

        $i = InscriptionSession::sole();
        $this->assertMatchesRegularExpression('/^FM-\d{8}-[A-Z0-9]{4}$/', $i->reference);
        $this->assertSame('Jean Baptiste Louis', $i->nom_complet);
        $this->assertSame('online', $i->mode);
        $this->assertSame(2500.0, (float) $i->montant);
        $this->assertSame('MonCash', $i->moyen_paiement_nom);
        $this->assertSame('a_verifier', $i->statut);
    }

    public function test_fige_le_montant_du_jour_de_linscription(): void
    {
        $this->moncash();
        $this->post('/formation/formation-ai', $this->inscription());

        $this->formation()->update(['prix' => 5000]);

        $this->assertSame(2500.0, (float) InscriptionSession::sole()->montant);
    }

    public function test_range_la_preuve_sur_le_disque_prive(): void
    {
        $this->moncash();
        $this->post('/formation/formation-ai', $this->inscription());
        $i = InscriptionSession::sole();

        $this->assertStringStartsWith('preuves-formation/', $i->fichier);
        $this->assertTrue(Storage::exists($i->fichier));
        $this->assertFalse(
            Storage::disk('public')->exists($i->fichier),
            'une preuve de paiement ne doit jamais être servie par une URL publique'
        );
    }

    public function test_exige_chaque_champ(): void
    {
        $this->moncash();

        $this->post('/formation/formation-ai', [])
            ->assertSessionHasErrors(['nom_complet', 'whatsapp', 'mode', 'moyen_paiement', 'preuve']);

        $this->assertSame(0, InscriptionSession::count());
    }

    public function test_refuse_un_svg_et_un_fichier_trop_lourd(): void
    {
        $this->moncash();

        $svg = UploadedFile::fake()->createWithContent(
            'x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );
        $this->post('/formation/formation-ai', $this->inscription(['preuve' => $svg]))
            ->assertSessionHasErrors('preuve');

        $gros = UploadedFile::fake()->create('gros.png', 9000, 'image/png');
        $this->post('/formation/formation-ai', $this->inscription(['preuve' => $gros]))
            ->assertSessionHasErrors('preuve');

        $this->assertSame(0, InscriptionSession::count());
    }

    public function test_refuse_un_mode_non_propose(): void
    {
        $this->moncash();
        $this->formation()->update(['modes' => ['online']]);

        $this->post('/formation/formation-ai', $this->inscription(['mode' => 'presentiel']))
            ->assertSessionHasErrors('mode');

        $this->assertSame(0, InscriptionSession::count());
    }

    // ── Confirmation et WhatsApp ─────────────────────────

    public function test_la_confirmation_offre_lenvoi_whatsapp_de_verification(): void
    {
        $this->moncash();
        $this->post('/formation/formation-ai', $this->inscription());
        $i = InscriptionSession::sole();

        $this->get(route('formations.merci', $this->formation()))
            ->assertOk()
            ->assertSee('Inscription reçue')
            ->assertSee($i->reference)
            ->assertSee('Envoyer pour vérification')
            ->assertSee('wa.me/50933988754', false);
    }

    public function test_le_message_whatsapp_porte_linscription(): void
    {
        $this->moncash();
        $this->post('/formation/formation-ai', $this->inscription());
        $i = InscriptionSession::sole();

        $this->assertStringStartsWith('https://wa.me/50933988754?text=', $i->lien_whatsapp);

        $texte = rawurldecode($i->lien_whatsapp);
        foreach ([
            $i->reference,
            'Jean Baptiste Louis',
            'En ligne',
            '2 500 HTG',
            'MonCash',
            'Grande Formation AI',
        ] as $attendu) {
            $this->assertStringContainsString($attendu, $texte);
        }
    }

    public function test_la_confirmation_nest_pas_enumerable(): void
    {
        $this->get(route('formations.merci', $this->formation()))
            ->assertRedirect(route('formations.show', $this->formation()));
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_lerp_et_la_preuve_sont_proteges(): void
    {
        $this->moncash();
        $this->post('/formation/formation-ai', $this->inscription());
        $i = InscriptionSession::sole();

        $this->get('/erp/formations')->assertStatus(302);
        $this->get("/erp/formations/{$i->id}")->assertStatus(302);
        $this->get("/erp/formations/{$i->id}/fichier")->assertStatus(302);
    }
}
