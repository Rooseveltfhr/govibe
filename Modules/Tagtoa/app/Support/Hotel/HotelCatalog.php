<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Illuminate\Validation\Rule;
use Modules\Tagtoa\App\Support\Locale;

/**
 * TAGTOA HÔTEL — ce qu'un hôtel peut déclarer, et comment on le valide. Classe pure : le
 * formulaire n'est qu'un confort d'affichage, le serveur revalide tout contre ces règles.
 *
 * Les installations viennent d'une liste fermée (pas de texte libre qui finirait en balise
 * HTML ou en doublon « Piscine » / « piscine »). Les langues sont celles que TAGTOA sait
 * réellement afficher (Support\Locale), jamais une langue promise sans traduction.
 */
class HotelCatalog
{
    /** clé => libellé (français : c'est la clé de traduction du module). */
    public const FACILITIES = [
        'wifi'         => 'Wi-Fi',
        'air_con'      => 'Climatisation',
        'pool'         => 'Piscine',
        'restaurant'   => 'Restaurant',
        'bar'          => 'Bar',
        'spa'          => 'Spa',
        'parking'      => 'Parking',
        'generator'    => 'Groupe électrogène / inverter',
        'shuttle'      => 'Navette',
        'laundry'      => 'Blanchisserie',
        'conference'   => 'Salle de conférence',
        'beach'        => 'Accès plage',
        'security'     => 'Sécurité 24h/24',
        'room_service' => 'Service en chambre',
    ];

    /** Règles de validation, préfixées `hotel.`. Tout est facultatif. */
    public static function rules(): array
    {
        return [
            'hotel'                       => ['nullable', 'array'],
            'hotel.description'           => ['nullable', 'string', 'max:1000'],
            'hotel.rooms_count'           => ['nullable', 'integer', 'min:1', 'max:2000'],
            'hotel.reception_phone'       => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-.\s]{5,40}$/'],
            'hotel.reception_email'       => ['nullable', 'email:rfc', 'max:120'],
            'hotel.reception_24h'         => ['nullable', 'boolean'],
            'hotel.reception_hours'       => ['nullable', 'string', 'max:80'],
            'hotel.facilities'            => ['nullable', 'array', 'max:'.count(self::FACILITIES)],
            'hotel.facilities.*'          => ['string', Rule::in(array_keys(self::FACILITIES))],
            'hotel.languages'             => ['nullable', 'array', 'max:10'],
            'hotel.languages.*'           => ['string', Rule::in(Locale::codes())],
            'hotel.room_service_enabled'  => ['nullable', 'boolean'],
            'hotel.guest_info'            => ['nullable', 'string', 'max:1500'],
            'hotel_cover'                 => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    /**
     * Ce qu'on enregistre, depuis une entrée DÉJÀ validée. Chaînes vides → null, listes
     * dédoublonnées, aucune clé inconnue. `reception_hours` n'a de sens que hors 24h/24.
     */
    public static function clean(array $in): array
    {
        $texte = fn ($v) => ($v = trim((string) ($v ?? ''))) === '' ? null : $v;
        $liste = fn ($v, array $permis) => array_values(array_intersect($permis, array_map('strval', (array) ($v ?? []))));

        $h24 = ! array_key_exists('reception_24h', $in) || filter_var($in['reception_24h'], FILTER_VALIDATE_BOOLEAN);

        return [
            'description'          => $texte($in['description'] ?? null),
            'rooms_count'          => isset($in['rooms_count']) && $in['rooms_count'] !== '' ? (int) $in['rooms_count'] : null,
            'reception_phone'      => $texte($in['reception_phone'] ?? null),
            'reception_email'      => $texte($in['reception_email'] ?? null),
            'reception_24h'        => $h24,
            'reception_hours'      => $h24 ? null : $texte($in['reception_hours'] ?? null),
            'facilities'           => $liste($in['facilities'] ?? [], array_keys(self::FACILITIES)),
            'languages'            => $liste($in['languages'] ?? [], Locale::codes()),
            'room_service_enabled' => filter_var($in['room_service_enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'guest_info'           => $texte($in['guest_info'] ?? null),
        ];
    }
}
