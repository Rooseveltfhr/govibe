<?php

namespace Modules\Tagtoa\App\Services\Tax;

use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Support\Tax\Tax;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — le régime de taxe d'un commerce, résolu une fois par vente.
 *
 * Le POS encaisse des dizaines de lignes : relire les réglages du commerce à
 * chaque article ferait autant de requêtes. Ce profil les porte une fois, et
 * répond ensuite sans toucher la base.
 *
 * Il porte aussi la seule règle qui compte pour un article :
 *   • taux renseigné   → on l'applique, y compris 0 (exonéré assumé) ;
 *   • taux absent      → celui du commerce.
 *
 * Confondre « non renseigné » et « zéro » ferait taxer d'un coup tout ce que le
 * marchand avait volontairement sorti de l'assiette, le jour où il active la
 * taxe sur son commerce.
 */
class TaxProfile
{
    private function __construct(
        public readonly bool $enabled,
        public readonly ?float $rate,
        public readonly bool $inclusive,
        public readonly ?string $label,
        public readonly ?string $number,
    ) {
    }

    /** Le régime du commerce courant, ou du commerce nommé. */
    public static function current(?string $tenantId = null): self
    {
        $tenantId ??= Tenant::id();

        try {
            $commerce = Business::whereKey($tenantId)->first();
        } catch (\Throwable $e) {
            // Table absente (déploiement en cours) : pas de taxe plutôt qu'une
            // panne. Ne jamais empêcher d'encaisser.
            $commerce = null;
        }

        return self::fromBusiness($commerce);
    }

    public static function fromBusiness(?Business $commerce): self
    {
        $taux = $commerce ? Tax::rate($commerce->tax_rate) : null;
        $actif = (bool) ($commerce?->tax_enabled) && $taux !== null && $taux > 0;

        return new self(
            enabled:   $actif,
            rate:      $actif ? $taux : null,
            // Par défaut le prix affiché est ce que le client paie : c'est
            // l'usage en Haïti et dans les Caraïbes, et c'est le sens le moins
            // dangereux si le réglage n'a jamais été touché.
            inclusive: $commerce === null ? true : (bool) $commerce->tax_inclusive,
            label:     $commerce?->tax_label ?: ($actif ? 'Taxe' : null),
            number:    $commerce?->tax_number,
        );
    }

    /** Régime sans taxe — pour les tests et les commerces non assujettis. */
    public static function none(): self
    {
        return new self(false, null, true, null, null);
    }

    /**
     * Le taux qui s'applique à CET article.
     *
     * @param  mixed  $article  un Pos\Product ou un Menu\Item, ou null
     */
    public function rateFor(mixed $article): float
    {
        if (! $this->enabled) {
            return 0.0;
        }

        // Un taux écrit sur l'article gagne, y compris 0 : c'est une décision
        // du marchand (riz, médicaments), pas une case oubliée.
        $propre = $article ? Tax::rate($article->tax_rate ?? null) : null;

        return $propre ?? $this->rate ?? 0.0;
    }

    /** « TCA 10 % », ce qui s'imprime sur le reçu. */
    public function label(): string
    {
        return Tax::label($this->label, $this->rate);
    }
}
