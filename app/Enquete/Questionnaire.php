<?php

namespace App\Enquete;

/**
 * Le questionnaire « Enquête sur vos besoins » : ses textes, ses listes de
 * réponses, dans les deux langues.
 *
 * Tout vit ici plutôt que dans les vues, pour une raison qui compte : la
 * valeur ENREGISTRÉE est une clé stable (« restaurant »), jamais un libellé.
 * Une réponse remplie en créole et une réponse remplie en français portent la
 * même clé, donc elles se comptent ensemble. Si chaque vue écrivait son propre
 * texte, un dépouillement trouverait « Restoran » et « Restaurant » comme deux
 * réponses différentes, et l'enquête ne servirait à rien.
 *
 * L'ERP relit ces mêmes listes pour afficher et exporter : les libellés ne
 * peuvent donc pas diverger entre le formulaire et le dépouillement.
 */
final class Questionnaire
{
    public const LANGUE_DEFAUT = 'fr';

    /** @return array<string, string> */
    public static function langues(): array
    {
        return ['fr' => 'Français', 'ht' => 'Kreyòl'];
    }

    public static function langueValide(?string $langue): string
    {
        return array_key_exists((string) $langue, self::langues())
            ? (string) $langue
            : self::LANGUE_DEFAUT;
    }

    /**
     * Le libellé d'une clé dans la langue demandée.
     *
     * Une clé inconnue est rendue telle quelle plutôt que vidée : une réponse
     * dont le libellé a disparu du catalogue reste lisible dans l'ERP.
     */
    public static function libelle(array $liste, ?string $cle, string $langue): ?string
    {
        if ($cle === null || $cle === '') {
            return null;
        }

        return $liste[$cle][$langue] ?? $liste[$cle]['fr'] ?? $cle;
    }

    /** @param array<int, string>|null $cles */
    public static function libelles(array $liste, ?array $cles, string $langue): array
    {
        return array_values(array_filter(array_map(
            fn ($cle) => self::libelle($liste, $cle, $langue),
            $cles ?? []
        )));
    }

    // ── Listes de réponses ───────────────────────────────

    /**
     * Les types d'activité demandés par Roosevelt, plus ceux qui pèsent dans
     * le commerce haïtien et qu'une liste trop courte laisserait sous
     * « Autre » — où ils deviennent impossibles à compter.
     */
    public static function typesActivite(): array
    {
        return [
            'restaurant' => ['fr' => 'Restaurant', 'ht' => 'Restoran'],
            'bar' => ['fr' => 'Bar / Discothèque', 'ht' => 'Ba / Diskotèk'],
            'hotel' => ['fr' => 'Hôtel / Guest house', 'ht' => 'Otèl / Gest hawuz'],
            'boutique' => ['fr' => 'Boutique / Commerce de détail', 'ht' => 'Boutik / Komès an detay'],
            'supermarche' => ['fr' => 'Supermarché / Épicerie', 'ht' => 'Sipèmakèt / Episri'],
            'quincaillerie' => ['fr' => 'Quincaillerie / Matériaux', 'ht' => 'Kenkayri / Materyo'],
            'pharmacie' => ['fr' => 'Pharmacie', 'ht' => 'Famasi'],
            'transport' => ['fr' => 'Transport / Logistique', 'ht' => 'Transpò / Lojistik'],
            'ecole' => ['fr' => 'École / Université', 'ht' => 'Lekòl / Inivèsite'],
            'eglise' => ['fr' => 'Église / Organisation religieuse', 'ht' => 'Legliz / Òganizasyon relijye'],
            'ong' => ['fr' => 'ONG / Association', 'ht' => 'ONG / Asosyasyon'],
            'institution' => ['fr' => 'Institution publique', 'ht' => 'Enstitisyon piblik'],
            'banque' => ['fr' => 'Banque / Microfinance / Caisse', 'ht' => 'Bank / Mikwofinans / Kès'],
            'sante' => ['fr' => 'Clinique / Cabinet de santé', 'ht' => 'Klinik / Kabinè sante'],
            'salon' => ['fr' => 'Salon de beauté / Barbier', 'ht' => 'Salon bote / Bab'],
            'atelier' => ['fr' => 'Atelier / Garage / Artisanat', 'ht' => 'Atelye / Garaj / Atizana'],
            'agriculture' => ['fr' => 'Agriculture / Élevage', 'ht' => 'Agrikilti / Elvaj'],
            'construction' => ['fr' => 'Construction / BTP', 'ht' => 'Konstriksyon / BTP'],
            'immobilier' => ['fr' => 'Immobilier', 'ht' => 'Imobilye'],
            'media' => ['fr' => 'Média / Communication / Imprimerie', 'ht' => 'Medya / Kominikasyon / Enprimri'],
            'technologie' => ['fr' => 'Technologie / Informatique', 'ht' => 'Teknoloji / Enfòmatik'],
            'services_pro' => ['fr' => 'Services professionnels (cabinet, conseil)', 'ht' => 'Sèvis pwofesyonèl (kabinè, konsèy)'],
            'evenementiel' => ['fr' => 'Événementiel / Traiteur', 'ht' => 'Evènmansyèl / Tretè'],
            'blanchisserie' => ['fr' => 'Blanchisserie / Pressing', 'ht' => 'Lesivaj / Presing'],
            'transfert' => ['fr' => 'Transfert d\'argent / Change', 'ht' => 'Transfè lajan / Chanj'],
            'autre' => ['fr' => 'Autre', 'ht' => 'Lòt'],
        ];
    }

