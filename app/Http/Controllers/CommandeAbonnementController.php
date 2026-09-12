<?php

namespace App\Http\Controllers;

use App\Models\CommandeAbonnement;
use App\Models\PasserellePaiement;
use App\Models\Plan;
use App\Models\PreuvePaiement;
use App\Services\PaiementService;
use App\Services\TarifPasserelle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * L'offre publique des trois services vendus par abonnement, et la commande.
 *
 * Les tarifs ne sont pas écrits ici : ils viennent du catalogue de plans tenu
 * dans l'ERP. Un prix codé dans une vue finit par contredire la facture.
 */
class CommandeAbonnementController extends Controller
{
    public function __construct(
        private TarifPasserelle $tarifs,
        private PaiementService $paiements,
    ) {}

    /**
     * Page commerciale d'un service : ses offres publiées, et rien d'autre.
     *
     * Le segment d'URL est traduit en clé interne ici. La contrainte de route
     * n'accepte déjà que les trois segments connus ; la vérification reste,
     * pour que la méthode soit sûre appelée autrement.
     */
    public function index(string $segment)
    {
        $service = Plan::serviceDuSegment($segment);

        abort_if($service === null, 404);

        return view('abonnements.service', [
            'service' => $service,
            'segment' => $segment,
            'libelle' => Plan::services()[$service],
            'plans' => Plan::actif()->pourService($service)->get(),
            'presentation' => $this->presentation($service),
            'whatsapp' => preg_replace('/\D+/', '', (string) config('govibe.whatsapp_verification')),
        ]);
    }

    /** Formulaire de commande d'un plan précis. */
    public function commande(Request $request, Plan $plan)
    {
        abort_unless($plan->actif, 404);

        // Un plan sur devis ne se commande pas en ligne : son prix n'existe
        // pas encore. Le visiteur est renvoyé vers l'offre, qui porte le
        // bouton de demande de devis.
        if ($plan->sur_devis) {
            return redirect()->route('abonnements.service', $plan->segment);
        }

        $cycle = $this->cycleDemande($request, $plan);

        return view('abonnements.commande', $this->contexte($plan, $cycle));
    }

    public function store(Request $request, Plan $plan)
    {
        abort_unless($plan->actif && ! $plan->sur_devis, 404);

        $cycle = $this->cycleDemande($request, $plan);
        $codes = $this->codesDisponibles($plan, $cycle);

        $valide = $request->validate($this->regles($plan, $codes), $this->messages());

        $passerelle = PasserellePaiement::where('code', $valide['moyen_paiement'])->firstOrFail();

        // Le montant est recalculé à partir du catalogue et du taux en vigueur.
        // Celui affiché dans le formulaire a voyagé par le navigateur : s'y
        // fier laisserait un visiteur commander à son propre prix.
        $montants = $this->montants($plan, $cycle);
        $tarif = $this->tarifs->pour($passerelle, $montants['ttc'], $plan->devise);

        if ($tarif === null) {
            return back()->withInput()->withErrors([
                'moyen_paiement' => 'Ce moyen de paiement n\'est pas disponible pour cette offre. Choisissez-en un autre.',
            ]);
        }

        $commande = DB::transaction(function () use ($request, $valide, $plan, $cycle, $montants, $passerelle, $tarif) {
            $commande = CommandeAbonnement::create([
                'reference' => CommandeAbonnement::genererReference(),
                'plan_id' => $plan->id,
                'service' => $plan->service,
                'plan_nom' => $plan->nom,
                'cycle' => $cycle,

                'prix_unitaire' => $montants['ht'],
                'tca_taux' => $plan->tca_taux,
                'montant_ttc' => $montants['ttc'],
                'devise' => $plan->devise,

                'devise_paiement' => $tarif['devise'],
                'taux_change' => $tarif['taux'],
                'montant_a_payer' => $tarif['montant'],

                'nom_complet' => $valide['nom_complet'],
                'entreprise' => $valide['entreprise'] ?? null,
                'email' => $valide['email'],
                'whatsapp' => $valide['whatsapp'],

                'domaine' => $this->domaineRetenu($valide),
                // L'origine ne concerne que les services qui la posent : la
                // garder ailleurs ferait croire à un transfert à préparer.
                'domaine_origine' => $plan->service === 'hebergement' ? null : ($valide['domaine_origine'] ?? null),
                // Une durée en années n'a de sens que pour un nom de domaine.
                'duree_annees' => $plan->service === 'domaine' ? ($valide['duree_annees'] ?? null) : null,
                // La période vendue est celle du plan : l'équipe note ici une
                // durée plus longue si elle est convenue plus tard.
                'besoins' => $valide['besoins'] ?? null,

                'passerelle_id' => $passerelle->id,
                'passerelle_nom' => $passerelle->nom,
                'mode_paiement' => $passerelle->mode,

                'statut' => 'nouvelle',
                'ip' => $request->ip(),
            ]);

            // La preuve vient après la commande : son motif porte la référence
            // de celle-ci. C'est ce que l'agent lit dans la liste des preuves
            // pour savoir laquelle va avec quelle commande.
            $preuve = $request->hasFile('preuve')
                ? $this->enregistrerPreuve($request, $commande, $passerelle, $tarif)
                : null;

            if ($preuve) {
                $commande->forceFill(['preuve_paiement_id' => $preuve->id])->save();
            }

            $paiement = $this->paiements->ouvrir(
                $passerelle,
                $tarif['montant'],
                $tarif['devise'],
                $commande,
                null,
                $request->ip(),
            );

            if ($preuve) {
                $paiement->forceFill(['preuve_paiement_id' => $preuve->id])->save();
            }

            $commande->forceFill(['paiement_id' => $paiement->id])->save();

            return $commande;
        });

        // Passerelle automatique : le client part payer. Le verdict revient par
        // /paiement/retour, où la passerelle est réinterrogée — jamais lu dans
        // l'URL de retour.
        if ($passerelle->estApi()) {
            $resultat = $this->paiements->initier($commande->paiement);

            if ($resultat->reussi && $resultat->urlRedirection) {
                return redirect()->away($resultat->urlRedirection);
            }

            $commande->forceFill(['statut' => 'paiement_attente'])->save();

            return redirect()->route('abonnements.merci')
                ->with('commande_id', $commande->id)
                ->with('avertissement', $resultat->erreur
                    ?: 'Le paiement automatique n\'a pas pu démarrer. Notre équipe vous contacte pour finaliser.');
        }

        // Paiement manuel : la preuve est déjà reçue, un agent la vérifie.
        $commande->forceFill(['statut' => 'paiement_attente'])->save();

        // La confirmation passe par la session, jamais par un identifiant dans
        // l'URL : cette page porte le nom, l'adresse et le montant d'une
        // personne, et une URL numérotée se parcourt de 1 à N.
        return redirect()->route('abonnements.merci')->with('commande_id', $commande->id);
    }

