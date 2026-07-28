<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'target_audience',
        'price_monthly', 'price_yearly', 'currency',
        'features', 'limits', 'color', 'badge', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features'      => 'array',
            'limits'        => 'array',
            'price_monthly' => 'decimal:2',
            'price_yearly'  => 'decimal:2',
            'is_active'     => 'boolean',
        ];
    }

    /** Plans par défaut — utilisés pour le seeder et la page /tarifs si la table est vide. */
    public static function defaults(): array
    {
        return [
            [
                'name'            => 'Starter',
                'slug'            => 'starter',
                'description'     => 'Pour démarrer simplement.',
                'target_audience' => 'Freelances, TPE',
                'price_monthly'   => 2500,
                'price_yearly'    => 25000,
                'currency'        => 'HTG',
                'color'           => '#6B7280',
                'badge'           => null,
                'features'        => ['POS (1 caisse)','50 clients CRM','Facturation basique','Rapport journalier','Support email'],
                'limits'          => ['clients'=>50,'users'=>2,'pos_terminals'=>1],
                'sort_order'      => 1,
            ],
            [
                'name'            => 'Business',
                'slug'            => 'business',
                'description'     => 'La suite complète pour les PME.',
                'target_audience' => 'PME, Commerces, Restaurants',
                'price_monthly'   => 8000,
                'price_yearly'    => 80000,
                'currency'        => 'HTG',
                'color'           => '#DC2626',
                'badge'           => 'Populaire',
                'features'        => ['Tout Starter','POS illimité','CRM complet','RH & Employés','Inventaire','Projets & Tâches','Rapports avancés','WhatsApp auto','Support prioritaire'],
                'limits'          => ['clients'=>500,'users'=>10,'pos_terminals'=>5],
                'sort_order'      => 2,
            ],
            [
                'name'            => 'ONG',
                'slug'            => 'ong',
                'description'     => 'Adapté aux ONG, Églises et Écoles.',
                'target_audience' => 'ONG, Église, École, Association',
                'price_monthly'   => 4000,
                'price_yearly'    => 40000,
                'currency'        => 'HTG',
                'color'           => '#16A34A',
                'badge'           => 'Solidaire',
                'features'        => ['Tout Business','Module Dons & Bénéficiaires','Reçus fiscaux','Gestion membres','Événements','Rapports impact','Formation incluse (2h/mois)'],
                'limits'          => ['clients'=>200,'users'=>8,'pos_terminals'=>2],
                'sort_order'      => 3,
            ],
            [
                'name'            => 'Academy',
                'slug'            => 'academy',
                'description'     => 'Gestion de formations et d\'inscriptions.',
                'target_audience' => 'Centres de formation, Universités',
                'price_monthly'   => 3000,
                'price_yearly'    => 30000,
                'currency'        => 'HTG',
                'color'           => '#7C3AED',
                'badge'           => null,
                'features'        => ['Gestion cours illimités','Inscriptions en ligne','Présences & absences','Certificats numériques','Espace étudiant','WhatsApp rappels','Export Excel'],
                'limits'          => ['students'=>300,'courses'=>50,'users'=>5],
                'sort_order'      => 4,
            ],
            [
                'name'            => 'Enterprise',
                'slug'            => 'enterprise',
                'description'     => 'Pour groupes, franchises et multi-sites.',
                'target_audience' => 'Groupes, Franchises, Multi-branches',
                'price_monthly'   => 25000,
                'price_yearly'    => 250000,
                'currency'        => 'HTG',
                'color'           => '#D97706',
                'badge'           => 'Sur mesure',
                'features'        => ['Tout Business + ONG + Academy','Multi-branches illimité','API REST complète','Tableau de bord consolidé','SLA 99,9%','Account Manager dédié','Formation équipe (8h/mois)','Personnalisation UI'],
                'limits'          => ['clients'=>-1,'users'=>-1,'pos_terminals'=>-1],
                'sort_order'      => 5,
            ],
        ];
    }
}
