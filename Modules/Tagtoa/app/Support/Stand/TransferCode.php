<?php

namespace Modules\Tagtoa\App\Support\Stand;

/**
 * TAGTOA SMART STAND — le code d'une cession.
 *
 * Il ne ressemble PAS au secret gratté, et c'est délibéré. Le secret sous le
 * panneau prouve qu'on tient l'objet ; ce code-ci prouve que le propriétaire
 * précédent a voulu céder. Deux autorités différentes, deux codes différents —
 * les confondre reviendrait à dire que tenir l'objet suffit pour en devenir
 * propriétaire alors qu'il est déjà à quelqu'un.
 *
 * ── Longueur ────────────────────────────────────────────────────────────
 *
 * Douze caractères sur l'alphabet Crockford, soit 32¹² ≈ 6 × 10¹⁷ (60 bits).
 * Le secret gratté n'en fait que huit (40 bits) et cela suffit, parce qu'il est
 * protégé par une limite de dix essais par stand. Ce code-ci circule sur
 * WhatsApp et se présente sans qu'on sache d'avance à quelle offre il
 * appartient : on ne peut donc pas compter les essais « par offre » avant de
 * l'avoir reconnu. La marge est prise sur la longueur plutôt que sur une
 * limitation qui arriverait trop tard.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class TransferCode
{
    public const LENGTH = 12;

    /** Fabrique un code. NON PUR par nature — il doit être imprévisible. */
    public static function make(): string
    {
        // Même source que le secret des stands : `random_int`, et rien d'autre.
        // `rand()` se déduit de quelques tirages, et un marchand qui a vu deux
        // codes pourrait alors deviner ceux des autres.
        return StandId::makeSecret(self::LENGTH);
    }

    /**
     * Code nettoyé, prêt à comparer. PUR.
     *
     * Délègue à `StandId::normalizeSecret` : les corrections Crockford (O→0,
     * I/L→1, U→V) sont écrites à UN seul endroit. Les recopier ici les ferait
     * diverger au premier ajustement, et un code juste serait alors refusé
     * d'un côté et accepté de l'autre.
     */
    public static function normalize(?string $code): string
    {
        return mb_substr(StandId::normalizeSecret($code), 0, self::LENGTH);
    }

    /** Ce code a-t-il la forme attendue ? PUR. */
    public static function isValid(?string $code): bool
    {
        return strlen(self::normalize($code)) === self::LENGTH;
    }

    /**
     * Ce qui est conservé en base. PUR.
     *
     * Le clair n'entre jamais : il est montré une fois au cédant, puis oublié.
     * Une fuite de la base ne donne alors les stands de personne.
     */
    public static function fingerprint(?string $code): string
    {
        return hash('sha256', self::normalize($code));
    }

    /**
     * Code groupé pour l'écran : « A3F9-K2MP-7XQR ». PUR.
     *
     * Douze caractères d'affilée se recopient mal ; en trois groupes de
     * quatre, l'œil ne perd pas sa place. Le groupement est cosmétique — la
     * normalisation retire les tirets avant toute comparaison.
     */
    public static function pretty(?string $code): string
    {
        $c = self::normalize($code);

        return strlen($c) === self::LENGTH
            ? implode('-', str_split($c, 4))
            : $c;
    }

    /**
     * Entropie du code, en bits. PUR.
     *
     * Sert au test de garde : si quelqu'un raccourcit le code « pour faciliter
     * la saisie », la sécurité s'effondre sans qu'aucune fonctionnalité ne
     * cesse de marcher — exactement la régression qu'on ne voit jamais.
     */
    public static function entropyBits(): float
    {
        return round(self::LENGTH * log(strlen(StandId::ALPHABET), 2), 2);
    }
}