    public function merci(Request $request)
    {
        $id = session('commande_id');
        $commande = $id ? CommandeAbonnement::find($id) : null;

        if (! $commande) {
            return redirect()->route('abonnements.service', Plan::segments()['site_web']);
        }

        // Rejouée telle quelle au rafraîchissement, sans repasser par l'envoi.
        $request->session()->reflash();

        return view('abonnements.merci', [
            'commande' => $commande,
            'passerelle' => $commande->passerelle,
            'avertissement' => session('avertissement'),
        ]);
    }

    // ── Calculs ──────────────────────────────────────────

    /**
     * Le cycle demandé, ou le seul que le plan propose.
     *
     * Un plan peut n'avoir qu'un tarif annuel : accepter « mensuel » mènerait à
     * un prix nul.
     */
    private function cycleDemande(Request $request, Plan $plan): string
    {
        $demande = $request->input('cycle', $request->query('cycle', 'mensuel'));

        if (! array_key_exists($demande, Plan::cycles()) || $plan->prixPour($demande) === null) {
            return $plan->prixPour('mensuel') !== null ? 'mensuel' : 'annuel';
        }

        return $demande;
    }

    /** @return array{ht: float, taxe: float, ttc: float} */
    private function montants(Plan $plan, string $cycle): array
    {
        $ht = (float) $plan->prixPour($cycle);
        $taxe = round($ht * ((float) $plan->tca_taux / 100), 2);

        return ['ht' => $ht, 'taxe' => $taxe, 'ttc' => round($ht + $taxe, 2)];
    }

    /** Ce que la vue du formulaire a besoin de connaître. */
    private function contexte(Plan $plan, string $cycle): array
    {
        $montants = $this->montants($plan, $cycle);

        return [
            'plan' => $plan,
            'segment' => $plan->segment,
            'cycle' => $cycle,
            'montants' => $montants,
            'moyens' => $this->tarifs->passerellesPour($montants['ttc'], $plan->devise),
            'presentation' => $this->presentation($plan->service),
            'whatsapp' => preg_replace('/\D+/', '', (string) config('govibe.whatsapp_verification')),
        ];
    }

