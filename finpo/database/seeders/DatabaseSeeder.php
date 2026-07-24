<?php

namespace Database\Seeders;

use App\Models\Booth;
use App\Models\Coupon;
use App\Models\Exhibitor;
use App\Models\GalleryItem;
use App\Models\NewsPost;
use App\Models\Partner;
use App\Models\ProgramSession;
use App\Models\Room;
use App\Models\Speaker;
use App\Models\Sponsor;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /** Seed idempotent : réexécutable sans dupliquer les données. */
    public function run(): void
    {
        $this->admin();
        $this->tickets();
        $this->coupons();
        $this->speakers();
        $this->programme();
        $this->sponsors();
        $this->partners();
        $this->boothsAndExhibitors();
        $this->news();
        $this->gallery();
    }

    private function admin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@finpo.ht'],
            [
                'name'     => 'Admin FINPO',
                'password' => Hash::make(env('FINPO_ADMIN_PASSWORD', 'finpo2026')),
                'role'     => 'admin',
            ],
        );
    }

    private function tickets(): void
    {
        $categories = [
            ['Étudiant', 'student', 1000, 'Accès aux conférences et à l\'expo pour les étudiants (carte étudiante requise à l\'entrée).', 500, '#ca8a04', ["Accès Forum & Expo (3 jours)", "Kit participant", "Certificat de participation"]],
            ['Professionnel', 'professional', 5000, 'Le billet standard pour les professionnels et cadres d\'institutions.', 1500, '#0f766e', ["Accès Forum & Expo (3 jours)", "Kit participant premium", "Accès aux ateliers", "Certificat de participation", "Pause-café incluse"]],
            ['Institution / Entreprise', 'private', 15000, 'Pack institutionnel : 4 représentants d\'une même organisation.', 300, '#0e7490', ["4 badges nominatifs", "Accès Forum, Expo & Networking", "Logo dans le programme officiel", "Certificats de participation"]],
            ['VIP', 'vip', 25000, 'Expérience premium : accès lounge VIP, déjeuners officiels et soirée de gala.', 150, '#b45309', ["Accès toutes zones + lounge VIP", "Déjeuners officiels & gala", "Placement prioritaire aux keynotes", "Rencontres B2B facilitées", "Kit VIP"]],
            ['ONG / Association', 'ngo', 3000, 'Tarif solidaire pour les ONG, OCB et associations.', 400, '#15803d', ["Accès Forum & Expo (3 jours)", "Kit participant", "Certificat de participation"]],
            ['Startup', 'startup', 3500, 'Tarif spécial jeunes entreprises innovantes (moins de 5 ans).', 200, '#db2777', ["Accès Forum & Expo (3 jours)", "Accès zone innovation", "Pitch corner (sur sélection)", "Certificat"]],
            ['Média / Presse', 'press', 0, 'Accréditation presse gratuite (carte de presse obligatoire).', 100, '#c2410c', ["Accès salle de presse", "Kit média", "Interviews facilitées"]],
            ['Volontaire', 'volunteer', 0, 'Rejoignez l\'équipe d\'organisation de FINPO 2026.', 120, '#059669', ["Formation pré-événement", "T-shirt officiel", "Certificat de volontariat", "Repas inclus"]],
        ];

        foreach ($categories as $i => [$name, $audience, $price, $description, $quota, $color, $benefits]) {
            TicketCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name'        => $name,
                    'audience'    => $audience,
                    'price'       => $price,
                    'currency'    => 'HTG',
                    'description' => $description,
                    'quota'       => $quota,
                    'color'       => $color,
                    'benefits'    => $benefits,
                    'active'      => true,
                    'sort'        => $i,
                ],
            );
        }
    }

    private function coupons(): void
    {
        Coupon::updateOrCreate(['code' => 'FINPO10'], [
            'type' => 'percent', 'value' => 10, 'max_uses' => 200, 'active' => true,
        ]);
        Coupon::updateOrCreate(['code' => 'EARLYBIRD'], [
            'type' => 'percent', 'value' => 25, 'max_uses' => 100,
            'expires_at' => '2026-09-30 23:59:59', 'active' => true,
        ]);
    }

    private function speakers(): void
    {
        $speakers = [
            ['Dr. Marie-Ange Bellevue', 'Gouverneure', 'Banque de la République d\'Haïti', 'finance', 'Financer la transformation institutionnelle d\'Haïti', 'photo-1573496359142-b8d87734a5a2', true],
            ['Jean-Robert Lafortune', 'Directeur Général', 'Ministère de l\'Économie et des Finances', 'government', 'Modernisation de l\'administration publique', 'photo-1560250097-0b93528c311a', true],
            ['Stephanie Balmir', 'CEO', 'Digicel Haïti', 'technology', 'Connectivité et inclusion numérique', 'photo-1580489944761-15a19d654956', true],
            ['Dr. Patrick Attié', 'Directeur', 'École Supérieure d\'Infotronique d\'Haïti', 'education', 'Former les talents tech de demain', 'photo-1472099645785-5658abf4ff4e', true],
            ['Régine Simon', 'Représentante Résidente', 'PNUD Haïti', 'international', 'Partenariats internationaux pour le développement durable', 'photo-1567532939604-b6b5b0db2604', true],
            ['Marc-Henry Delva', 'Président', 'Chambre de Commerce et d\'Industrie d\'Haïti', 'private', 'Le secteur privé, moteur de croissance', 'photo-1519085360753-af0119f7cbe7', true],
            ['Dr. Nathalie Coicou', 'Directrice', 'GHESKIO', 'health', 'Innovation en santé publique', 'photo-1594744803329-e58b31de8bf5', true],
            ['James Pierre-Louis', 'Fondateur', 'AgriTech Haïti', 'agriculture', 'La technologie au service de l\'agriculture haïtienne', 'photo-1507003211169-0a1dd7228f2d', true],
            ['Fabienne Jean-Baptiste', 'Directrice Exécutive', 'Fondation Konesans', 'ngo', 'Le rôle des OSC dans la gouvernance locale', 'photo-1589571894960-20bbe2828d0a', false],
            ['Ricardo Étienne', 'Country Manager', 'Banque Interaméricaine de Développement', 'international', 'Investir dans les infrastructures résilientes', 'photo-1556157382-97eda2d62296', false],
            ['Vanessa Désir', 'Directrice Innovation', 'GOVIBE Innovation Hub', 'technology', 'Écosystème startup haïtien : état des lieux', 'photo-1573497019940-1c28c88b4f3e', false],
            ['Emmanuel Dorsainvil', 'Directeur Environnement', 'Ministère de l\'Environnement', 'environment', 'Transition écologique et économie verte', 'photo-1500648767791-00dcc994a43e', false],
        ];

        foreach ($speakers as $i => [$name, $position, $institution, $category, $topic, $photo, $featured]) {
            Speaker::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name'        => $name,
                    'position'    => $position,
                    'institution' => $institution,
                    'category'    => $category,
                    'topic'       => $topic,
                    'photo_url'   => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=600&q=80",
                    'bio'         => "$name est $position de $institution. Figure reconnue du secteur, elle/il interviendra à FINPO 2026 sur le thème « $topic », partageant une expérience de terrain et une vision stratégique pour le développement d'Haïti.",
                    'linkedin'    => 'https://linkedin.com/in/'.Str::slug($name),
                    'featured'    => $featured,
                    'active'      => true,
                    'sort'        => $i,
                ],
            );
        }
    }

    private function programme(): void
    {
        $rooms = [];
        $roomDefs = [
            ['Grande Salle Toussaint Louverture', 1200],
            ['Salle Catherine Flon', 400],
            ['Salle Anténor Firmin', 250],
            ['Zone Expo & Networking', 800],
        ];
        foreach ($roomDefs as $i => [$name, $capacity]) {
            $rooms[$i] = Room::updateOrCreate(['name' => $name], ['capacity' => $capacity, 'sort' => $i]);
        }

        $speakerIds = Speaker::pluck('id', 'slug');
        $day1 = '2026-11-18';
        $day2 = '2026-11-19';
        $day3 = '2026-11-20';

        $sessions = [
            // Jour 1
            [$day1, '08:00', '09:00', 0, 'ceremony', 'Institutionnel', 'Accueil & enregistrement des participants', 'Retrait des badges, café de bienvenue et visite libre de l\'expo.', [], false],
            [$day1, '09:00', '10:30', 0, 'ceremony', 'Institutionnel', 'Cérémonie d\'ouverture officielle', 'Allocutions officielles, présentation de la vision FINPO 2026 et lancement des trois journées du forum.', ['jean-robert-lafortune'], true],
            [$day1, '10:45', '11:45', 0, 'keynote', 'Finance', 'Keynote — Financer la transformation institutionnelle', 'Comment mobiliser les capitaux publics et privés pour moderniser les institutions haïtiennes.', ['dr-marie-ange-bellevue'], true],
            [$day1, '12:00', '13:30', 1, 'panel', 'Gouvernance', 'Panel — Modernisation de l\'administration publique', 'Digitalisation des services publics, transparence et redevabilité : retours d\'expérience.', ['jean-robert-lafortune', 'regine-simon'], false],
            [$day1, '14:30', '16:00', 2, 'workshop', 'Technologie', 'Atelier — Transformation numérique des institutions', 'Atelier pratique : bâtir une feuille de route numérique pour son organisation.', ['stephanie-balmir', 'vanessa-desir'], false],
            [$day1, '16:30', '18:00', 3, 'networking', 'Networking', 'Cocktail de networking — Secteur public & privé', 'Rencontres facilitées entre décideurs publics, entreprises et organisations.', [], false],
            // Jour 2
            [$day2, '09:00', '10:00', 0, 'keynote', 'Technologie', 'Keynote — Connectivité et inclusion numérique', 'L\'accès au numérique comme levier d\'inclusion économique et sociale.', ['stephanie-balmir'], true],
            [$day2, '10:15', '11:45', 0, 'panel', 'Économie', 'Panel — Le secteur privé, moteur de croissance', 'Climat des affaires, investissement et création d\'emplois durables.', ['marc-henry-delva', 'ricardo-etienne'], false],
            [$day2, '12:00', '13:30', 1, 'panel', 'Santé', 'Panel — Innovation en santé publique', 'Partenariats public-privé et technologies au service de la santé pour tous.', ['dr-nathalie-coicou'], false],
            [$day2, '14:00', '15:30', 2, 'workshop', 'Agriculture', 'Atelier — AgriTech : moderniser l\'agriculture haïtienne', 'Données, irrigation intelligente et accès au marché pour les producteurs.', ['james-pierre-louis'], false],
            [$day2, '15:45', '17:00', 1, 'panel', 'Éducation', 'Panel — Former les talents de demain', 'Adéquation formation-emploi et compétences numériques.', ['dr-patrick-attie'], false],
            [$day2, '17:15', '18:30', 3, 'networking', 'Networking', 'Business Matchmaking B2B', 'Sessions de rendez-vous d\'affaires pré-programmés entre participants.', [], false],
            // Jour 3
            [$day3, '09:00', '10:00', 0, 'keynote', 'International', 'Keynote — Partenariats internationaux pour le développement', 'Aligner coopération internationale et priorités nationales.', ['regine-simon'], true],
            [$day3, '10:15', '11:45', 1, 'panel', 'Environnement', 'Panel — Transition écologique et économie verte', 'Énergies renouvelables, gestion des risques et financement climatique.', ['emmanuel-dorsainvil'], false],
            [$day3, '12:00', '13:00', 2, 'panel', 'Société civile', 'Panel — Le rôle des OSC dans la gouvernance', 'Participation citoyenne et contrôle de l\'action publique.', ['fabienne-jean-baptiste'], false],
            [$day3, '14:00', '15:30', 0, 'awards', 'Awards', 'FINPO Awards — Cérémonie de remise des prix', 'Distinction des institutions, entreprises et organisations les plus innovantes de l\'année.', [], true],
            [$day3, '15:45', '17:00', 0, 'ceremony', 'Institutionnel', 'Cérémonie de clôture & déclaration finale', 'Synthèse des travaux, engagements des parties prenantes et rendez-vous 2027.', [], false],
        ];

        foreach ($sessions as [$day, $start, $end, $roomIdx, $type, $track, $title, $description, $speakerSlugs, $featured]) {
            $session = ProgramSession::updateOrCreate(
                ['day' => $day, 'starts_at' => $start, 'title' => $title],
                [
                    'description' => $description,
                    'ends_at'     => $end,
                    'room_id'     => $rooms[$roomIdx]->id,
                    'type'        => $type,
                    'track'       => $track,
                    'featured'    => $featured,
                    'active'      => true,
                ],
            );
            $ids = collect($speakerSlugs)->map(fn ($slug) => $speakerIds->get($slug))->filter()->all();
            $session->speakers()->sync($ids);
        }
    }

    private function sponsors(): void
    {
        $sponsors = [
            ['Banque Nationale de Crédit', 'title'],
            ['Digicel Business', 'diamond'],
            ['Sogebank', 'platinum'],
            ['Unibank', 'platinum'],
            ['Brasserie Nationale d\'Haïti', 'gold'],
            ['Groupe Deka', 'gold'],
            ['Sûrtab', 'silver'],
            ['Capital Bank', 'silver'],
            ['Marriott Port-au-Prince', 'bronze'],
            ['Rhum Barbancourt', 'bronze'],
            ['Kay Kòd', 'community'],
        ];

        foreach ($sponsors as $i => [$name, $level]) {
            Sponsor::updateOrCreate(['name' => $name], [
                'level'    => $level,
                'logo_url' => 'https://placehold.co/360x140/0b1220/e8b931?text='.urlencode($name),
                'website'  => 'https://example.com',
                'status'   => 'approved',
                'sort'     => $i,
            ]);
        }
    }

    private function partners(): void
    {
        $partners = [
            ['Ministère de l\'Économie et des Finances', 'government'],
            ['Ministère du Commerce et de l\'Industrie', 'government'],
            ['PNUD Haïti', 'international'],
            ['Banque Interaméricaine de Développement', 'international'],
            ['Union Européenne en Haïti', 'international'],
            ['Chambre de Commerce et d\'Industrie d\'Haïti', 'private'],
            ['AmCham Haiti', 'private'],
            ['Université d\'État d\'Haïti', 'academic'],
            ['Université Quisqueya', 'academic'],
            ['Fondation Konesans', 'ngo'],
            ['Le Nouvelliste', 'media'],
            ['Magik9', 'media'],
            ['GOVIBE Innovation Hub', 'strategic'],
        ];

        foreach ($partners as $i => [$name, $category]) {
            Partner::updateOrCreate(['name' => $name], [
                'category' => $category,
                'logo_url' => 'https://placehold.co/320x120/101a2e/9fb3d1?text='.urlencode($name),
                'website'  => 'https://example.com',
                'status'   => 'approved',
                'sort'     => $i,
            ]);
        }
    }

    private function boothsAndExhibitors(): void
    {
        foreach (['A' => [12, 150000, '3x3'], 'B' => [12, 100000, '3x3'], 'C' => [8, 250000, '6x3']] as $zone => [$count, $price, $size]) {
            for ($n = 1; $n <= $count; $n++) {
                Booth::updateOrCreate(
                    ['code' => sprintf('%s-%02d', $zone, $n)],
                    ['zone' => $zone, 'size' => $size, 'price' => $price, 'status' => 'available'],
                );
            }
        }

        $exhibitors = [
            ['Digicel Business', 'Télécommunications', 'Solutions de connectivité et services cloud pour les institutions.', 'A-01'],
            ['Sogebank', 'Banque & Finance', 'Produits bancaires institutionnels, financement des PME et monétique.', 'A-02'],
            ['AgriTech Haïti', 'Agriculture', 'Technologies agricoles : capteurs, irrigation intelligente, marché digital.', 'B-01'],
            ['Sûrtab', 'Technologie', 'Assemblage de tablettes et équipements numériques en Haïti.', 'B-02'],
            ['GOVIBE Innovation Hub', 'Innovation', 'Écosystème d\'innovation : incubation, formation et événements tech.', 'C-01'],
            ['Haïti Solar Solutions', 'Énergie', 'Solutions solaires clés en main pour institutions et entreprises.', 'B-03'],
            ['Kreyòl Essence', 'Agro-industrie', 'Produits naturels haïtiens à l\'export : huile de ricin, moringa.', 'A-03'],
            ['Banj', 'Technologie', 'Espace de coworking et hub entrepreneurial de Port-au-Prince.', 'B-04'],
        ];

        foreach ($exhibitors as $i => [$company, $sector, $description, $boothCode]) {
            $booth = Booth::where('code', $boothCode)->first();
            if ($booth) {
                $booth->update(['status' => 'sold']);
            }
            Exhibitor::updateOrCreate(
                ['slug' => Str::slug($company)],
                [
                    'company'     => $company,
                    'sector'      => $sector,
                    'description' => $description,
                    'products'    => "Découvrez sur notre stand les dernières solutions de $company : démonstrations en direct, offres spéciales FINPO et rencontres avec nos experts.",
                    'services'    => 'Conseil, accompagnement et support dédiés aux institutions publiques, privées et organisations.',
                    'logo_url'    => 'https://placehold.co/320x320/0b1220/7de2ff?text='.urlencode($company),
                    'banner_url'  => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?auto=format&fit=crop&w=1600&q=80',
                    'website'     => 'https://example.com',
                    'socials'     => ['facebook' => 'https://facebook.com', 'linkedin' => 'https://linkedin.com'],
                    'booth_id'    => $booth?->id,
                    'status'      => 'approved',
                    'featured'    => $i < 4,
                    'sort'        => $i,
                ],
            );
        }
    }

    private function news(): void
    {
        $posts = [
            ['FINPO 2026 : les inscriptions sont officiellement ouvertes', 'Annonce', 'photo-1505373877841-8d25f7d46678', 'Réservez dès maintenant votre place à la première édition du Forum & Expo National des Institutions Publiques, Privées et Organisations.', 30],
            ['La BRH confirme sa participation comme Title Sponsor', 'Communiqué', 'photo-1559223607-a43c990c692c', 'La Banque de la République d\'Haïti rejoint FINPO 2026 en tant que sponsor principal de l\'événement.', 22],
            ['Programme dévoilé : 3 jours, 17 sessions, 12 intervenants', 'Actualité', 'photo-1475721027785-f74eccf877e2', 'Keynotes, panels, ateliers pratiques et networking : découvrez le programme complet de FINPO 2026.', 15],
            ['Appel à candidatures : FINPO Awards 2026', 'Annonce', 'photo-1531058020387-3be344556be6', 'Institutions, entreprises et organisations : soumettez votre candidature aux FINPO Awards avant le 30 septembre.', 10],
            ['Pourquoi exposer à FINPO 2026 ? 5 raisons clés', 'Article', 'photo-1587825140708-dfaf72ae4b04', 'Visibilité, contacts qualifiés, opportunités d\'affaires : les bénéfices concrets d\'un stand à l\'expo.', 6],
            ['Tarif early bird : -25% jusqu\'au 30 septembre', 'Mise à jour', 'photo-1556761175-b413da4baf72', 'Profitez du code EARLYBIRD pour bénéficier de 25% de réduction sur toutes les catégories de billets.', 2],
        ];

        foreach ($posts as [$title, $tag, $photo, $excerpt, $daysAgo]) {
            NewsPost::updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title'        => $title,
                    'tag'          => $tag,
                    'cover_url'    => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=1200&q=80",
                    'excerpt'      => $excerpt,
                    'body'         => $excerpt."\n\n".
                        "FINPO 2026 réunira du 18 au 20 novembre 2026 à Port-au-Prince plus de 3 000 participants : institutions publiques, entreprises privées, ONG, universités et organisations internationales.\n\n".
                        "Au programme : keynotes de haut niveau, panels sectoriels, ateliers pratiques, expo de 32 stands, networking B2B et la cérémonie des FINPO Awards.\n\n".
                        "Organisé par GOVIBE Innovation Hub, FINPO a pour mission de connecter les institutions, construire des partenariats durables et accélérer le développement d'Haïti.\n\n".
                        "Informations et inscriptions sur finpo.ht — contact : info@finpo.ht.",
                    'published_at' => now()->subDays($daysAgo),
                ],
            );
        }
    }

    private function gallery(): void
    {
        $photos = [
            ['photo-1540575467063-178a50c2df87', 'Vue de la grande salle plénière'],
            ['photo-1511578314322-379afb476865', 'Networking entre participants'],
            ['photo-1475721027785-f74eccf877e2', 'Keynote d\'ouverture'],
            ['photo-1587825140708-dfaf72ae4b04', 'Panel sectoriel'],
            ['photo-1556761175-b413da4baf72', 'Rencontres B2B'],
            ['photo-1531058020387-3be344556be6', 'Zone expo et stands'],
            ['photo-1505236858219-8359eb29e329', 'Cérémonie des Awards'],
            ['photo-1515187029135-18ee286d815b', 'Ateliers pratiques'],
        ];

        foreach ($photos as $i => [$photo, $caption]) {
            GalleryItem::updateOrCreate(
                ['url' => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=1200&q=80"],
                [
                    'type'      => 'photo',
                    'thumb_url' => "https://images.unsplash.com/{$photo}?auto=format&fit=crop&w=600&q=60",
                    'caption'   => $caption,
                    'edition'   => 2025,
                    'sort'      => $i,
                ],
            );
        }
    }
}
