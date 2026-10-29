<?php

namespace Tests\Feature;

use App\Enquete\Questionnaire;
use App\Models\ReponseEnquete;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnqueteTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@govibeht.com'],
            ['name' => 'Roosevelt', 'password' => Hash::make('MotDePasse2026'), 'is_admin' => true],
        );
    }

    /** @return array<string, mixed> */
    private function reponses(array $extra = []): array
    {
        return array_merge([
            'entreprise' => 'Restaurant Sainte-Anne',
            'type_activite' => 'restaurant',
            'ville' => 'Gonaïves',
            'departement' => 'artibonite',
            'effectif' => '2_5',
            'anciennete' => '1_3',
            'outils' => ['papier', 'whatsapp'],
            'encaissements' => ['especes', 'moncash'],
            'connexion' => 'intermittente',
            'presences' => ['facebook'],
            'points_vente' => 'un',
            'volume' => '10_50',
            'materiel' => ['android', 'imprimante'],
            'essai' => 'jamais',
            'freins' => ['prix', 'courant'],
            'echeance' => 'trois_mois',
            'difficultes' => ['stock', 'caisse'],
            'perte_de_temps' => 'Compter le stock chaque soir.',
            'solutions' => ['caisse', 'stock'],
            'budget' => '2500_10000',
            'souhaite_contact' => '0',
        ], $extra);
    }

    // ── La page publique ─────────────────────────────────

    public function test_la_page_s_ouvre_en_francais_par_defaut(): void
    {
        $this->get('/enquete')
            ->assertOk()
            ->assertSee('Enquête sur vos besoins')
            ->assertSee('Pourquoi remplir ce formulaire ?')
            ->assertSee('Restaurant')
            // L'engagement de ne rien demander de secret est affiché.
            ->assertSee('ni mot de passe, ni code PIN', false);
    }

    public function test_la_page_bascule_entierement_en_creole(): void
    {
        $reponse = $this->get('/enquete?lang=ht');

        $reponse->assertOk()
            ->assertSee('Ankèt sou bezwen w')
            ->assertSee('Poukisa ou ta dwe ranpli fòmilè sa a ?')
            // Les options aussi, pas seulement les titres : un formulaire dont
            // les choix restent en français n'est pas traduit.
            ->assertSee('Restoran')
            ->assertSee('Kenkayri / Materyo')
            ->assertSee('Voye repons mwen yo')
            ->assertDontSee('Pourquoi remplir ce formulaire');
    }

    public function test_l_en_tete_est_court_et_le_contexte_se_deplie(): void
    {
        $reponse = $this->get('/enquete')->assertOk();

        // Le contexte long n'est plus dans l'en-tête : il vit dans un bloc
        // replié, pour qu'un mur de texte ne précède pas la première question.
        $reponse->assertSee('En savoir plus sur cette enquête')
            ->assertSee('<details', false)
            // Rien n'est perdu pour autant.
            ->assertSee('Pourquoi remplir ce formulaire ?');

        // L'engagement de confidentialité, lui, reste visible : c'est lui qui
        // décide si quelqu'un répond.
        $reponse->assertSee('ni mot de passe, ni code PIN', false);
    }

    public function test_une_langue_inconnue_retombe_sur_le_francais(): void
    {
        // Plutôt qu'une page vide ou une erreur pour un lien mal recopié.
        $this->get('/enquete?lang=es')->assertOk()->assertSee('Enquête sur vos besoins');
        $this->get('/enquete?lang=')->assertOk()->assertSee('Enquête sur vos besoins');
    }

    public function test_tous_les_types_d_activite_demandes_sont_proposes(): void
    {
        $types = Questionnaire::typesActivite();

        foreach (['bar', 'restaurant', 'hotel', 'boutique', 'transport',
            'quincaillerie', 'ong', 'institution', 'ecole', 'eglise', 'banque', 'autre'] as $cle) {
            $this->assertArrayHasKey($cle, $types, "Le type « {$cle} » manque au questionnaire.");
        }

        $reponse = $this->get('/enquete')->assertOk();

        foreach ($types as $libelle) {
            // Sans échappement du motif, « Grand'Anse » ne correspondrait pas :
            // Blade l'écrit « Grand&#039;Anse » dans la page.
            $reponse->assertSee($libelle['fr']);
        }
    }

    // ── Enregistrement ───────────────────────────────────

    public function test_une_reponse_complete_est_enregistree(): void
    {
        $this->post('/enquete', $this->reponses())
            ->assertRedirect(route('enquete.merci', ['lang' => 'fr']));

        $r = ReponseEnquete::firstOrFail();
        $this->assertMatchesRegularExpression('/^EQ-\d{8}-[A-Z0-9]{4}$/', $r->reference);
        $this->assertSame('restaurant', $r->type_activite);
        $this->assertSame(['stock', 'caisse'], $r->difficultes);
        $this->assertSame(['especes', 'moncash'], $r->encaissements);
        $this->assertSame('fr', $r->langue);
        $this->assertSame('nouvelle', $r->statut);
    }

    public function test_une_reponse_en_creole_enregistre_les_memes_cles(): void
    {
        $this->post('/enquete', $this->reponses(['lang' => 'ht']));

        $r = ReponseEnquete::firstOrFail();

        // Le point qui fait tenir tout le dépouillement : la langue change
        // l'écran, jamais la valeur. Sinon « Restaurant » et « Restoran »
        // compteraient comme deux activités différentes.
        $this->assertSame('ht', $r->langue);
        $this->assertSame('restaurant', $r->type_activite);
        $this->assertSame('Restoran', $r->typeLibelle('ht'));
        $this->assertSame('Restaurant', $r->typeLibelle('fr'));
    }

    public function test_au_moins_une_difficulte_est_exigee(): void
    {
        // C'est la question pour laquelle l'enquête existe.
        $this->post('/enquete', $this->reponses(['difficultes' => []]))
            ->assertSessionHasErrors('difficultes');

        $this->post('/enquete', $this->reponses(['difficultes' => null]))
            ->assertSessionHasErrors('difficultes');

        $this->assertSame(0, ReponseEnquete::count());
    }

    // ── Les questions ajoutées pour rendre l'enquête productive ──

    public function test_les_questions_cles_sont_enregistrees(): void
    {
        $this->post('/enquete', $this->reponses());

        $r = ReponseEnquete::firstOrFail();
        $this->assertSame('un', $r->points_vente);
        $this->assertSame('10_50', $r->volume);
        $this->assertSame(['android', 'imprimante'], $r->materiel);
        $this->assertSame(['prix', 'courant'], $r->freins);
        $this->assertSame('trois_mois', $r->echeance);
        $this->assertSame('jamais', $r->essai);
    }

    public function test_la_question_du_materiel_est_posee_dans_les_deux_langues(): void
    {
        // La plus déterminante du formulaire : aucun outil ne s'installe sur
        // du matériel que la personne n'a pas.
        $this->get('/enquete')->assertOk()
            ->assertSee('De quel matériel disposez-vous déjà ?')
            ->assertSee('Un téléphone Android')
            ->assertSee('Rien de tout cela');

        $this->get('/enquete?lang=ht')->assertOk()
            ->assertSee('Ki materyèl ou genyen deja ?')
            ->assertSee('Yon telefòn Android')
            ->assertSee('Anyen nan sa yo');
    }

    public function test_abandonner_un_outil_oblige_a_dire_pourquoi(): void
    {
        // C'est ce que l'enquête cherche le plus à apprendre : un outil
        // délaissé en dit plus long qu'une liste de souhaits.
        $this->post('/enquete', $this->reponses(['essai' => 'abandonne']))
            ->assertSessionHasErrors('essai_raison');

        $this->post('/enquete', $this->reponses([
            'essai' => 'abandonne',
            'essai_raison' => 'Trop compliqué, et personne pour nous former.',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(
            'Trop compliqué, et personne pour nous former.',
            ReponseEnquete::firstOrFail()->essai_raison
        );
    }

    public function test_la_raison_n_est_gardee_qu_avec_un_abandon(): void
    {
        // Gardée ailleurs, elle contredirait la réponse donnée.
        $this->post('/enquete', $this->reponses([
            'essai' => 'utilise',
            'essai_raison' => 'Trop compliqué.',
        ]))->assertSessionHasNoErrors();

        $this->assertNull(ReponseEnquete::firstOrFail()->essai_raison);
    }

    public function test_les_nouvelles_questions_restent_facultatives(): void
    {
        // Seules les difficultés sont obligatoires : alourdir le formulaire
        // ferait abandonner ceux qu'on cherche à entendre.
        $minimal = [
            'type_activite' => 'boutique',
            'difficultes' => ['stock'],
            'souhaite_contact' => '0',
        ];

        $this->post('/enquete', $minimal)->assertSessionHasNoErrors();

        $r = ReponseEnquete::firstOrFail();
        $this->assertNull($r->materiel);
        $this->assertNull($r->echeance);
        $this->assertNull($r->volume);
    }

    public function test_le_message_d_erreur_suit_la_langue_choisie(): void
    {
        $this->post('/enquete', $this->reponses(['lang' => 'ht', 'difficultes' => []]))
            ->assertSessionHasErrors(['difficultes' => 'Chwazi omwen yon difikilte — se kesyon ki pi enpòtan an.']);
    }

    public function test_une_valeur_inventee_est_refusee(): void
    {
        // Les listes sont fermées : une clé forgée polluerait le dépouillement.
        $this->post('/enquete', $this->reponses(['type_activite' => 'casino-de-monaco']))
            ->assertSessionHasErrors('type_activite');

        $this->post('/enquete', $this->reponses(['difficultes' => ['stock', 'inventee']]))
            ->assertSessionHasErrors('difficultes.1');

        $this->assertSame(0, ReponseEnquete::count());
    }

    public function test_autre_exige_une_precision_et_ne_la_garde_que_la(): void
    {
        $this->post('/enquete', $this->reponses(['type_activite' => 'autre']))
            ->assertSessionHasErrors('type_activite_autre');

        $this->post('/enquete', $this->reponses([
            'type_activite' => 'autre',
            'type_activite_autre' => 'Studio de photographie',
        ]))->assertSessionHasNoErrors();

        $r = ReponseEnquete::firstOrFail();
        // Sans la précision affichée, l'ERP montrerait « Autre » sur des
        // dizaines de fiches sans jamais dire de quelles activités il s'agit.
        $this->assertSame('Studio de photographie', $r->typeLibelle('fr'));

        ReponseEnquete::query()->delete();

        // La précision donnée avec un type connu ne doit pas être conservée :
        // elle contredirait le type choisi.
        $this->post('/enquete', $this->reponses([
            'type_activite' => 'hotel',
            'type_activite_autre' => 'Studio de photographie',
        ]));

        $this->assertNull(ReponseEnquete::firstOrFail()->type_activite_autre);
    }

    // ── Les coordonnées ──────────────────────────────────

    public function test_demander_un_rappel_exige_un_nom_et_un_numero(): void
    {
        $this->post('/enquete', $this->reponses(['souhaite_contact' => '1']))
            ->assertSessionHasErrors(['nom_contact', 'whatsapp']);
    }

    public function test_les_coordonnees_ne_sont_pas_gardees_quand_le_rappel_est_refuse(): void
    {
        // Les conserver après un « non » reviendrait à constituer un fichier
        // que la personne a refusé.
        $this->post('/enquete', $this->reponses([
            'souhaite_contact' => '0',
            'nom_contact' => 'Marie Joseph',
            'whatsapp' => '+509 3712 4455',
            'email' => 'marie@exemple.ht',
        ]))->assertSessionHasNoErrors();

        $r = ReponseEnquete::firstOrFail();
        $this->assertFalse($r->souhaite_contact);
        $this->assertNull($r->nom_contact);
        $this->assertNull($r->whatsapp);
        $this->assertNull($r->email);
    }

    public function test_les_coordonnees_sont_gardees_quand_le_rappel_est_accepte(): void
    {
        $this->post('/enquete', $this->reponses([
            'souhaite_contact' => '1',
            'nom_contact' => 'Marie Joseph',
            'whatsapp' => '+509 3712 4455',
            'email' => 'marie@exemple.ht',
        ]));

        $r = ReponseEnquete::firstOrFail();
        $this->assertTrue($r->souhaite_contact);
        $this->assertSame('Marie Joseph', $r->nom_contact);
    }

    // ── La confirmation ──────────────────────────────────

    public function test_la_confirmation_passe_par_la_session(): void
    {
        $this->post('/enquete', $this->reponses());
        $r = ReponseEnquete::firstOrFail();

        $this->withSession(['enquete_id' => $r->id])
            ->get(route('enquete.merci'))
            ->assertOk()
            ->assertSee($r->reference)
            ->assertSee('Merci, vos réponses sont enregistrées');

        // Sans la session, rien n'est affiché : la page porte le nom d'une
        // entreprise et ses difficultés, et une URL numérotée se parcourt.
        $this->flushSession();
        $this->get(route('enquete.merci'))->assertRedirect(route('enquete.show', ['lang' => 'fr']));
    }

    public function test_la_confirmation_reste_dans_la_langue_du_formulaire(): void
    {
        $this->post('/enquete', $this->reponses(['lang' => 'ht']));

        $this->withSession(['enquete_id' => ReponseEnquete::firstOrFail()->id])
            ->get(route('enquete.merci', ['lang' => 'ht']))
            ->assertOk()
            ->assertSee('Mèsi, repons ou yo anrejistre')
            ->assertDontSee('Merci, vos réponses sont enregistrées');
    }

    // ── Dépouillement ────────────────────────────────────

    public function test_les_frequences_comptent_les_reponses_de_toutes_les_langues(): void
    {
        $this->post('/enquete', $this->reponses(['difficultes' => ['stock', 'caisse']]));
        $this->post('/enquete', $this->reponses(['lang' => 'ht', 'difficultes' => ['stock']]));
        $this->post('/enquete', $this->reponses([
            'type_activite' => 'boutique', 'difficultes' => ['stock', 'clients'],
        ]));

        $frequences = ReponseEnquete::frequences('difficultes');

        $this->assertSame(3, $frequences['stock']);
        $this->assertSame(1, $frequences['caisse']);
        // Trié du plus fréquent au moins fréquent.
        $this->assertSame('stock', array_key_first($frequences));

        // Filtré par activité : c'est la lecture qui oriente le produit.
        $this->assertSame(['stock' => 2, 'caisse' => 1], ReponseEnquete::frequences('difficultes', 'restaurant'));
    }

    // ── ERP ──────────────────────────────────────────────

    public function test_l_erp_de_l_enquete_est_protege(): void
    {
        $this->get(route('erp.enquete.index'))->assertRedirect();
        $this->get(route('erp.enquete.export'))->assertRedirect();
    }

    public function test_l_erp_liste_depouille_et_exporte(): void
    {
        $this->post('/enquete', $this->reponses());
        $r = ReponseEnquete::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('erp.enquete.index'))
            ->assertOk()
            ->assertSee($r->reference)
            ->assertSee('Restaurant Sainte-Anne')
            // Le dépouillement, pas seulement la liste.
            ->assertSee('Ce qui coince le plus')
            ->assertSee('Ce qui empêcherait d\'adopter')
            ->assertSee('Le matériel déjà disponible');

        $this->actingAs($this->admin())
            ->get(route('erp.enquete.show', $r))
            ->assertOk()
            ->assertSee('Compter le stock chaque soir.');

        $export = $this->actingAs($this->admin())->get(route('erp.enquete.export'));
        $export->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $contenu = $export->streamedContent();
        $this->assertStringContainsString($r->reference, $contenu);
        // Export en français quelle que soit la langue de réponse : c'est la
        // langue de travail de l'équipe.
        $this->assertStringContainsString('Restaurant', $contenu);
    }

    public function test_ouvrir_une_fiche_la_marque_lue(): void
    {
        $this->post('/enquete', $this->reponses());
        $r = ReponseEnquete::firstOrFail();
        $this->assertSame('nouvelle', $r->statut);

        // Sans cela, le compteur des nouvelles ne descendrait jamais et
        // cesserait d'indiquer quoi que ce soit.
        $this->actingAs($this->admin())->get(route('erp.enquete.show', $r))->assertOk();

        $this->assertSame('lue', $r->refresh()->statut);
    }

    public function test_l_erp_filtre_sur_ceux_qui_veulent_etre_rappeles(): void
    {
        $this->post('/enquete', $this->reponses(['entreprise' => 'Sans rappel']));
        $this->post('/enquete', $this->reponses([
            'entreprise' => 'Avec rappel', 'souhaite_contact' => '1',
            'nom_contact' => 'Marie', 'whatsapp' => '+509 3712 4455',
        ]));

        $this->actingAs($this->admin())
            ->get(route('erp.enquete.index', ['rappel' => 'oui']))
            ->assertOk()
            ->assertSee('Avec rappel')
            ->assertDontSee('Sans rappel');
    }
}