    /** @return array<int, string> */
    private function codesDisponibles(Plan $plan, string $cycle): array
    {
        $montants = $this->montants($plan, $cycle);

        return array_map(
            fn ($m) => $m['passerelle']->code,
            $this->tarifs->passerellesPour($montants['ttc'], $plan->devise)
        );
    }

    // ── Validation ───────────────────────────────────────

    private function regles(Plan $plan, array $codes): array
    {
        $service = $plan->service;

        return [
            'nom_complet' => 'required|string|max:150',
            'entreprise' => 'nullable|string|max:150',
            'email' => 'required|email:filter|max:190',
            'whatsapp' => 'required|string|max:40',
            'cycle' => ['nullable', Rule::in(array_keys(Plan::cycles()))],

            // Le nom de domaine est ce qui sera enregistré ou hébergé : sans
            // lui, la commande n'est pas exécutable.
            'domaine' => [
                Rule::requiredIf(fn () => $service !== 'site_web'),
                'nullable', 'string', 'max:253',
            ],
            'domaine_origine' => [
                Rule::requiredIf(fn () => $service === 'site_web'),
                'nullable', Rule::in(array_keys(CommandeAbonnement::originesDomaine())),
            ],
            // La durée n'est pas demandée en ligne : le moteur d'abonnement
            // facture au mois ou à l'année, et vendre « 5 ans » au prix d'un an
            // promettrait ce qui ne serait pas livré. Une période plus longue se
            // publie comme une offre à son propre tarif, ou se convient avec
            // l'équipe — et la colonne la garde alors.
            'duree_annees' => ['nullable', 'integer', 'min:1', 'max:10'],
            'besoins' => 'nullable|string|max:2000',

            // Seules les passerelles réellement proposables sont acceptées :
            // une passerelle masquée faute de clés ou de taux ne doit pas
            // pouvoir être choisie en retapant son code.
            'moyen_paiement' => ['required', Rule::in($codes)],

            // La preuve n'est exigée que des moyens manuels : une passerelle
            // API confirme elle-même, et réclamer une capture avant de payer
            // serait impossible à fournir. Le SVG est refusé — il peut porter
            // du script exécuté par le navigateur de l'équipe.
            'preuve' => [
                Rule::requiredIf(function () use ($codes) {
                    $code = request('moyen_paiement');

                    if (! in_array($code, $codes, true)) {
                        return false;
                    }

                    return PasserellePaiement::where('code', $code)->value('mode') !== 'api';
                }),
                'nullable', 'file', 'mimes:jpeg,jpg,png,webp,heic,pdf', 'max:8192',
            ],
        ];
    }

    private function messages(): array
    {
        return [
            'nom_complet.required' => 'Votre nom est obligatoire.',
            'email.required' => 'Une adresse e-mail est nécessaire : elle porte vos accès et vos factures.',
            'email.email' => 'Cette adresse e-mail ne semble pas valide.',
            'whatsapp.required' => 'Votre numéro WhatsApp est obligatoire : c\'est par là que nous vous répondons.',
            'domaine.required' => 'Indiquez le nom de domaine concerné.',
            'domaine_origine.required' => 'Dites-nous où en est votre nom de domaine.',
            'moyen_paiement.required' => 'Choisissez votre moyen de paiement.',
            'moyen_paiement.in' => 'Ce moyen de paiement n\'est pas disponible. Choisissez-en un autre.',
            'preuve.required' => 'Ajoutez la capture d\'écran de votre paiement.',
            'preuve.mimes' => 'Envoyez une image (JPG, PNG, WEBP, HEIC) ou un PDF.',
            'preuve.max' => 'Le fichier dépasse 8 Mo. Réduisez-le avant de l\'envoyer.',
        ];
    }

    /**
     * Le domaine tel qu'on le cherchera : sans protocole, sans chemin, en
     * minuscules.
     *
     * Les gens collent « https://monentreprise.com/ » — gardé tel quel, ce
     * nom ne ressortirait sur aucune recherche et un doublon passerait
     * inaperçu. Aucun format n'est imposé en revanche : un client qui écrit
     * « monentreprise » sans extension veut justement qu'on l'aide à choisir.
     */
    private function domaineRetenu(array $valide): ?string
    {
        if (($valide['domaine_origine'] ?? null) === 'aucun') {
            return null;
        }

        $domaine = mb_strtolower(trim((string) ($valide['domaine'] ?? '')));
        $domaine = preg_replace('#^[a-z][a-z0-9+.\-]*://#', '', $domaine);
        $domaine = explode('/', $domaine)[0];
        $domaine = trim($domaine);

        return $domaine === '' ? null : $domaine;
    }

