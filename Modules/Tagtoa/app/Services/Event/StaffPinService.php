<?php

namespace Modules\Tagtoa\App\Services\Event;

/**
 * TAGTOA EVENT — logique d'authentification staff par PIN (terrain).
 *
 * Toute la logique ici est PURE (testable sans Laravel) : normalisation,
 * validation, hachage bcrypt natif (password_hash), et matrice
 * rôle → écran. Le PIN est opérationnel seulement (vente carte / check-in) —
 * jamais un accès financier ou de configuration.
 */
class StaffPinService
{
    /** Rôles autorisés dans ce module (pas de POS/marchand). */
    public const ROLES = ['admin', 'vente', 'checkin'];

    /** Écrans terrain adressables. */
    public const SCREENS = ['staff', 'sales', 'pickup', 'checkin', 'dashboard', 'sync'];

    /** Matrice rôle → écrans autorisés. 'admin' a accès à tout. */
    protected const ACCESS = [
        'vente'   => ['sales', 'pickup'],
        'checkin' => ['checkin'],
    ];

    /** Garde seulement les chiffres du PIN saisi. PUR. */
    public static function normalizePin(string $pin): string
    {
        return preg_replace('/\D+/', '', $pin) ?? '';
    }

    /** PIN valide = 4 à 6 chiffres (après normalisation). PUR. */
    public static function isValidPinFormat(string $pin): bool
    {
        $len = strlen(self::normalizePin($pin));

        return $len >= 4 && $len <= 6;
    }

    /** Rôle connu ? PUR. */
    public static function isValidRole(string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    /** Hachage bcrypt natif du PIN (sans façade Laravel). PUR. */
    public static function hashPin(string $pin): string
    {
        return password_hash(self::normalizePin($pin), PASSWORD_BCRYPT);
    }

    /** Vérifie un PIN contre son hash. Hash vide => false. PUR. */
    public static function verifyPin(string $pin, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify(self::normalizePin($pin), $hash);
    }

    /** Le rôle a-t-il accès à cet écran ? admin = tout. PUR. */
    public static function canAccess(string $role, string $screen): bool
    {
        if ($role === 'admin') {
            return true;
        }

        return in_array($screen, self::ACCESS[$role] ?? [], true);
    }
}