    public static function effectifs(): array
    {
        return [
            'seul' => ['fr' => 'Je travaille seul(e)', 'ht' => 'M ap travay pou kont mwen'],
            '2_5' => ['fr' => '2 à 5 personnes', 'ht' => '2 a 5 moun'],
            '6_20' => ['fr' => '6 à 20 personnes', 'ht' => '6 a 20 moun'],
            '21_50' => ['fr' => '21 à 50 personnes', 'ht' => '21 a 50 moun'],
            'plus_50' => ['fr' => 'Plus de 50 personnes', 'ht' => 'Plis pase 50 moun'],
        ];
    }

    public static function anciennetes(): array
    {
        return [
            'projet' => ['fr' => 'Pas encore ouvert — en projet', 'ht' => 'Poko louvri — nan pwojè'],
            'moins_1' => ['fr' => 'Moins d\'un an', 'ht' => 'Mwens pase yon ane'],
            '1_3' => ['fr' => '1 à 3 ans', 'ht' => '1 a 3 ane'],
            '4_10' => ['fr' => '4 à 10 ans', 'ht' => '4 a 10 ane'],
            'plus_10' => ['fr' => 'Plus de 10 ans', 'ht' => 'Plis pase 10 ane'],
        ];
    }

    /** Comment l'activité est tenue aujourd'hui. */
    public static function outils(): array
    {
        return [
            'papier' => ['fr' => 'Cahier, carnet, papier', 'ht' => 'Kaye, kanè, papye'],
            'tete' => ['fr' => 'De mémoire, rien d\'écrit', 'ht' => 'Nan tèt mwen, anyen ekri'],
            'whatsapp' => ['fr' => 'WhatsApp', 'ht' => 'WhatsApp'],
            'excel' => ['fr' => 'Excel ou Google Sheets', 'ht' => 'Excel oswa Google Sheets'],
            'caisse' => ['fr' => 'Une caisse enregistreuse', 'ht' => 'Yon kès anrejistrè'],
            'logiciel' => ['fr' => 'Un logiciel de gestion', 'ht' => 'Yon lojisyèl jesyon'],
            'application' => ['fr' => 'Une application mobile', 'ht' => 'Yon aplikasyon mobil'],
            'comptable' => ['fr' => 'Un comptable externe', 'ht' => 'Yon kontab deyò'],
        ];
    }

    public static function encaissements(): array
    {
        return [
            'especes' => ['fr' => 'Espèces', 'ht' => 'Kach'],
            'moncash' => ['fr' => 'MonCash', 'ht' => 'MonCash'],
            'natcash' => ['fr' => 'NatCash', 'ht' => 'NatCash'],
            'virement' => ['fr' => 'Virement / chèque bancaire', 'ht' => 'Vireman / chèk labank'],
            'carte' => ['fr' => 'Carte bancaire', 'ht' => 'Kat labank'],
            'transfert_intl' => ['fr' => 'Transfert international (Zelle, PayPal…)', 'ht' => 'Transfè entènasyonal (Zelle, PayPal…)'],
            'credit' => ['fr' => 'À crédit, le client paie plus tard', 'ht' => 'Sou kredi, kliyan an peye pita'],
        ];
    }

