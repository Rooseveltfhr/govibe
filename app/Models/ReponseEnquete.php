<?php

namespace App\Models;

use App\Enquete\Questionnaire;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Une réponse à l'enquête « Enquête sur vos besoins ».
 *
 * Les champs à choix stockent une clé, jamais un libellé : c'est ce qui permet
 * de compter ensemble une réponse donnée en français et la même donnée en
 * créole. Les libellés viennent du Questionnaire au moment de l'affichage.
 */
class ReponseEnquete extends Model
{
    use HasUuids;

    protected $table = 'reponses_enquete';

    protected $fillable = [
        'uuid', 'reference', 'langue',
        'entreprise', 'type_activite', 'type_activite_autre', 'ville', 'departement',
        'effectif', 'anciennete', 'points_vente', 'volume',
        'outils', 'materiel', 'encaissements', 'connexion', 'presences',
        'essai', 'essai_raison',
        'difficultes', 'perte_de_temps', 'pertes', 'priorite', 'freins',
        'solutions', 'budget', 'echeance',
        'souhaite_contact', 'nom_contact', 'whatsapp', 'email',
        'statut', 'notes_internes', 'traitee_par', 'traitee_le', 'ip',
    ];

    protected $casts = [
        'outils' => 'array',
        'materiel' => 'array',
        'encaissements' => 'array',
        'presences' => 'array',
        'difficultes' => 'array',
        'freins' => 'array',
        'solutions' => 'array',
        'souhaite_contact' => 'boolean',
        'traitee_le' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'traitee_par');
    }

    public static function genererReference(): string
    {
        do {
            $reference = 'EQ-'.now()->format('Ymd').'-'.strtoupper(Str::random(4));
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    // ── Libellés, dans la langue demandée ────────────────

    private function enLangue(?string $langue): string
    {
        return Questionnaire::langueValide($langue ?: $this->langue);
    }

    /**
     * Le type d'activité. « Autre » rend ce que la personne a écrit : sinon
     * l'ERP afficherait « Autre » sur des dizaines de fiches sans jamais dire
     * de quelles activités il s'agit.
     */
    public function typeLibelle(?string $langue = null): string
    {
        $l = $this->enLangue($langue);

        if ($this->type_activite === 'autre' && filled($this->type_activite_autre)) {
            return $this->type_activite_autre;
        }

        return Questionnaire::libelle(Questionnaire::typesActivite(), $this->type_activite, $l)
            ?? $this->type_activite;
    }

    public function departementLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::departements(), $this->departement, $this->enLangue($langue));
    }

    public function effectifLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::effectifs(), $this->effectif, $this->enLangue($langue));
    }

    public function ancienneteLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::anciennetes(), $this->anciennete, $this->enLangue($langue));
    }

    public function connexionLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::connexions(), $this->connexion, $this->enLangue($langue));
    }

    public function pointsVenteLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::pointsVente(), $this->points_vente, $this->enLangue($langue));
    }

    public function volumeLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::volumes(), $this->volume, $this->enLangue($langue));
    }

    public function essaiLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::essais(), $this->essai, $this->enLangue($langue));
    }

    public function echeanceLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::echeances(), $this->echeance, $this->enLangue($langue));
    }

    public function budgetLibelle(?string $langue = null): ?string
    {
        return Questionnaire::libelle(Questionnaire::budgets(), $this->budget, $this->enLangue($langue));
    }

    public function statutLibelle(?string $langue = null): string
    {
        return Questionnaire::libelle(Questionnaire::statuts(), $this->statut, $this->enLangue($langue))
            ?? $this->statut;
    }

    /** @return array<int, string> */
    public function outilsLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::outils(), $this->outils, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function encaissementsLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::encaissements(), $this->encaissements, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function presencesLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::presences(), $this->presences, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function difficultesLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::difficultes(), $this->difficultes, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function materielLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::materiels(), $this->materiel, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function freinsLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::freins(), $this->freins, $this->enLangue($langue));
    }

    /** @return array<int, string> */
    public function solutionsLibelles(?string $langue = null): array
    {
        return Questionnaire::libelles(Questionnaire::solutions(), $this->solutions, $this->enLangue($langue));
    }

    /** Comment nommer le répondant quand il n'a pas donné de nom d'entreprise. */
    public function getIntituleAttribute(): string
    {
        return $this->entreprise
            ?: ($this->nom_contact ?: $this->typeLibelle());
    }

    // ── Portées ──────────────────────────────────────────

    public function scopeNouvelles(Builder $q): Builder
    {
        return $q->where('statut', 'nouvelle');
    }

    public function scopeARappeler(Builder $q): Builder
    {
        return $q->where('souhaite_contact', true)->whereIn('statut', ['nouvelle', 'lue']);
    }

    /**
     * Compte combien de fois chaque clé revient dans une colonne à choix
     * multiples — c'est tout l'intérêt d'une enquête : savoir ce qui remonte
     * le plus souvent, pas seulement lire les fiches une par une.
     *
     * Le comptage se fait en PHP et non en SQL : la colonne est du JSON, et
     * les fonctions JSON diffèrent entre SQLite et MySQL. À l'échelle d'une
     * enquête, quelques milliers de lignes se parcourent sans peine.
     *
     * @return array<string, int> clés triées de la plus fréquente à la moins
     */
    public static function frequences(string $colonne, ?string $typeActivite = null): array
    {
        $requete = static::query()->select($colonne);

        if ($typeActivite) {
            $requete->where('type_activite', $typeActivite);
        }

        $compte = [];

        foreach ($requete->get() as $reponse) {
            foreach ((array) $reponse->{$colonne} as $cle) {
                $compte[$cle] = ($compte[$cle] ?? 0) + 1;
            }
        }

        arsort($compte);

        return $compte;
    }
}
