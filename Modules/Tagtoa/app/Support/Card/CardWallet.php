<?php

namespace Modules\Tagtoa\App\Support\Card;

/**
 * TAGTOA CARD — helpers PURS de la carte NFC prépayée « closed-loop » TAGTOA,
 * testables sans Laravel ni réseau.
 *
 * Une carte TAGTOA est une carte NFC (NTAG213/216) qui porte un SOLDE dans
 * l'écosystème TAGTOA. Le client recharge (cash chez un marchand, ou en ligne),
 * puis paie chez N'IMPORTE QUEL marchand TAGTOA en tapant la carte + PIN.
 * « Closed-loop » = l'argent reste dans TAGTOA (aucun réseau carte externe).
 *
 * L'UID NFC n'est JAMAIS stocké/recherché en clair : on normalise puis on hashe.
 * Les montants sont manipulés en UNITÉS MINEURES (entiers) — aucune erreur de
 * virgule flottante sur un solde.
 */
class CardWallet
{
    public const STATUS_ACTIVE  = 'active';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_LOST    = 'lost';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_BLOCKED, self::STATUS_LOST];

    public const TYPE_TOPUP  = 'topup';
    public const TYPE_CHARGE = 'charge';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUST = 'adjust';

    /**
     * Normalise un UID NFC : retire séparateurs (: - espaces) et met en
     * majuscules, de sorte que « 04:A2:1B » et « 04a21b » désignent la même carte. PUR.
     */
    public static function normalizeUid(string $uid): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', trim($uid)) ?? '');
    }

    /** Hash stable d'un UID NFC (jamais l'UID en clair). Sel optionnel. PUR. */
    public static function hashUid(string $uid, string $salt = ''): string
    {
        return hash('sha256', $salt.self::normalizeUid($uid));
    }

    /** Masque un UID pour affichage : garde les 4 derniers. PUR. */
    public static function maskUid(string $uid): string
    {
        $n = self::normalizeUid($uid);
        $len = strlen($n);
        if ($len <= 4) {
            return $n;
        }

        return str_repeat('•', $len - 4).substr($n, -4);
    }

    /** Une carte peut-elle dépenser ? (statut actif seulement). PUR. */
    public static function isSpendable(?string $status): bool
    {
        return $status === self::STATUS_ACTIVE;
    }

    /** Le solde couvre-t-il le montant demandé ? (montant > 0). PUR. Unités mineures. */
    public static function canCharge(int $balanceMinor, int $amountMinor): bool
    {
        return $amountMinor > 0 && $balanceMinor >= $amountMinor;
    }

    /** Nouveau solde après un débit (jamais négatif). PUR. Unités mineures. */
    public static function applyCharge(int $balanceMinor, int $amountMinor): int
    {
        return max(0, $balanceMinor - max(0, $amountMinor));
    }

    /** Nouveau solde après une recharge. PUR. Unités mineures. */
    public static function applyTopup(int $balanceMinor, int $amountMinor): int
    {
        return $balanceMinor + max(0, $amountMinor);
    }

    /** Un PIN de carte est-il au bon format ? (4 à 6 chiffres) ou vide (pas de PIN). PUR. */
    public static function isValidPinFormat(?string $pin): bool
    {
        if ($pin === null || $pin === '') {
            return true; // carte sans PIN autorisée
        }

        return (bool) preg_match('/^\d{4,6}$/', $pin);
    }

    /** Génère une référence de transaction carte (idempotence). */
    public static function reference(): string
    {
        return 'CARD-'.strtoupper(bin2hex(random_bytes(5)));
    }

    /**
     * Quota effectif d'activation de cartes officielles : limite du forfait
     * (null = illimité) + crédits d'activation achetés. PUR.
     *
     * @return int|null null = illimité
     */
    public static function effectiveQuota($planLimit, int $credits): ?int
    {
        if ($planLimit === null) {
            return null; // forfait illimité
        }

        return max(0, (int) $planLimit) + max(0, $credits);
    }

    /** Peut-on activer une carte de plus ? (quota null = illimité). PUR. */
    public static function canActivate($planLimit, int $credits, int $usage): bool
    {
        $quota = self::effectiveQuota($planLimit, $credits);

        return $quota === null || $usage < $quota;
    }

    /**
     * Une activation consomme-t-elle un crédit acheté ? Oui seulement quand
     * l'usage dépasse le quota de base du forfait (les crédits complètent). PUR.
     */
    public static function consumesCredit($planLimit, int $usage): bool
    {
        if ($planLimit === null) {
            return false; // illimité → jamais de crédit
        }

        return $usage >= max(0, (int) $planLimit);
    }
}
