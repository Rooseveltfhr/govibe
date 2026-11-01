<?php

namespace Modules\Tagtoa\App\Services\Currency;

use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Currency\CurrencyRate;
use Modules\Tagtoa\App\Models\Currency\CurrencyRateHistory;
use Modules\Tagtoa\App\Support\Money;

/**
 * TAGTOA DEVISES (CUR-1) — taux de change saisis par le marchand, et conversion.
 *
 * Règles :
 *  - Un taux appartient à UN commerce (tenant_id passé explicitement, jamais deviné).
 *  - Chaque changement ajoute une ligne d'historique (qui, quand, ancien → nouveau).
 *  - Un saut de plus de 50 % exige une confirmation explicite (faute de frappe 1320 au lieu de 132).
 *  - La conversion se fait TOUJOURS ici, côté serveur ; un taux venu du navigateur n'est jamais lu.
 *  - Sans taux actif vers la devise principale, il n'y a PAS de conversion (null) : l'appelant
 *    refuse la vente plutôt que de vendre 5 G un article à 5 $.
 */
class CurrencyRateService
{
    /** Lettres majuscules ou symboles monétaires : « USD », « $HT », « EUR », « G ». */
    public const CODE_PATTERN = '/^[A-Z\p{Sc}]{1,6}$/u';

    public const RATE_MIN = 0.000001;

    public const RATE_MAX = 1000000;

    /** Au-delà de cet écart relatif avec l'ancien taux, une confirmation est exigée. */
    public const JUMP_CONFIRM = 0.5;

    public static function normalizeCode(?string $code): string
    {
        return mb_strtoupper(trim((string) $code));
    }

    /**
     * Crée ou met à jour le taux `code` → `base` du commerce `tenantId`.
     *
     * @param  array{code:string,rate:float|string,symbol?:?string,decimals?:int|string|null,confirm_jump?:bool}  $data
     *
     * @throws RateRuleException
     */
    public function save(string $tenantId, string $base, array $data, ?string $actor = null): CurrencyRate
    {
        $code = self::normalizeCode($data['code'] ?? '');
        $base = self::normalizeCode($base);
        $rate = (float) ($data['rate'] ?? 0);

        if (! preg_match(self::CODE_PATTERN, $code)) {
            throw new RateRuleException('invalid_code');
        }
        if ($code === $base) {
            throw new RateRuleException('same_as_base');
        }
        if (! is_finite($rate) || $rate < self::RATE_MIN || $rate > self::RATE_MAX) {
            throw new RateRuleException('invalid_rate');
        }

        try {
            return $this->saveOnce($tenantId, $base, $code, $rate, $data, $actor);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Deux premières sauvegardes simultanées (double clic) : la seconde met à jour.
            return $this->saveOnce($tenantId, $base, $code, $rate, $data, $actor);
        }
    }

    private function saveOnce(string $tenantId, string $base, string $code, float $rate, array $data, ?string $actor): CurrencyRate
    {
        return DB::transaction(function () use ($tenantId, $base, $code, $rate, $data, $actor) {
            $row = CurrencyRate::allTenants()->where('tenant_id', $tenantId)
                ->where('code', $code)->where('base_currency', $base)->lockForUpdate()->first();

            $old = $row ? (float) $row->rate : null;
            if ($old !== null && $old > 0 && abs($rate - $old) / $old > self::JUMP_CONFIRM && empty($data['confirm_jump'])) {
                throw new RateRuleException('jump_needs_confirmation');
            }

            $attrs = [
                'symbol'     => ($s = trim((string) ($data['symbol'] ?? ''))) === '' ? null : mb_substr($s, 0, 8),
                'decimals'   => max(0, min(3, (int) ($data['decimals'] ?? Money::meta($code)['decimals'] ?? 2))),
                'rate'       => $rate,
                'is_active'  => true,
                'updated_by' => $actor,
            ];

            if ($row) {
                $row->update($attrs);
            } else {
                $row = CurrencyRate::allTenants()->create($attrs + ['tenant_id' => $tenantId, 'code' => $code, 'base_currency' => $base]);
            }

            if ($old === null || abs($old - $rate) > 1e-9) {
                $this->history($row, $old, $rate, $old === null ? 'created' : 'rate', $actor);
            }

            return $row;
        });
    }

    /** Active / désactive. Un taux désactivé ne convertit plus rien : les articles concernés ne se vendent plus. */
    public function toggle(CurrencyRate $rate, ?string $actor = null): CurrencyRate
    {
        return DB::transaction(function () use ($rate, $actor) {
            $rate = CurrencyRate::allTenants()->whereKey($rate->id)->lockForUpdate()->firstOrFail();
            $rate->update(['is_active' => ! $rate->is_active, 'updated_by' => $actor]);
            $this->history($rate, (float) $rate->rate, (float) $rate->rate, $rate->is_active ? 'activated' : 'deactivated', $actor);

            return $rate;
        });
    }

