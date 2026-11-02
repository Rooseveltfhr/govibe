<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Illuminate\Validation\Rule;

/**
 * TAGTOA HÔTEL (HM-2) — types de chambre, plans tarifaires et « inclus ». Classe pure ; le serveur revalide tout
 * contre ces règles (le formulaire n'est qu'un confort).
 */
class HotelRoomTypeRules
{
    /** Ce qui peut être inclus dans un prix : clé => [libellé, icône]. Le marchand peut aussi écrire ses propres libellés. */
    public const INCLUSIONS = [
        'breakfast'        => ['label' => 'Petit déjeuner',            'icon' => 'fa-mug-hot'],
        'lunch'            => ['label' => 'Déjeuner',                  'icon' => 'fa-utensils'],
        'dinner'           => ['label' => 'Dîner',                     'icon' => 'fa-bell-concierge'],
        'water'            => ['label' => 'Eau',                       'icon' => 'fa-bottle-water'],
        'pool'             => ['label' => 'Accès à la piscine',        'icon' => 'fa-person-swimming'],
        'air_con'          => ['label' => 'Chambre climatisée',        'icon' => 'fa-snowflake'],
        'fan'              => ['label' => 'Chambre avec ventilateur',  'icon' => 'fa-fan'],
        'wifi'             => ['label' => 'Wi-Fi',                     'icon' => 'fa-wifi'],
        'parking'          => ['label' => 'Parking',                   'icon' => 'fa-square-parking'],
        'airport_transfer' => ['label' => 'Transport à l\'aéroport',   'icon' => 'fa-van-shuttle'],
        'towels'           => ['label' => 'Serviettes',                'icon' => 'fa-bath'],
        'tv'               => ['label' => 'Télévision',                'icon' => 'fa-tv'],
        // Les services de l'hôtel (jamais payants) : on les COCHE ici comme « inclus » dans le prix de la chambre.
        'housekeeping'     => ['label' => 'Ménage',                    'icon' => 'fa-broom'],
        'linen'            => ['label' => 'Linge et articles',         'icon' => 'fa-shirt'],
        'reception'        => ['label' => 'Réception',                 'icon' => 'fa-bell-concierge'],
        'maintenance'      => ['label' => 'Maintenance',               'icon' => 'fa-screwdriver-wrench'],
    ];

    /** Durées proposées pour un « moment » (en heures). */
    public const BLOCK_HOURS = [1, 2, 3, 4, 6, 12];

    public const KINDS = ['night' => 'Nuit', 'block' => 'Moment'];

    public const PRICE_MAX = 99999999;
    public const MAX_TYPES = 30;
    public const MAX_CUSTOM_INCLUSIONS = 6;
    public const CUSTOM_MAX_LENGTH = 60;

    /** @return array<string,mixed> */
    public static function typeRules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'capacity'    => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /** @param  array<int,string>  $devises  codes de devises acceptés pour ce commerce */
    public static function planRules(array $devises): array
    {
        return [
            'kind'         => ['required', Rule::in(array_keys(self::KINDS))],
            'hours'        => ['nullable', 'required_if:kind,block', 'integer', Rule::in(self::BLOCK_HOURS)],
            'price'        => ['required', 'numeric', 'min:0', 'max:'.self::PRICE_MAX],
            'currency'     => ['required', 'string', Rule::in($devises)],
            'inclusions'   => ['nullable', 'array', 'max:'.count(self::INCLUSIONS)],
            'inclusions.*' => ['string', Rule::in(array_keys(self::INCLUSIONS))],
            'custom'       => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array{name:string,description:?string,capacity:?int} */
    public static function cleanType(array $in): array
    {
        $desc = trim((string) ($in['description'] ?? ''));

        return [
            'name'        => trim((string) $in['name']),
            'description' => $desc === '' ? null : $desc,
            'capacity'    => isset($in['capacity']) && $in['capacity'] !== '' ? (int) $in['capacity'] : null,
        ];
    }

    /** Plan prêt à écrire : l'heure n'existe que pour un « moment », jamais pour une nuit. */
    public static function cleanPlan(array $in): array
    {
        $block = ($in['kind'] ?? null) === 'block';

        return [
            'kind'       => $block ? 'block' : 'night',
            'hours'      => $block ? (int) $in['hours'] : null,
            'slot'       => $block ? (int) $in['hours'] : 0,
            'price'      => round((float) $in['price'], 2),
            'currency'   => strtoupper((string) $in['currency']),
            'inclusions' => self::cleanInclusions($in['inclusions'] ?? [], (string) ($in['custom'] ?? '')),
        ];
    }

    /**
     * Forme stockée : ['keys' => clés du catalogue (dédoublonnées, ordre du catalogue), 'custom' => libellés libres].
     * Un libellé libre est du texte brut : jamais de balise, longueur et nombre bornés, sans doublon.
     *
     * @return array{keys:array<int,string>,custom:array<int,string>}
     */
    public static function cleanInclusions(mixed $keys, string $custom): array
    {
        $keys = is_array($keys) ? array_values(array_filter($keys, 'is_string')) : [];
        $cles = array_values(array_filter(array_keys(self::INCLUSIONS), fn ($k) => in_array($k, $keys, true)));

        $libres = [];
        foreach (preg_split('/\R/u', $custom) ?: [] as $ligne) {
            $ligne = trim(preg_replace('/\s+/u', ' ', strip_tags($ligne)) ?? '');
            if ($ligne === '') {
                continue;
            }
            $ligne = mb_substr($ligne, 0, self::CUSTOM_MAX_LENGTH);
            if (in_array(mb_strtolower($ligne), array_map('mb_strtolower', $libres), true)) {
                continue;
            }
            $libres[] = $ligne;
            if (count($libres) >= self::MAX_CUSTOM_INCLUSIONS) {
                break;
            }
        }

        return ['keys' => $cles, 'custom' => $libres];
    }

    /**
     * Pour l'affichage : [['label' => ..., 'icon' => ...], ...]. Une clé inconnue (catalogue qui évolue) est ignorée
     * en silence ; un libellé libre garde l'icône par défaut. Tolère une valeur absente ou mal formée.
     *
     * @return array<int,array{label:string,icon:string}>
     */
    public static function describe(mixed $stored): array
    {
        $stored = is_array($stored) ? $stored : [];
        $items = [];
        foreach (is_array($stored['keys'] ?? null) ? $stored['keys'] : [] as $k) {
            if (is_string($k) && isset(self::INCLUSIONS[$k])) {
                $items[] = ['label' => __(self::INCLUSIONS[$k]['label']), 'icon' => self::INCLUSIONS[$k]['icon']];
            }
        }
        foreach (is_array($stored['custom'] ?? null) ? $stored['custom'] : [] as $c) {
            if (is_string($c) && trim($c) !== '') {
                $items[] = ['label' => $c, 'icon' => 'fa-check'];
            }
        }

        return $items;
    }
}