    public static function connexions(): array
    {
        return [
            'stable' => ['fr' => 'Oui, stable', 'ht' => 'Wi, estab'],
            'intermittente' => ['fr' => 'Par moments seulement', 'ht' => 'Sèlman tanzantan'],
            'mobile' => ['fr' => 'Seulement par données mobiles', 'ht' => 'Sèlman ak done telefòn'],
            'aucune' => ['fr' => 'Pas d\'internet sur place', 'ht' => 'Pa gen entènèt sou plas'],
        ];
    }

    public static function presences(): array
    {
        return [
            'site' => ['fr' => 'Un site web', 'ht' => 'Yon sit entènèt'],
            'facebook' => ['fr' => 'Une page Facebook', 'ht' => 'Yon paj Facebook'],
            'instagram' => ['fr' => 'Un compte Instagram', 'ht' => 'Yon kont Instagram'],
            'whatsapp_business' => ['fr' => 'WhatsApp Business', 'ht' => 'WhatsApp Business'],
            'tiktok' => ['fr' => 'TikTok', 'ht' => 'TikTok'],
            'aucune' => ['fr' => 'Rien en ligne pour le moment', 'ht' => 'Anyen sou entènèt pou kounye a'],
        ];
    }

    /** Le cœur de l'enquête : ce qui coince vraiment. */
    public static function difficultes(): array
    {
        return [
            'stock' => ['fr' => 'Suivre le stock, savoir ce qui reste', 'ht' => 'Swiv stòk la, konnen sa ki rete'],
            'ventes' => ['fr' => 'Savoir ce que je vends vraiment, jour par jour', 'ht' => 'Konnen sa m vann vre, jou pa jou'],
            'caisse' => ['fr' => 'Contrôler la caisse, éviter les pertes', 'ht' => 'Kontwole kès la, evite pèt'],
            'clients' => ['fr' => 'Garder la trace de mes clients', 'ht' => 'Kenbe tras kliyan m yo'],
            'credits' => ['fr' => 'Récupérer l\'argent que les clients me doivent', 'ht' => 'Rekipere lajan kliyan dwe m'],
            'employes' => ['fr' => 'Suivre les employés, présences, paie', 'ht' => 'Swiv anplwaye, prezans, peman'],
            'comptabilite' => ['fr' => 'Comptabilité, savoir si je gagne ou je perds', 'ht' => 'Kontablite, konnen si m ap genyen ou pèdi'],
            'factures' => ['fr' => 'Faire des factures et des reçus propres', 'ht' => 'Fè fakti ak resi ki pwòp'],
            'paiements' => ['fr' => 'Encaisser facilement, accepter les paiements', 'ht' => 'Ankese fasil, aksepte peman'],
            'commandes' => ['fr' => 'Prendre et suivre les commandes', 'ht' => 'Pran ak swiv kòmand'],
            'livraison' => ['fr' => 'Organiser les livraisons', 'ht' => 'Òganize livrezon'],
            'fournisseurs' => ['fr' => 'Gérer les fournisseurs et les achats', 'ht' => 'Jere founisè ak acha'],
            'clients_nouveaux' => ['fr' => 'Trouver de nouveaux clients', 'ht' => 'Jwenn nouvo kliyan'],
            'communication' => ['fr' => 'Me faire connaître, communiquer en ligne', 'ht' => 'Fè moun konnen m, kominike sou entènèt'],
            'rendez_vous' => ['fr' => 'Gérer les rendez-vous ou les réservations', 'ht' => 'Jere randevou oswa rezèvasyon'],
            'documents' => ['fr' => 'Retrouver mes documents et mes dossiers', 'ht' => 'Jwenn dokiman ak dosye m yo'],
            'electricite' => ['fr' => 'Courant et internet qui coupent', 'ht' => 'Kouran ak entènèt ki koupe'],
            'personnel_forme' => ['fr' => 'Trouver du personnel formé', 'ht' => 'Jwenn moun ki fòme'],
        ];
    }

