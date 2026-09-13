<?php

namespace Tests\Feature;

use App\Models\Paiement;
use App\Models\PasserellePaiement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Le back-office sur app.govibeht.com, le site public sur govibeht.com.
 *
 * Ces tests portent surtout sur ce qui casse quand on sépare deux domaines :
 * les requêtes qui portent un corps, les appels de serveur à serveur, et la
 * sonde de disponibilité.
 */
class SeparationDomainesTest extends TestCase
{
    use RefreshDatabase;

    private function isoler(): void
    {
        config([
            'govibe.domaine_app' => 'app.govibeht.com',
            'govibe.domaine_vitrine' => 'govibeht.com',
        ]);
    }

    // ── Interrupteur ─────────────────────────────────────

    public function test_sans_domaine_configure_les_deux_hotes_servent_tout(): void
    {
        config(['govibe.domaine_app' => null]);

        // Une isolation posée avant que le sous-domaine ne résolve enfermerait
        // l'équipe dehors de son propre ERP, sans moyen d'y revenir.
        $this->get('http://govibeht.com/erp/login')->assertOk();
        $this->get('http://app.govibeht.com/erp/login')->assertOk();
        $this->get('http://govibeht.com/')->assertOk();
        $this->get('http://app.govibeht.com/')->assertOk();
    }

    public function test_le_developpement_local_garde_son_acces(): void
    {
        $this->isoler();

        // Sans cette exception, plus personne ne peut travailler sur une
        // machine où le sous-domaine n'existe pas.
        $this->get('http://localhost/erp/login')->assertOk();
        $this->get('http://govibe.test/erp/login')->assertOk();
    }

    // ── Les deux sens ────────────────────────────────────

    public function test_le_back_office_demande_sur_la_vitrine_part_vers_le_domaine_applicatif(): void
    {
        $this->isoler();

        foreach (['/erp/login', '/erp/commandes', '/portail/connexion', '/portail/factures'] as $chemin) {
            $reponse = $this->get('http://govibeht.com'.$chemin);

            $reponse->assertStatus(302);
            $this->assertSame('https://app.govibeht.com'.$chemin, $reponse->headers->get('Location'));
        }
    }

    public function test_la_chaine_de_requete_survit_a_la_redirection(): void
    {
        $this->isoler();

        // Un lien de l'ERP porte ses filtres : les perdre renverrait l'agent
        // sur une liste entière au lieu de ce qu'il cherchait.
        $reponse = $this->get('http://govibeht.com/erp/commandes?statut=paiement_recu&q=sainteanne');

        $this->assertSame(
            'https://app.govibeht.com/erp/commandes?statut=paiement_recu&q=sainteanne',
            $reponse->headers->get('Location')
        );
    }

    public function test_une_page_publique_demandee_sur_le_domaine_applicatif_repart_vers_la_vitrine(): void
    {
        $this->isoler();

        // Sans cette règle, le site entier répondrait à deux adresses et les
        // moteurs de recherche verraient deux fois le même contenu.
        $reponse = $this->get('http://app.govibeht.com/sites-web');

        $reponse->assertStatus(302);
        $this->assertSame('https://govibeht.com/sites-web', $reponse->headers->get('Location'));
    }

    public function test_le_back_office_repond_normalement_sur_son_domaine(): void
    {
        $this->isoler();

        $this->get('http://app.govibeht.com/erp/login')->assertOk();
        $this->get('http://app.govibeht.com/portail/connexion')->assertOk();
        $this->get('http://www.app.govibeht.com/erp/login')->assertOk();
    }

    // ── Ce qui casse quand on redirige sans réfléchir ────

    public function test_une_requete_avec_corps_est_redirigee_en_308_pour_ne_pas_perdre_la_saisie(): void
    {
        $this->isoler();

        // Un 301 ou un 302 sur un POST le transforme en GET et jette le corps :
        // l'utilisateur voit son formulaire échouer sans comprendre pourquoi.
        // 308 est la seule redirection qui oblige à rejouer la même méthode.
        $reponse = $this->post('http://govibeht.com/erp/login', [
            'email' => 'admin@govibeht.com',
            'password' => 'MotDePasse2026',
        ]);

        $reponse->assertStatus(308);
        $this->assertSame('https://app.govibeht.com/erp/login', $reponse->headers->get('Location'));
    }

    public function test_la_notification_de_passerelle_n_est_jamais_redirigee(): void
    {
        $this->isoler();

        $passerelle = PasserellePaiement::where('code', 'moncash')->firstOrFail();
        $paiement = Paiement::create([
            'reference' => Paiement::genererReference(),
            'passerelle_id' => $passerelle->id,
            'mode' => 'api', 'pilote' => 'moncash', 'passerelle_nom' => 'MonCash',
            'montant' => 2500, 'devise' => 'HTG', 'statut' => 'en_attente',
            'cle_idempotence' => (string) Str::uuid(),
        ]);

        // Elle arrive d'un serveur tiers qui ne suit pas forcément une
        // redirection. Un verdict de paiement perdu, c'est un règlement
        // encaissé que personne ne constate.
        $this->post('http://app.govibeht.com/paiement/notification/'.$paiement->uuid)
            ->assertOk()
            ->assertJson(['recu' => true]);

        $this->post('http://govibeht.com/paiement/notification/'.$paiement->uuid)
            ->assertOk();
    }

    public function test_la_sonde_de_disponibilite_repond_sur_les_deux_hotes(): void
    {
        $this->isoler();

        // La rediriger ferait croire à une panne selon l'hôte surveillé.
        $this->get('http://govibeht.com/up')->assertOk();
        $this->get('http://app.govibeht.com/up')->assertOk();
    }

    // ── En-têtes de sécurité ─────────────────────────────

    public function test_les_entetes_de_securite_sont_poses_sur_chaque_reponse(): void
    {
        $reponse = $this->get('/');

        $reponse->assertHeader('X-Content-Type-Options', 'nosniff');
        $reponse->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $reponse->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_le_back_office_refuse_tout_cadre(): void
    {
        // Une page piégée qui superposerait un bouton invisible sur
        // « Approuver le paiement » ferait valider un règlement d'un clic.
        $this->get('/erp/login')->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/portail/connexion')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_est_eteint_par_defaut(): void
    {
        // Posé trop tôt, il rend le domaine inaccessible en clair pendant des
        // mois — y compris pendant un incident de certificat.
        $this->assertSame(0, (int) config('govibe.hsts_jours'));

        $this->get('https://govibeht.com/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_s_applique_une_fois_regle_et_seulement_en_https(): void
    {
        config(['govibe.hsts_jours' => 7]);

        $this->get('https://govibeht.com/')
            ->assertHeader('Strict-Transport-Security', 'max-age='.(7 * 86400));

        // En clair, l'en-tête n'a aucun sens : le navigateur l'ignore et le
        // poser laisserait croire qu'il protège.
        $this->get('http://govibeht.com/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