    private function enregistrerPreuve(
        Request $request,
        CommandeAbonnement $commande,
        PasserellePaiement $passerelle,
        array $tarif,
    ): PreuvePaiement {
        $fichier = $request->file('preuve');

        // Disque privé : une preuve de paiement porte des identifiants de
        // compte. Elle n'est lisible que par l'ERP, authentifié.
        $chemin = $fichier->store('preuves-paiement');

        return PreuvePaiement::create([
            'reference' => PreuvePaiement::genererReference(),
            'nom' => $commande->nom_complet,
            'telephone' => $commande->whatsapp,
            'email' => $commande->email,
            'moyen' => $passerelle->code,
            'moyen_nom' => $passerelle->nom,
            'montant' => $tarif['montant'],
            'devise' => $tarif['devise'],
            // La référence de la commande, et non le nom du client : c'est par
            // elle que l'agent relie la capture à ce qui a été acheté.
            'motif' => 'Commande '.$commande->reference.' — '.$commande->plan_nom,
            'fichier' => $chemin,
            'fichier_nom_origine' => $fichier->getClientOriginalName(),
            'fichier_taille' => $fichier->getSize(),
            'fichier_mime' => $fichier->getClientMimeType(),
            'ip' => $request->ip(),
        ]);
    }

    // ── Texte de présentation ────────────────────────────

    /**
     * Le discours commercial de chaque service. Les tarifs et les
     * caractéristiques viennent du catalogue, jamais d'ici.
     */
    private function presentation(string $service): array
    {
        return match ($service) {
            'site_web' => [
                'titre' => 'Votre site web, en ligne et entretenu',
                'accroche' => 'Un site professionnel sans achat de serveur, sans installation et sans maintenance à votre charge. Vous payez par mois, nous tenons la machine.',
                'points' => [
                    ['icone' => 'fa-rocket', 'titre' => 'Mise en ligne rapide', 'texte' => 'Votre site est installé, configuré et publié par notre équipe.'],
                    ['icone' => 'fa-shield-halved', 'titre' => 'Sauvegardes et sécurité', 'texte' => 'Certificat SSL, sauvegardes et mises à jour comprises dans l\'abonnement.'],
                    ['icone' => 'fa-mobile-screen', 'titre' => 'Lisible sur téléphone', 'texte' => 'La majorité de vos visiteurs arrivent par mobile : le site est fait pour eux.'],
                    ['icone' => 'fa-headset', 'titre' => 'Assistance en créole', 'texte' => 'Une équipe à Gonaïves, joignable sur WhatsApp.'],
                ],
                'cle' => 'Sites web',
            ],
            'hebergement' => [
                'titre' => 'Hébergement pour votre site ou votre application',
                'accroche' => 'L\'espace, la puissance et la bande passante dont votre projet a besoin — surveillé, sauvegardé, et facturé au mois.',
                'points' => [
                    ['icone' => 'fa-server', 'titre' => 'Ressources dédiées', 'texte' => 'Un espace délimité, avec la puissance annoncée dans votre offre.'],
                    ['icone' => 'fa-lock', 'titre' => 'SSL et pare-feu', 'texte' => 'Chiffrement activé et accès filtré dès la mise en service.'],
                    ['icone' => 'fa-clock-rotate-left', 'titre' => 'Sauvegardes régulières', 'texte' => 'Une copie récupérable : une erreur de manipulation n\'est pas une perte.'],
                    ['icone' => 'fa-right-left', 'titre' => 'Migration accompagnée', 'texte' => 'Votre site actuel est transféré par notre équipe.'],
                ],
                'cle' => 'Hébergement',
            ],
            default => [
                'titre' => 'Votre nom de domaine, enregistré et renouvelé',
                'accroche' => 'Le nom que vos clients tapent pour vous trouver. Enregistré à votre nom, renouvelé avant l\'échéance, géré depuis votre portail.',
                'points' => [
                    ['icone' => 'fa-id-card', 'titre' => 'Enregistré à votre nom', 'texte' => 'Le domaine vous appartient : GOVIBE le gère, ne le détient pas.'],
                    ['icone' => 'fa-bell', 'titre' => 'Renouvellement surveillé', 'texte' => 'Une échéance manquée fait perdre le nom : la date est suivie pour vous.'],
                    ['icone' => 'fa-envelope', 'titre' => 'Adresses e-mail au domaine', 'texte' => 'Une adresse à votre nom de domaine vaut mieux qu\'un compte gratuit.'],
                    ['icone' => 'fa-right-left', 'titre' => 'Transfert possible', 'texte' => 'Un domaine déjà enregistré ailleurs peut être transféré.'],
                ],
                'cle' => 'Noms de domaine',
            ],
        };
    }
}