    public static function solutions(): array
    {
        return [
            'gestion' => ['fr' => 'Un logiciel de gestion pour mon activité', 'ht' => 'Yon lojisyèl jesyon pou aktivite m'],
            'caisse' => ['fr' => 'Une caisse / point de vente', 'ht' => 'Yon kès / pwen lavant'],
            'stock' => ['fr' => 'Un suivi de stock', 'ht' => 'Yon swivi stòk'],
            'application' => ['fr' => 'Une application mobile', 'ht' => 'Yon aplikasyon mobil'],
            'site' => ['fr' => 'Un site web', 'ht' => 'Yon sit entènèt'],
            'paiement' => ['fr' => 'Encaisser par paiement digital', 'ht' => 'Ankese ak peman dijital'],
            'facturation' => ['fr' => 'Facturation et reçus automatiques', 'ht' => 'Fakti ak resi otomatik'],
            'clients' => ['fr' => 'Un fichier clients / fidélité', 'ht' => 'Yon fichye kliyan / fidelite'],
            'commandes_ligne' => ['fr' => 'Commandes et livraison en ligne', 'ht' => 'Kòmand ak livrezon sou entènèt'],
            'rh' => ['fr' => 'Gestion du personnel et de la paie', 'ht' => 'Jesyon pèsonèl ak peman'],
            'ia' => ['fr' => 'Intelligence artificielle, assistant automatique', 'ht' => 'Entèlijans atifisyèl, asistan otomatik'],
            'automatisation' => ['fr' => 'Automatiser les tâches répétitives', 'ht' => 'Otomatize travay k ap repete'],
            'formation' => ['fr' => 'Former mon équipe aux outils', 'ht' => 'Fòme ekip mwen sou zouti yo'],
            'conseil' => ['fr' => 'Être conseillé avant de choisir', 'ht' => 'Jwenn konsèy anvan m chwazi'],
        ];
    }

    public static function budgets(): array
    {
        return [
            'inconnu' => ['fr' => 'Je ne sais pas encore', 'ht' => 'M poko konnen'],
            'moins_2500' => ['fr' => 'Moins de 2 500 HTG / mois', 'ht' => 'Mwens pase 2 500 HTG / mwa'],
            '2500_10000' => ['fr' => '2 500 à 10 000 HTG / mois', 'ht' => '2 500 a 10 000 HTG / mwa'],
            '10000_25000' => ['fr' => '10 000 à 25 000 HTG / mois', 'ht' => '10 000 a 25 000 HTG / mwa'],
            'plus_25000' => ['fr' => 'Plus de 25 000 HTG / mois', 'ht' => 'Plis pase 25 000 HTG / mwa'],
            'selon_resultat' => ['fr' => 'Selon ce que la solution rapporte', 'ht' => 'Selon sa solisyon an rapòte'],
        ];
    }

    public static function statuts(): array
    {
        return [
            'nouvelle' => ['fr' => 'Nouvelle', 'ht' => 'Nouvo'],
            'lue' => ['fr' => 'Lue', 'ht' => 'Li'],
            'contactee' => ['fr' => 'Recontactée', 'ht' => 'Kontakte'],
            'archivee' => ['fr' => 'Archivée', 'ht' => 'Achive'],
        ];
    }

    /**
     * Les dix départements d'Haïti. Une liste plutôt qu'un champ libre : c'est
     * sur elle que se fera la carte des besoins par région, et « Ouest »,
     * « ouest » et « O. » ne se regrouperaient pas tout seuls.
     */
    public static function departements(): array
    {
        return [
            'ouest' => ['fr' => 'Ouest', 'ht' => 'Lwès'],
            'artibonite' => ['fr' => 'Artibonite', 'ht' => 'Latibonit'],
            'nord' => ['fr' => 'Nord', 'ht' => 'Nò'],
            'nord_est' => ['fr' => 'Nord-Est', 'ht' => 'Nòdès'],
            'nord_ouest' => ['fr' => 'Nord-Ouest', 'ht' => 'Nòdwès'],
            'centre' => ['fr' => 'Centre', 'ht' => 'Sant'],
            'sud' => ['fr' => 'Sud', 'ht' => 'Sid'],
            'sud_est' => ['fr' => 'Sud-Est', 'ht' => 'Sidès'],
            'grand_anse' => ['fr' => 'Grand\'Anse', 'ht' => 'Grandans'],
            'nippes' => ['fr' => 'Nippes', 'ht' => 'Nip'],
            'diaspora' => ['fr' => 'Hors d\'Haïti', 'ht' => 'Deyò peyi a'],
        ];
    }