    /**
     * Taux ACTIFS du commerce vers une devise principale, indexés par code.
     *
     * @return array<string, CurrencyRate>
     */
    public function activeFor(?string $tenantId, string $base): array
    {
        if (! $tenantId) {
            return [];
        }

        return CurrencyRate::allTenants()->where('tenant_id', $tenantId)
            ->where('base_currency', self::normalizeCode($base))->where('is_active', true)
            ->get()->keyBy('code')->all();
    }

    /**
     * Montant en devise `code` → devise principale `base`, arrondi aux décimales de `base`.
     * NULL quand aucun taux actif ne le permet.
     *
     * @param  array<string, CurrencyRate>  $rates  résultat de activeFor()
     */
    public static function convert(float $amount, ?string $code, string $base, array $rates): ?float
    {
        $code = self::normalizeCode($code);
        $base = self::normalizeCode($base);
        // Article dans la devise du menu (ou sans devise) : AUCUN arrondi supplémentaire.
        // Arrondir ici aux décimales de la devise (0 pour HTG) changerait 12,40 G en 12 G
        // sur tous les menus existants.
        if ($code === '' || $code === $base) {
            return round($amount, 2);
        }
        $r = $rates[$code] ?? null;
        if (! $r || ! $r->is_active) {
            return null;
        }

        // Montants convertis : 2 décimales (colonnes decimal(…,2)), le total reste la somme des lignes.
        return round($amount * (float) $r->rate, 2);
    }

    /**
     * Les taux qui ont été ACTIFS à un moment de la fenêtre [$from, $to], d'après l'historique
     * (ajout seul). Sert à juger un prix encaissé hors ligne : la caisse a chargé son catalogue
     * avant la vente, avec le taux d'alors — pas forcément celui d'aujourd'hui.
     *
     * @return array<int,float>
     */
    public function ratesActiveBetween(string $tenantId, string $code, string $base, \Carbon\CarbonInterface $from, \Carbon\CarbonInterface $to): array
    {
        $rows = \Modules\Tagtoa\App\Models\Currency\CurrencyRateHistory::allTenants()
            ->where('tenant_id', $tenantId)->where('code', self::normalizeCode($code))
            ->where('base_currency', self::normalizeCode($base))
            ->orderBy('created_at')->orderBy('id')->get();

        $segments = [];   // [début, taux, actif]
        $rate = null;
        $active = false;
        foreach ($rows as $h) {
            $rate = $h->new_rate !== null ? (float) $h->new_rate : $rate;
            $active = match ($h->event) {
                'deactivated' => false,
                default       => true,      // created / rate / activated
            };
            $segments[] = [$h->created_at, $rate, $active];
        }

        $out = [];
        foreach ($segments as $i => [$start, $r, $on]) {
            $end = $segments[$i + 1][0] ?? null;     // null = jusqu'à maintenant
            if ($on && $r !== null && $start->lte($to) && ($end === null || $end->gte($from))) {
                $out[] = $r;
            }
        }

        return array_values(array_unique($out));
    }

    /** Taux utilisé pour `code` (1 pour la devise principale), NULL si absent. */
    public static function rateOf(?string $code, string $base, array $rates): ?float
    {
        $code = self::normalizeCode($code);
        if ($code === '' || $code === self::normalizeCode($base)) {
            return 1.0;
        }

        return isset($rates[$code]) && $rates[$code]->is_active ? (float) $rates[$code]->rate : null;
    }

    /** Formate un montant dans une devise du marchand (personnalisée) ou connue. */
    public static function format(float $amount, ?string $code, string $base, array $rates): string
    {
        $code = self::normalizeCode($code) ?: self::normalizeCode($base);
        // Symbole choisi par le marchand en priorité ; sinon la présentation connue (USD → $5.00).
        if (isset($rates[$code]) && $rates[$code]->symbol) {
            return $rates[$code]->format($amount);
        }

        return Money::format($amount, $code);
    }

    /**
     * Devise de base de la CAISSE d'un commerce : la même pour le catalogue, le scanner, la vente
     * et la synchronisation, pour qu'un prix annoncé soit toujours le prix encaissé.
     */
    public static function posBase(?string $tenantId): string
    {
        $c = $tenantId ? \Modules\Tagtoa\App\Models\Business\Business::whereKey($tenantId)->value('currency') : null;

        return self::normalizeCode($c ?: (string) config('tagtoa.default_currency', 'HTG'));
    }

    private function history(CurrencyRate $r, ?float $old, ?float $new, string $event, ?string $actor): void
    {
        CurrencyRateHistory::allTenants()->create([
            'tenant_id' => $r->tenant_id, 'rate_id' => $r->id, 'code' => $r->code, 'base_currency' => $r->base_currency,
            'old_rate' => $old, 'new_rate' => $new, 'event' => $event, 'changed_by' => $actor,
        ]);
    }
}
