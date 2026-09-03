<?php

namespace Tests\Feature;

use App\Models\PasserellePaiement;
use App\Models\PreuvePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreuvePaiementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * MonCash est déjà créé par la migration des passerelles : on récupère
     * la ligne existante plutôt que d'en insérer une seconde.
     */
    private function moncash(): PasserellePaiement
    {
        $p = PasserellePaiement::firstOrNew(['code' => 'moncash']);
        $p->fill([
            'nom' => 'MonCash', 'type' => 'mobile_money',
            'numero_compte' => '34420793', 'actif' => true, 'ordre' => 1,
        ])->save();

        return $p;
    }

    private function envoi(array $extra = []): array
    {
        return array_merge([
            'nom' => 'Marie Joseph',
            'telephone' => '+509 3712 4455',
            'email' => 'marie@example.com',
            'moyen' => 'moncash',
            'montant' => '2500.50',
            'devise' => 'HTG',
            'transaction_id' => 'MC998877',
            'motif' => 'Inscription bootcamp',
            'note' => 'Payé ce matin.',
            'capture' => UploadedFile::fake()->image('recu.png', 300, 500),
        ], $extra);
    }

    public function test_enregistre_une_preuve_complete(): void
    {
        $this->moncash();

        $this->post('/paiement/preuve', $this->envoi())
            ->assertRedirect(route('paiement.preuve.merci'));

        $p = PreuvePaiement::sole();
        $this->assertMatchesRegularExpression('/^PP-\d{8}-[A-Z0-9]{4}$/', $p->reference);
        $this->assertSame('MonCash', $p->moyen_nom);
        $this->assertSame(2500.50, (float) $p->montant);
        $this->assertSame('recue', $p->statut);
        $this->assertGreaterThan(0, $p->fichier_taille);
    }

    public function test_range_la_capture_sur_le_disque_prive(): void
    {
        $this->post('/paiement/preuve', $this->envoi());
        $p = PreuvePaiement::sole();

        $this->assertStringStartsWith('preuves-paiement/', $p->fichier);
        $this->assertTrue(Storage::exists($p->fichier), 'la capture doit être sur le disque privé');
        $this->assertFalse(
            Storage::disk('public')->exists($p->fichier),
            'une preuve de paiement ne doit jamais être servie par une URL publique'
        );
    }

    public function test_construit_un_message_whatsapp_portant_la_reference(): void
    {
        $this->moncash();
        $this->post('/paiement/preuve', $this->envoi());
        $p = PreuvePaiement::sole();

        $lien = $p->lien_whatsapp;
        $this->assertStringStartsWith('https://wa.me/50933988754?text=', $lien);

        $texte = rawurldecode($lien);
        $this->assertStringContainsString($p->reference, $texte);
        $this->assertStringContainsString('Marie Joseph', $texte);
        $this->assertStringContainsString('MonCash', $texte);
        $this->assertStringContainsString('MC998877', $texte);
    }

    public function test_refuse_un_svg_qui_peut_porter_du_script(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->post('/paiement/preuve', $this->envoi(['capture' => $svg]))
            ->assertSessionHasErrors('capture');

        $this->assertSame(0, PreuvePaiement::count());
    }

    public function test_refuse_un_envoi_sans_capture(): void
    {
        $donnees = $this->envoi();
        unset($donnees['capture']);

        $this->post('/paiement/preuve', $donnees)->assertSessionHasErrors('capture');
        $this->assertSame(0, PreuvePaiement::count());
    }

    public function test_exige_le_nom_et_le_telephone(): void
    {
        $this->post('/paiement/preuve', ['capture' => UploadedFile::fake()->image('r.png')])
            ->assertSessionHasErrors(['nom', 'telephone']);

        $this->assertSame(0, PreuvePaiement::count());
    }

    public function test_refuse_un_fichier_au_dela_de_huit_mo(): void
    {
        $gros = UploadedFile::fake()->create('gros.png', 9000, 'image/png');

        $this->post('/paiement/preuve', $this->envoi(['capture' => $gros]))
            ->assertSessionHasErrors('capture');
    }

    public function test_ne_laisse_pas_parcourir_les_confirmations(): void
    {
        $this->get('/paiement/preuve/merci')->assertRedirect(route('paiement'));
    }

    public function test_affiche_la_confirmation_au_client_qui_vient_denvoyer(): void
    {
        $this->post('/paiement/preuve', $this->envoi());
        $p = PreuvePaiement::sole();

        $this->get(route('paiement.preuve.merci'))
            ->assertOk()
            ->assertSee($p->reference)
            ->assertSee('Marie Joseph')
            ->assertSee('wa.me/50933988754', false);
    }

    public function test_protege_la_capture_et_la_liste_derriere_lauth_erp(): void
    {
        $this->post('/paiement/preuve', $this->envoi());
        $p = PreuvePaiement::sole();

        $this->get('/erp/preuves')->assertStatus(302);
        $this->get("/erp/preuves/{$p->id}/fichier")->assertStatus(302);
    }

    public function test_affiche_le_bouton_denvoi_sur_la_page_de_paiement(): void
    {
        $this->moncash();

        $this->get('/paiement')
            ->assertOk()
            ->assertSee('Envoyer la preuve de paiement')
            ->assertSee(route('paiement.preuve'), false);
    }

    public function test_le_formulaire_repond_et_liste_les_moyens(): void
    {
        $this->moncash();

        $this->get('/paiement/preuve')
            ->assertOk()
            ->assertSee('Envoyer la preuve')
            ->assertSee('MonCash');
    }
}