    // ── Textes de la page ────────────────────────────────

    /** @return array<string, string> */
    public static function textes(string $langue): array
    {
        return self::tousLesTextes()[$langue] ?? self::tousLesTextes()[self::LANGUE_DEFAUT];
    }

    private static function tousLesTextes(): array
    {
        return [
            'fr' => [
                'titre_page' => 'Enquête sur vos besoins | GOVIBE Innovation Hub',
                'meta' => 'GOVIBE Innovation Hub écoute les entreprises haïtiennes : dites-nous les difficultés de votre activité, nous construisons les solutions digitales qui y répondent.',

                'bandeau' => 'Enquête sur vos besoins',
                'titre' => 'Aidons les entreprises haïtiennes à devenir plus performantes grâce à la technologie',
                'accroche' => 'GOVIBE Innovation Hub écoute les entreprises haïtiennes.',
                'intro_1' => 'Vous dirigez une entreprise, une école, un hôtel, un restaurant, une boutique, une agence ou toute autre activité ?',
                'intro_2' => 'Nous voulons comprendre les vraies difficultés que vous rencontrez chaque jour, ce qui vous fait perdre du temps, des clients ou de l\'argent, et ce que vous aimeriez améliorer dans votre activité.',
                'intro_3' => 'Notre objectif est de développer des solutions digitales adaptées à la réalité des entreprises haïtiennes : logiciels de gestion, applications mobiles, paiement digital, automatisation, intelligence artificielle et autres outils technologiques.',

                'pourquoi_titre' => 'Pourquoi remplir ce formulaire ?',
                'pourquoi_1' => 'Pour nous aider à identifier les problèmes réels de votre entreprise.',
                'pourquoi_2' => 'Pour nous aider à développer des solutions qui répondent à vos besoins.',
                'pourquoi_3' => 'Pour découvrir de nouvelles façons de réduire vos dépenses, améliorer votre service et augmenter vos ventes.',
                'pourquoi_4' => 'Pour exprimer votre intérêt à recevoir des informations sur les solutions digitales qui correspondent à votre activité.',

                'duree' => 'Durée : environ 5 à 8 minutes.',
                'langue_note' => 'Langue : vous pouvez remplir ce formulaire en français ou en créole haïtien.',
                'confidentialite' => 'Confidentialité : vos réponses servent à l\'étude des besoins des entreprises et au développement de solutions digitales. Nous ne vous demandons ni mot de passe, ni code PIN, ni information secrète sur vos comptes financiers.',
                'cta_haut' => 'Commencer',

                'choisir_langue' => 'Langue du formulaire',

                's1_titre' => 'Votre activité',
                's2_titre' => 'Comment vous travaillez aujourd\'hui',
                's3_titre' => 'Vos difficultés',
                's4_titre' => 'Ce qui vous intéresserait',
                's5_titre' => 'Rester en contact',

                'q_entreprise' => 'Nom de votre entreprise ou organisation',
                'q_entreprise_aide' => 'Facultatif — laissez vide si vous préférez répondre sans vous nommer',
                'q_type' => 'Quel est votre type d\'activité ?',
                'q_type_autre' => 'Précisez votre activité',
                'q_ville' => 'Ville ou commune',
                'q_departement' => 'Département',
                'q_effectif' => 'Combien de personnes travaillent avec vous ?',
                'q_anciennete' => 'Depuis combien de temps l\'activité existe-t-elle ?',

                'q_outils' => 'Avec quoi tenez-vous votre activité aujourd\'hui ?',
                'q_encaissement' => 'Comment vos clients vous paient-ils ?',
                'q_connexion' => 'Avez-vous internet sur place ?',
                'q_presence' => 'Êtes-vous présent en ligne ?',

                'q_difficultes' => 'Qu\'est-ce qui vous pose le plus de difficulté ?',
                'q_difficultes_aide' => 'Choisissez tout ce qui vous concerne — c\'est la question la plus importante du formulaire',
                'q_temps' => 'Qu\'est-ce qui vous fait perdre le plus de temps ?',
                'q_pertes' => 'Qu\'est-ce qui vous fait perdre de l\'argent ou des clients ?',
                'q_priorite' => 'Si une seule chose pouvait être améliorée demain, laquelle ?',

                'q_solutions' => 'Quelles solutions vous intéresseraient ?',
                'q_budget' => 'Quel budget mensuel serait envisageable ?',
                'q_budget_aide' => 'Une fourchette suffit. Cela nous aide à concevoir une offre réaliste.',

                'q_contact' => 'Souhaitez-vous que GOVIBE vous recontacte ?',
                'q_contact_oui' => 'Oui, contactez-moi',
                'q_contact_non' => 'Non, je réponds seulement à l\'enquête',
                'q_nom' => 'Votre nom',
                'q_whatsapp' => 'Votre numéro WhatsApp',
                'q_email' => 'Votre adresse e-mail',
                'q_email_aide' => 'Facultatif',

                'multi_aide' => 'Plusieurs réponses possibles',
                'facultatif' => 'Facultatif',
                'choisir' => 'Choisissez…',
                'envoyer' => 'Envoyer mes réponses',
                'envoi_en_cours' => 'Envoi en cours…',
                'erreurs' => 'Vos réponses n\'ont pas été enregistrées. Corrigez les points signalés ci-dessous.',

                'merci_titre' => 'Merci, vos réponses sont enregistrées',
                'merci_texte' => 'Votre voix compte. Ce que vous venez de nous dire sert directement à concevoir des outils adaptés à la réalité des entreprises haïtiennes.',
                'merci_contact' => 'Vous avez demandé à être recontacté : notre équipe vous écrira.',
                'merci_reference' => 'Votre référence',
                'merci_retour' => 'Retour à l\'accueil',
                'merci_services' => 'Voir nos services',
                'conclusion' => 'La voix de votre entreprise compte. Construisons ensemble des solutions qui répondent aux vrais besoins.',
            ],

            'ht' => [
                'titre_page' => 'Ankèt sou bezwen w | GOVIBE Innovation Hub',
                'meta' => 'GOVIBE Innovation Hub ap koute antrepriz ayisyen yo : di nou difikilte biznis ou, n ap bati solisyon dijital ki reponn yo.',

                'bandeau' => 'Ankèt sou bezwen w',
                'titre' => 'Ann ede antrepriz ayisyen yo vin pi pèfòman gras a teknoloji',
                'accroche' => 'GOVIBE Innovation Hub ap koute antrepriz ayisyen yo.',
                'intro_1' => 'Èske w ap dirije yon biznis, yon lekòl, yon otèl, yon restoran, yon boutik, yon ajans oswa nenpòt lòt kalite aktivite ?',
                'intro_2' => 'Nou vle konprann vrè difikilte w ap rankontre chak jou, sa k ap fè w pèdi tan, kliyan oswa lajan, ak sa w ta renmen amelyore nan biznis ou.',
                'intro_3' => 'Objektif nou se devlope solisyon dijital ki adapte ak reyalite antrepriz ayisyen yo : lojisyèl jesyon, aplikasyon mobil, peman dijital, otomatizasyon, entèlijans atifisyèl ak lòt zouti teknolojik.',

                'pourquoi_titre' => 'Poukisa ou ta dwe ranpli fòmilè sa a ?',
                'pourquoi_1' => 'Pou ede nou idantifye pwoblèm reyèl antrepriz ou a.',
                'pourquoi_2' => 'Pou ede nou devlope solisyon ki reponn ak bezwen w.',
                'pourquoi_3' => 'Pou dekouvri nouvo fason pou diminye depans, amelyore sèvis ak ogmante lavant ou.',
                'pourquoi_4' => 'Pou eksprime enterè w pou resevwa enfòmasyon sou solisyon dijital ki koresponn ak biznis ou.',

                'duree' => 'Dire : anviwon 5 a 8 minit.',
                'langue_note' => 'Lang : ou ka ranpli fòmilè a an kreyòl ayisyen oswa an franse.',
                'confidentialite' => 'Konfidansyalite : enfòmasyon ou bay yo ap sèvi pou etid bezwen antrepriz yo ak devlopman solisyon dijital. Nou p ap mande w modpas, PIN oswa enfòmasyon sekrè sou kont finansye ou.',
                'cta_haut' => 'Kòmanse',

                'choisir_langue' => 'Lang fòmilè a',

                's1_titre' => 'Aktivite w',
                's2_titre' => 'Kijan w ap travay jodi a',
                's3_titre' => 'Difikilte w yo',
                's4_titre' => 'Sa ki ta enterese w',
                's5_titre' => 'Rete an kontak',

                'q_entreprise' => 'Non biznis ou oswa òganizasyon w',
                'q_entreprise_aide' => 'Opsyonèl — kite l vid si w prefere reponn san bay non w',
                'q_type' => 'Ki kalite aktivite w ap fè ?',
                'q_type_autre' => 'Presize aktivite w la',
                'q_ville' => 'Vil oswa komin',
                'q_departement' => 'Depatman',
                'q_effectif' => 'Konbyen moun k ap travay avè w ?',
                'q_anciennete' => 'Depi konbyen tan aktivite a egziste ?',

                'q_outils' => 'Ak kisa w ap jere aktivite w jodi a ?',
                'q_encaissement' => 'Kijan kliyan w yo peye w ?',
                'q_connexion' => 'Èske w gen entènèt sou plas ?',
                'q_presence' => 'Èske w prezan sou entènèt ?',

                'q_difficultes' => 'Kisa ki ba w plis difikilte ?',
                'q_difficultes_aide' => 'Chwazi tout sa ki konsène w — se kesyon ki pi enpòtan nan fòmilè a',
                'q_temps' => 'Kisa ki fè w pèdi plis tan ?',
                'q_pertes' => 'Kisa ki fè w pèdi lajan oswa kliyan ?',
                'q_priorite' => 'Si yon sèl bagay te ka amelyore demen, kiyès ?',

                'q_solutions' => 'Ki solisyon ki ta enterese w ?',
                'q_budget' => 'Ki bidjè pa mwa ki ta posib ?',
                'q_budget_aide' => 'Yon echèl sifi. Sa ede nou fè yon òf reyalis.',

                'q_contact' => 'Èske w vle GOVIBE kontakte w ?',
                'q_contact_oui' => 'Wi, kontakte m',
                'q_contact_non' => 'Non, m ap reponn ankèt la sèlman',
                'q_nom' => 'Non w',
                'q_whatsapp' => 'Nimewo WhatsApp ou',
                'q_email' => 'Adrès imèl ou',
                'q_email_aide' => 'Opsyonèl',

                'multi_aide' => 'Ou ka chwazi plizyè repons',
                'facultatif' => 'Opsyonèl',
                'choisir' => 'Chwazi…',
                'envoyer' => 'Voye repons mwen yo',
                'envoi_en_cours' => 'Y ap voye…',
                'erreurs' => 'Repons ou yo pa anrejistre. Korije pwen yo make anba a.',

                'merci_titre' => 'Mèsi, repons ou yo anrejistre',
                'merci_texte' => 'Vwa w konte. Sa w sot di nou an ap sèvi dirèkteman pou konsevwa zouti ki adapte ak reyalite antrepriz ayisyen yo.',
                'merci_contact' => 'Ou mande pou yo kontakte w : ekip nou an ap ekri w.',
                'merci_reference' => 'Referans ou',
                'merci_retour' => 'Tounen nan akèy la',
                'merci_services' => 'Wè sèvis nou yo',
                'conclusion' => 'Vwa antrepriz ou a enpòtan. Ann konstwi solisyon ki reponn ak vrè bezwen.',
            ],
        ];
    }
}
