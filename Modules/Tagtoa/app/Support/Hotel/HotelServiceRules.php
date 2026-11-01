<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Illuminate\Validation\Rule;

/**
 * TAGTOA HÔTEL — ce qu'un service d'hôtel peut déclarer. Classe pure ; le serveur revalide
 * tout contre ces règles (le formulaire n'est qu'un confort).
 */
class HotelServiceRules
{
    /** Natures de service : libellé, icône, équipe destinataire par défaut. */
    public const KINDS = [
        'room_service' => ['label' => 'Room service',            'icon' => 'fa-utensils',        'team' => 'kitchen'],
        'housekeeping' => ['label' => 'Ménage',                  'icon' => 'fa-broom',           'team' => 'housekeeping'],
        'linen'        => ['label' => 'Linge et articles',       'icon' => 'fa-shirt',           'team' => 'housekeeping'],
        'reception'    => ['label' => 'Réception',               'icon' => 'fa-bell-concierge',  'team' => 'reception'],
        'maintenance'  => ['label' => 'Maintenance',             'icon' => 'fa-screwdriver-wrench', 'team' => 'maintenance'],
        'reservation'  => ['label' => 'Réservation (spa, navette…)', 'icon' => 'fa-calendar-check', 'team' => 'reception'],
        'information'  => ['label' => 'Informations',            'icon' => 'fa-circle-info',     'team' => 'reception'],
    ];

    /** Équipes qui reçoivent les demandes (les rôles fins arrivent avec H6). */
    public const TEAMS = [
        'reception'    => 'Réception',
        'housekeeping' => 'Ménage',
        'maintenance'  => 'Maintenance',
        'kitchen'      => 'Cuisine',
    ];

    /** Langues du portail invité (décision H0). */
    public const LOCALES = ['ht', 'fr', 'en', 'es'];

    /** Services proposés par « Ajouter les services standards » (nom, nature). */
    public const DEFAULTS = [
        ['kind' => 'housekeeping', 'name' => 'Nettoyage de la chambre'],
        ['kind' => 'linen',        'name' => 'Serviettes supplémentaires'],
        ['kind' => 'linen',        'name' => 'Oreiller ou couverture'],
        ['kind' => 'reception',    'name' => 'Appeler la réception'],
        ['kind' => 'maintenance',  'name' => 'Signaler un problème (eau, électricité, climatisation)'],
        ['kind' => 'information',  'name' => 'Wi-Fi et informations utiles'],
    ];

    public const PRICE_MAX = 9999999;

    /** Plafond par hôtel : un catalogue, pas une base de données. */
    public const MAX_PER_HOTEL = 100;

    /** @return array<string,mixed> */
    public static function rules(string $tenantId): array
    {
        return [
            'kind'        => ['required', 'string', Rule::in(array_keys(self::KINDS))],
            'name'        => ['required', 'string', 'max:80'],
            'translations'   => ['nullable', 'array'],
            'translations.*' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'price'       => ['nullable', 'numeric', 'min:0', 'max:'.self::PRICE_MAX],
            'team'        => ['nullable', 'string', Rule::in(array_keys(self::TEAMS))],
            // Le room service pointe vers un Menu du MÊME hôtel, jamais celui d'un autre.
            'menu_id'     => ['nullable', 'required_if:kind,room_service', 'integer',
                Rule::exists('tagtoa_menus', 'id')->where('tenant_id', $tenantId)],
            'sort'        => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /** Normalise l'entrée validée : vides → null, traductions limitées aux langues connues. */
    public static function clean(array $in): array
    {
        $t = fn ($v) => ($v = trim((string) ($v ?? ''))) === '' ? null : $v;

        $tr = [];
        foreach (self::LOCALES as $l) {
            if ($v = $t($in['translations'][$l] ?? null)) {
                $tr[$l] = $v;
            }
        }

        $kind = (string) $in['kind'];

        return [
            'kind'         => $kind,
            'name'         => trim((string) $in['name']),
            'translations' => $tr ?: null,
            'description'  => $t($in['description'] ?? null),
            'price'        => ($p = $t($in['price'] ?? null)) === null ? null : round((float) $p, 2),
            'team'         => $t($in['team'] ?? null) ?? (self::KINDS[$kind]['team'] ?? null),
            // Un menu n'a de sens que pour le room service : ailleurs, jamais enregistré.
            'menu_id'      => $kind === 'room_service' ? (int) $in['menu_id'] : null,
            'sort'         => (int) ($in['sort'] ?? 0),
        ];
    }
}
