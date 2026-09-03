<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FicheTechnique extends Model
{
    protected $table = 'fiches_techniques';

    protected $fillable = [
        'reference',
        'nom_organisation', 'nom_commercial', 'type_organisation', 'secteur',
        'commune', 'adresse', 'telephone', 'email', 'taille_employes',
        'contact_nom', 'contact_fonction', 'contact_telephone', 'contact_email',
        'est_decideur', 'decideur_nom', 'decideur_contact',
        'score_besoin', 'score_potentiel', 'rendez_vous_possible',
        'statut', 'prochaine_action', 'date_relance',
        'agent', 'responsable_assigne',
        'reponses', 'observation_agent',
    ];

    protected $casts = [
        'reponses'             => 'array',
        'date_relance'         => 'date',
        'est_decideur'         => 'boolean',
        'rendez_vous_possible' => 'boolean',
        'score_besoin'         => 'integer',
        'score_potentiel'      => 'integer',
    ];

    public function suivis(): HasMany
    {
        return $this->hasMany(FicheSuivi::class, 'fiche_technique_id')->latest();
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'FT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    /* ── Qualification ───────────────────────────────────────────── */

    /**
     * Somme des deux axes, sur 8. C'est ce qui ordonne le portefeuille :
     * un besoin urgent chez un prospect sans moyens ne vaut pas un besoin
     * moyen chez un prospect solvable.
     */
    public function getScoreTotalAttribute(): int
    {
        return $this->score_besoin + $this->score_potentiel;
    }

    /**
     * Un prospect est qualifié quand le besoin est réel et le potentiel
     * suffisant — c'est le seuil à partir duquel un agent y retourne.
     */
    public function getEstQualifieAttribute(): bool
    {
        return $this->score_besoin >= 2 && $this->score_potentiel >= 2;
    }

    public function getPrioriteAttribute(): string
    {
        return match (true) {
            $this->score_total >= 6 => 'haute',
            $this->score_total >= 4 => 'moyenne',
            $this->score_total >= 2 => 'basse',
            default                 => 'aucune',
        };
    }

    public function getAEteSuiviAttribute(): bool
    {
        return $this->suivis()->exists();
    }

    /* ── Portées ─────────────────────────────────────────────────── */

    public function scopeQualifies(Builder $q): Builder
    {
        return $q->where('score_besoin', '>=', 2)->where('score_potentiel', '>=', 2);
    }

    public function scopeARelancer(Builder $q): Builder
    {
        return $q->whereNotNull('date_relance')
                 ->whereDate('date_relance', '<=', today())
                 ->whereNotIn('statut', ['client', 'perdu']);
    }

    /* ── Référentiels ────────────────────────────────────────────── */
    /* Partagés par le formulaire public et l'affichage ERP : une seule
       source, donc pas de libellé qui diverge entre les deux. */

    public static function typesOrganisation(): array
    {
        return [
            'entreprise'     => 'Entreprise',
            'ong'            => 'ONG',
            'ecole'          => 'École / Université',
            'institution'    => 'Institution',
            'association'    => 'Association',
            'petit_commerce' => 'Petit commerce',
            'autre'          => 'Autre',
        ];
    }

    public static function secteurs(): array
    {
        return [
            'education' => 'Éducation', 'sante' => 'Santé', 'commerce' => 'Commerce',
            'finance' => 'Finance', 'agriculture' => 'Agriculture',
            'hotellerie' => 'Hôtellerie / Tourisme', 'restauration' => 'Restauration',
            'transport' => 'Transport', 'construction' => 'Construction',
            'services' => 'Services professionnels', 'ong_dev' => 'ONG / développement',
            'autre' => 'Autre',
        ];
    }

    public static function taillesEmployes(): array
    {
        return ['1-5' => '1 à 5', '6-10' => '6 à 10', '11-25' => '11 à 25', '26-50' => '26 à 50', '50+' => 'Plus de 50'];
    }

    public static function fonctions(): array
    {
        return [
            'pdg' => 'PDG / Directeur', 'manager' => 'Manager',
            'admin' => 'Responsable administratif', 'informatique' => 'Responsable informatique',
            'marketing' => 'Responsable marketing', 'finance' => 'Comptable / Finance',
            'secretaire' => 'Secrétaire', 'autre' => 'Autre',
        ];
    }

    public static function statuts(): array
    {
        return [
            'nouveau'      => 'Nouveau prospect',
            'contacte'     => 'Contacté',
            'decideur'     => 'Décideur identifié',
            'rdv_planifie' => 'Rendez-vous planifié',
            'rencontre'    => 'Rencontre effectuée',
            'proposition'  => 'Proposition envoyée',
            'negociation'  => 'Négociation',
            'client'       => 'Client',
            'perdu'        => 'Perdu',
            'a_suivre'     => 'À suivre',
        ];
    }

    public static function prochainesActions(): array
    {
        return [
            'appel' => 'Appel téléphonique', 'whatsapp' => 'WhatsApp',
            'demo_klasyo' => 'Démonstration KLASYO', 'demo_tagtoa' => 'Démonstration TAGTOA',
            'audit' => 'Audit numérique', 'rencontre_pdg' => 'Rencontre avec Directeur/PDG',
            'proposition' => 'Proposition commerciale', 'formation' => 'Formation',
            'aucun' => 'Aucun suivi',
        ];
    }

    public static function solutions(): array
    {
        return [
            'formation_klasyo' => 'Formation + KLASYO',
            'solution_numerique' => 'Solution numérique',
            'tagtoa' => 'TAGTOA',
            'klasyo' => 'KLASYO',
            'plusieurs' => 'Plusieurs solutions',
        ];
    }

    public static function niveauxScore(): array
    {
        return [
            0 => 'Aucun besoin identifié', 1 => 'Faible', 2 => 'Moyen',
            3 => 'Important', 4 => 'Urgent',
        ];
    }

    public static function niveauxPotentiel(): array
    {
        return [
            0 => 'Très faible', 1 => 'Faible', 2 => 'Moyen',
            3 => 'Élevé', 4 => 'Très élevé',
        ];
    }

    /**
     * Groupes de cases à cocher du questionnaire, rangés par section.
     * Le formulaire les parcourt pour se construire ; l'ERP les parcourt
     * pour relire une fiche. Ajouter une question ne demande aucune migration.
     */
    public static function questionsCochables(): array
    {
        return [
            'activite' => [
                'titre' => "Comprendre l'activité",
                'champs' => [
                    'canaux_acquisition' => ['label' => 'Comment les clients vous trouvent-ils ?', 'options' => ['Réseaux sociaux', 'WhatsApp', 'Site web', 'Recommandations', 'Local physique', 'Publicité', 'Autre']],
                    'gestion_clients'    => ['label' => 'Comment gérez-vous vos clients ?', 'options' => ['Cahier', 'Excel', 'WhatsApp', 'Logiciel', 'CRM', 'Autre']],
                    'gestion_operations' => ['label' => 'Comment gérez-vous vos opérations internes ?', 'options' => ['Manuel', 'Excel', 'Logiciel', 'Plusieurs logiciels', 'Système personnalisé']],
                ],
            ],
            'numerique' => [
                'titre' => 'Diagnostic numérique',
                'champs' => [
                    'actions_site'    => ['label' => 'Actions possibles sur le site', 'options' => ['Demande de service', 'Réservation', 'Achat', 'Paiement', 'Contact', 'Aucune']],
                    'reseaux_utilises'=> ['label' => 'Réseaux sociaux utilisés', 'options' => ['Facebook', 'Instagram', 'TikTok', 'LinkedIn', 'YouTube', 'WhatsApp Business', 'Autre']],
                ],
            ],
            'paiement' => [
                'titre' => 'Paiement et vente',
                'champs' => [
                    'moyens_paiement' => ['label' => 'Moyens de paiement acceptés', 'options' => ['Cash', 'MonCash', 'NatCash', 'Virement bancaire', 'Carte bancaire', 'Autre']],
                ],
            ],
            'tagtoa' => [
                'titre' => 'TAGTOA — NFC et QR',
                'champs' => [
                    'besoins_partage' => ['label' => 'Ce qu\'ils ont besoin de partager rapidement', 'options' => ['Coordonnées', 'Catalogue', 'Menu', 'Réseaux sociaux', 'Site web', 'Carte professionnelle', 'Informations produits', "Informations sur l'entreprise"]],
                ],
            ],
            'klasyo' => [
                'titre' => 'KLASYO — formation',
                'champs' => [
                    'types_formations' => ['label' => 'Types de formations organisées', 'options' => ['Informatique', 'Management', 'Vente', 'Service client', 'Sécurité', 'Finance', 'RH', 'Technique', 'Autre']],
                    'gestion_formations' => ['label' => 'Comment les formations sont gérées', 'options' => ['Présentiel', 'WhatsApp', 'Zoom/Meet', 'Plateforme LMS', 'Aucun système']],
                    'besoins_suivi'   => ['label' => 'Ce qu\'ils ont besoin de suivre', 'options' => ['Participants', 'Présence', 'Progression', 'Examens', 'Certificats', 'Résultats']],
                ],
            ],
            'ecole' => [
                'titre' => 'Écoles',
                'champs' => [
                    'partage_cours'    => ['label' => 'Comment les cours sont partagés', 'options' => ['WhatsApp', 'Google Classroom', 'Moodle', 'Facebook', 'Papier', 'Autre']],
                    'besoins_plateforme' => ['label' => 'Besoins de gestion', 'options' => ['Cours', 'Élèves', 'Enseignants', 'Examens', 'Devoirs', 'Notes', 'Présence', 'Certificats', 'Communication avec parents', 'Paiements scolaires']],
                    'sujets_formation' => ['label' => 'Sujets de formation souhaités', 'options' => ['Intelligence artificielle', 'Informatique', 'Cybersécurité', 'Entrepreneuriat', 'Marketing digital', 'Création de contenu', 'Programmation', 'Leadership', 'Autre']],
                ],
            ],
        ];
    }

    /**
     * Section 5 : ce qui est encore fait à la main. Trois états par fonction,
     * parce que « pas de système » et « géré manuellement » n'appellent pas
     * la même proposition.
     */
    public static function fonctionsGestion(): array
    {
        return [
            'clients' => 'Gestion des clients', 'employes' => 'Gestion des employés',
            'stocks' => 'Gestion des stocks', 'facturation' => 'Facturation',
            'comptabilite' => 'Comptabilité', 'ventes' => 'Ventes', 'achats' => 'Achats',
            'rapports' => 'Rapports', 'documents' => 'Gestion des documents',
            'presence' => 'Présence des employés', 'paie' => 'Paie',
        ];
    }

    /* ── Lecture des réponses ────────────────────────────────────── */

    public function reponse(string $cle, $defaut = null)
    {
        return data_get($this->reponses, $cle, $defaut);
    }

    /**
     * Rend une réponse lisible : les listes deviennent une énumération,
     * les booléens un oui/non, le vide un tiret.
     */
    public function reponseLisible(string $cle): string
    {
        $valeur = $this->reponse($cle);

        if (is_array($valeur)) {
            return $valeur ? implode(', ', $valeur) : '—';
        }
        if (is_bool($valeur)) {
            return $valeur ? 'Oui' : 'Non';
        }

        return filled($valeur) ? (string) $valeur : '—';
    }
}
