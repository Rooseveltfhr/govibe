<?php

namespace Modules\Tagtoa\App\Support\Hotel;

use Illuminate\Validation\Rule;

/**
 * TAGTOA HÔTEL — ce qu'une chambre peut déclarer. Classe pure (testable sans base) ; le
 * formulaire n'est qu'un confort d'affichage, le serveur revalide tout contre ces règles.
 */
class HotelRoomRules
{
    /** clé => libellé (français : clé de traduction du module). */
    public const TYPES = [
        'single'    => 'Simple',
        'double'    => 'Double',
        'twin'      => 'Twin',
        'triple'    => 'Triple',
        'family'    => 'Familiale',
        'standard'  => 'Standard',
        'deluxe'    => 'Deluxe',
        'suite'     => 'Suite',
        'bungalow'  => 'Bungalow',
        'apartment' => 'Appartement',
    ];

    /** Plafond d'une création en série : empêche de remplir la base d'un seul clic. */
    public const BULK_MAX = 200;

    /** Lettres, chiffres, espace, point, tiret, souligné, barre oblique : jamais de balise. */
    public const NUMBER_PATTERN = '/^[\p{L}\p{N}][\p{L}\p{N} .\-_\/]{0,19}$/u';

    /** @return array<string,mixed> */
    public static function rules(string $tenantId, ?int $ignoreId = null): array
    {
        return [
            'number' => ['required', 'string', 'max:20', 'regex:'.self::NUMBER_PATTERN,
                Rule::unique('tagtoa_hotel_rooms', 'number')->where('tenant_id', $tenantId)->ignore($ignoreId)],
            'name'   => ['nullable', 'string', 'max:80'],
            'type'   => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
            'floor'  => ['nullable', 'string', 'max:10'],
            'notes'  => ['nullable', 'string', 'max:300'],
        ];
    }

    /** Règles de la création en série (de 101 à 120). */
    public static function bulkRules(): array
    {
        return [
            'from' => ['required', 'integer', 'min:0', 'max:99999'],
            'to'   => ['required', 'integer', 'min:0', 'max:99999', 'gte:from'],
            'type' => ['nullable', 'string', Rule::in(array_keys(self::TYPES))],
        ];
    }

    /** Chaînes vides → null ; rien d'autre n'est enregistré. */
    public static function clean(array $in): array
    {
        $t = fn ($v) => ($v = trim((string) ($v ?? ''))) === '' ? null : $v;

        return [
            'number' => trim((string) ($in['number'] ?? '')),
            'name'   => $t($in['name'] ?? null),
            'type'   => $t($in['type'] ?? null),
            'floor'  => $t($in['floor'] ?? null),
            'notes'  => $t($in['notes'] ?? null),
        ];
    }
}
