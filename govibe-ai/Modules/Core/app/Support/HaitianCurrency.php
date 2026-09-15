<?php

namespace Modules\Core\Support;

/**
 * Konvansyon lajan ayisyen an.
 *
 * An Ayiti anpil moun site pri an « dola ayisyen »: 1 dola ayisyen = 5 goud.
 * Se pa dola ameriken. Yon kliyan ki di « sa fè 100 dola » ap pale de
 * 500 goud — epi yon ajan ki konprann sa mal ap bay yon pri ki senk fwa fo.
 *
 * Se poutèt sa kalkil sa a viv NAN KÒD, pa nan yon konsiy. Yon modèl lang
 * bay bon repons lan pifò tan; yon fonksyon teste bay li chak fwa. Lè yon
 * erè vle di yon kliyan peye senk fwa twòp, « pifò tan » pa ase.
 *
 * Klas pi — okenn E/S, okenn depandans framework.
 */
final class HaitianCurrency
{
    /** Konvansyon ki fikse depi lontan, li pa yon to chanj k ap flote. */
    public const GOURDES_PER_HAITIAN_DOLLAR = 5;

    /** « 500 goud » — nou pa mete santim lè pa gen santim. */
    public static function formatGourdes(int $gourdes): string
    {
        return number_format($gourdes, 0, '.', ' ').' goud';
    }

    /**
     * Konbyen dola ayisyen yon montan an goud fè.
     *
     * Retounen null lè montan an PA divizib egzakteman pa 5. Nou prefere pa
     * di anyen pase di yon chif awondi ki fè kliyan an tann yon lòt pri.
     */
    public static function toHaitianDollars(int $gourdes): ?int
    {
        if ($gourdes % self::GOURDES_PER_HAITIAN_DOLLAR !== 0) {
            return null;
        }

        return intdiv($gourdes, self::GOURDES_PER_HAITIAN_DOLLAR);
    }

    public static function fromHaitianDollars(int $haitianDollars): int
    {
        return $haitianDollars * self::GOURDES_PER_HAITIAN_DOLLAR;
    }

    /**
     * Fraz pri a, ak ekivalans lan sèlman lè li egzat.
     *
     *   500 goud  ->  « 500 goud (100 dola ayisyen) »
     *   123 goud  ->  « 123 goud »
     */
    public static function describe(int $gourdes, string $language = 'ht'): string
    {
        $amount = self::formatGourdes($gourdes);
        $dollars = self::toHaitianDollars($gourdes);

        if ($dollars === null) {
            return $amount;
        }

        return match ($language) {
            'fr' => sprintf('%s (%d dollars haïtiens)', $amount, $dollars),
            'en' => sprintf('%s (%d Haitian dollars)', $amount, $dollars),
            default => sprintf('%s (%d dola ayisyen)', $amount, $dollars),
        };
    }
}
