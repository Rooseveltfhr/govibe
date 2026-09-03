<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Le catalogue est créé par migration pour que la page soit vendable dès
     * la mise en ligne. Tout y est ensuite modifiable depuis l'ERP : noms,
     * capacités, prix, canaux. Aucune de ces valeurs n'est codée dans une vue.
     */
    public function up(): void
    {
        $maintenant = now();

        foreach ($this->agents() as $i => $agent) {
            // updateOrInsert : rejouée, la migration ne duplique pas le
            // catalogue et n'écrase pas des prix ajustés depuis l'ERP.
            DB::table('agents_ia')->updateOrInsert(
                ['slug' => $agent['slug']],
                array_merge($agent, [
                    'capacites' => json_encode($agent['capacites'], JSON_UNESCAPED_UNICODE),
                    'canaux' => json_encode($agent['canaux'], JSON_UNESCAPED_UNICODE),
                    'ordre' => $i + 1,
                    'actif' => true,
                    'created_at' => $maintenant,
                    'updated_at' => $maintenant,
                ])
            );
        }
    }

    public function down(): void
    {
        DB::table('agents_ia')->whereIn('slug', array_column($this->agents(), 'slug'))->delete();
    }

    private function agents(): array
    {
        return [
            [
                'slug' => 'service-client', 'nom' => 'Agent IA — Service Client',
                'categorie' => 'Relation client', 'icone' => 'fa-headset',
                'description_courte' => 'Un assistant IA qui répond automatiquement aux questions de vos clients et les accompagne 24h/24.',
                'capacites' => [
                    'Répondre aux questions fréquentes',
                    "Donner les informations de l'entreprise",
                    'Expliquer les produits et services',
                    'Recueillir les informations du client',
                    'Traiter les demandes courantes',
                    'Transférer les cas complexes à un humain',
                    'Relancer les clients',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => 150, 'prix_mensuel' => 45, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'hotel', 'nom' => 'Agent IA — Hôtel',
                'categorie' => 'Hôtellerie', 'icone' => 'fa-bell-concierge',
                'description_courte' => 'Votre réceptionniste IA disponible 24h/24 pour informer les clients et faciliter les réservations.',
                'capacites' => [
                    "Répondre aux questions sur l'hôtel",
                    'Présenter les chambres',
                    'Donner les tarifs',
                    "Expliquer les services de l'hôtel",
                    'Informer sur le check-in et le check-out',
                    'Recevoir les demandes de réservation',
                    'Recueillir les informations de séjour',
                    "Indiquer l'emplacement",
                    'Transférer à la réception',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => 250, 'prix_mensuel' => 75, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'restaurant', 'nom' => 'Agent IA — Restaurant',
                'categorie' => 'Restauration', 'icone' => 'fa-utensils',
                'description_courte' => 'Un assistant IA qui gère les demandes clients, présente le menu et facilite les réservations.',
                'capacites' => [
                    'Présenter le menu',
                    'Répondre aux questions sur les plats',
                    "Donner les heures d'ouverture",
                    'Recevoir les demandes de réservation',
                    'Recueillir les informations du client',
                    "Indiquer l'emplacement",
                    'Traiter les demandes de livraison et de commande',
                    'Mettre en avant les offres spéciales',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => 200, 'prix_mensuel' => 60, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'bar-club', 'nom' => 'Agent IA — Bar & Club',
                'categorie' => 'Divertissement', 'icone' => 'fa-champagne-glasses',
                'description_courte' => 'Un assistant IA pour gérer les événements, réservations, billets et demandes des clients.',
                'capacites' => [
                    'Présenter les événements à venir',
                    'Donner les informations sur un événement',
                    'Expliquer les tarifs',
                    'Prendre les réservations VIP',
                    'Prendre les réservations de tables',
                    'Renseigner sur les billets',
                    "Donner les heures d'ouverture",
                    'Annoncer les promotions',
                    'Assister les clients',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => 250, 'prix_mensuel' => 70, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'ong', 'nom' => 'Agent IA — ONG / Organisation',
                'categorie' => 'Organisation', 'icone' => 'fa-hands-holding-circle',
                'description_courte' => "Un assistant IA qui facilite l'accès aux informations, programmes et services de votre organisation.",
                'capacites' => [
                    'Expliquer les programmes',
                    'Répondre aux questions fréquentes',
                    "Expliquer les conditions d'éligibilité",
                    'Recueillir les candidatures',
                    'Orienter les bénéficiaires',
                    'Fixer des rendez-vous',
                    'Informer sur les projets',
                    'Recueillir les coordonnées',
                    "Transférer les cas complexes à l'équipe",
                ],
                'canaux' => ['WhatsApp', 'Site web'],
                'prix_installation' => 250, 'prix_mensuel' => 70, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'clinique', 'nom' => 'Agent IA — Clinique / Centre de santé',
                'categorie' => 'Santé', 'icone' => 'fa-stethoscope',
                'description_courte' => 'Un assistant IA qui aide les patients à obtenir des informations et à organiser leurs demandes.',
                'capacites' => [
                    'Donner les informations sur le centre',
                    'Présenter les services',
                    "Donner les heures d'ouverture",
                    'Recevoir les demandes de rendez-vous',
                    'Renseigner sur les médecins',
                    'Recueillir les coordonnées du patient',
                    'Répondre aux questions administratives',
                    "Transférer les demandes à l'équipe",
                ],
                'canaux' => ['WhatsApp', 'Site web'],
                'prix_installation' => 300, 'prix_mensuel' => 90, 'devise' => 'USD',
                'sur_devis' => false,
                'avertissement' => "Cet agent ne pose aucun diagnostic et ne remplace jamais un professionnel de santé. Toute question médicale est orientée vers l'équipe soignante.",
            ],
            [
                'slug' => 'immobilier', 'nom' => 'Agent IA — Immobilier',
                'categorie' => 'Immobilier', 'icone' => 'fa-building',
                'description_courte' => 'Un assistant IA qui qualifie les prospects et présente automatiquement les propriétés disponibles.',
                'capacites' => [
                    'Présenter les biens disponibles',
                    'Répondre aux questions sur un bien',
                    'Donner les prix',
                    "Recueillir les critères de l'acheteur",
                    'Qualifier les prospects',
                    'Fixer les visites',
                    'Recueillir les coordonnées',
                    'Transmettre les prospects qualifiés aux agents',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => 250, 'prix_mensuel' => 75, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'e-commerce', 'nom' => 'Agent IA — E-commerce',
                'categorie' => 'Commerce en ligne', 'icone' => 'fa-cart-shopping',
                'description_courte' => 'Un assistant IA qui accompagne les clients avant, pendant et après leur achat.',
                'capacites' => [
                    'Renseigner sur les produits',
                    'Recommander des produits',
                    'Assister pendant la commande',
                    'Répondre aux questions fréquentes',
                    'Informer sur la livraison',
                    'Assurer le support après-vente',
                    'Recueillir les prospects',
                    'Transférer à un humain',
                ],
                'canaux' => ['WhatsApp', 'Site web'],
                'prix_installation' => 300, 'prix_mensuel' => 90, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'education', 'nom' => 'Agent IA — Éducation',
                'categorie' => 'Éducation', 'icone' => 'fa-graduation-cap',
                'description_courte' => 'Un assistant IA pour les écoles, centres de formation et universités.',
                'capacites' => [
                    'Expliquer les programmes',
                    "Renseigner sur l'admission",
                    "Renseigner sur l'inscription",
                    'Donner les frais de scolarité',
                    'Donner les horaires',
                    'Répondre aux questions des étudiants',
                    'Recueillir les candidatures',
                    'Orienter vers le bon département',
                ],
                'canaux' => ['WhatsApp', 'Site web'],
                'prix_installation' => 250, 'prix_mensuel' => 70, 'devise' => 'USD',
                'sur_devis' => false, 'avertissement' => null,
            ],
            [
                'slug' => 'personnalise', 'nom' => 'Agent IA personnalisé',
                'categorie' => 'Sur mesure', 'icone' => 'fa-wand-magic-sparkles',
                'description_courte' => "Vous avez un besoin spécifique ? GOVIBE conçoit un Agent IA adapté à vos processus et à votre secteur d'activité.",
                'capacites' => [
                    'Analyse de vos processus métier',
                    'Conception sur mesure',
                    'Intégration à vos outils existants',
                    'Formation de vos équipes',
                    'Accompagnement au déploiement',
                ],
                'canaux' => ['WhatsApp', 'Site web', 'Voix'],
                'prix_installation' => null, 'prix_mensuel' => null, 'devise' => 'USD',
                'sur_devis' => true, 'avertissement' => null,
            ],
        ];
    }
};
