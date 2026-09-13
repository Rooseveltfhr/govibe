<?php

namespace Modules\Tagtoa\App\Support\Tax;

/**
 * TAGTOA — la taxe sur une vente : base, taxe, total.
 *
 * Deux façons d'afficher un prix cohabitent dans le monde, et se tromper de
 * convention n'est pas un détail d'affichage :
 *
 *   • TAXE COMPRISE — le prix sur l'étiquette est ce que le client paie. C'est
 *     l'usage en Haïti, dans les Caraïbes et en Afrique de l'Ouest. Le commerce
 *     doit alors EXTRAIRE la taxe du prix affiché ;
 *   • HORS TAXE — la taxe s'ajoute à la caisse. Usage nord-américain.
 *
 * Se tromper de sens, c'est soit facturer 10 % de trop au client, soit payer la
 * taxe de sa poche à chaque vente. D'où un réglage explicite par commerce,
 * jamais une valeur devinée.
 *
 * LE PROBLÈME DU CENTIME. Une taxe extraite donne presque toujours un nombre
 * qui ne tombe pas juste : 100 / 1,1 = 90,909… Arrondir la base et la taxe
 * chacune de leur côté donne 90,91 + 9,09 = 100,00 par chance, mais
 * 0,01 d'écart dans d'autres cas. Un reçu dont les lignes ne totalisent pas le
 * montant encaissé est un reçu qu'un comptable refuse. Ici la taxe est
 * TOUJOURS le reste : total − base. Elle absorbe l'arrondi, et la somme tombe
 * juste par construction.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class Tax
{
    /** Taux par défaut proposé : la TCA haïtienne. */
    public const DEFAULT_RATE = 10.0;

    /** Un article exonéré. Distinct de « pas de taxe du tout » côté commerce. */
    public const EXEMPT = 0.0;

    /**
     * Ce que les commerces de la région appellent leur taxe, et à quel taux.
     * Proposé à l'onboarding ; le marchand reste libre de saisir autre chose.
     */
    public const SUGGESTED = [
        'HT' => ['label' => 'TCA',   'rate' => 10.0, 'country' => 'Haïti'],
        'DO' => ['label' => 'ITBIS', 'rate' => 18.0, 'country' => 'République dominicaine'],
        'SN' => ['label' => 'TVA',   'rate' => 18.0, 'country' => 'Sénégal'],
        'CI' => ['label' => 'TVA',   'rate' => 18.0, 'country' => "Côte d'Ivoire"],
        'JM' => ['label' => 'GCT',   'rate' => 15.0, 'country' => 'Jamaïque'],
        'FR' => ['label' => 'TVA',   'rate' => 20.0, 'country' => 'France'],
    ];

    /** Taux acceptable ? Un taux négatif ou délirant est refusé. PUR. */
    public static function isValidRate(mixed $rate): bool
    {
        return is_numeric($rate) && (float) $rate >= 0 && (float) $rate < 100;
    }

    /** Taux normalisé, sinon null (« non renseigné », pas « zéro »). PUR. */
    public static function rate(mixed $rate): ?float
    {
        return self::isValidRate($rate) ? round((float) $rate, 3) : null;
    }

    /**
     * Décompose un montant en base et taxe. PUR.
     *
     * @param  float  $montant     le prix de la ligne, remise déjà appliquée
     * @param  float  $taux        en pourcentage (10 = 10 %)
     * @param  bool   $comprise    le montant contient-il déjà la taxe ?
     * @return array{base:float, tax:float, total:float}
     */
    public static function split(float $montant, ?float $taux, bool $comprise): array
    {
        $taux = self::rate($taux) ?? 0.0;

        if ($taux <= 0 || $montant == 0.0) {
            $m = round($montant, 2);

            return ['base' => $m, 'tax' => 0.0, 'total' => $m];
        }

        if ($comprise) {
            // Le prix affiché EST ce que le client paie : on en extrait la base.
            $total = round($montant, 2);
            $base  = round($total / (1 + $taux / 100), 2);

            // La taxe est le reste, jamais un second arrondi : c'est ce qui
            // garantit que base + taxe = total, au centime près.
            return ['base' => $base, 'tax' => round($total - $base, 2), 'total' => $total];
        }

        $base  = round($montant, 2);
        $total = round($base * (1 + $taux / 100), 2);

        return ['base' => $base, 'tax' => round($total - $base, 2), 'total' => $total];
    }

    /**
     * Récapitulatif d'un panier, par taux. PUR.
     *
     * Le détail PAR TAUX n'est pas un luxe : dès qu'un commerce vend des
     * articles exonérés à côté d'articles taxés, c'est ce que la déclaration
     * demande, et un total unique ne permet plus de le reconstituer.
     *
     * La remise réduit les bases taxables au PRORATA. L'imputer entièrement sur
     * une ligne changerait la taxe due selon l'ordre des articles, ce qui n'a
     * aucun sens — et ferait payer au commerce une taxe qu'il n'a pas encaissée.
     *
     * @param  array<int, array{amount:float, rate:?float}>  $lignes
     * @return array{base:float, tax:float, total:float, byRate:array<string, array{rate:float, base:float, tax:float}>}
     */
    public static function summarize(array $lignes, bool $comprise, float $remise = 0.0): array
    {
        $brut = 0.0;
        foreach ($lignes as $l) {
            $brut += (float) ($l['amount'] ?? 0);
        }

        $remise = max(0.0, min($remise, $brut));
        // Part de chaque ligne qui subsiste après remise.
        $facteur = $brut > 0 ? ($brut - $remise) / $brut : 0.0;

        $base = 0.0;
        $taxe = 0.0;
        $total = 0.0;
        $parTaux = [];

        foreach ($lignes as $l) {
            $montant = round((float) ($l['amount'] ?? 0) * $facteur, 2);
            $taux = self::rate($l['rate'] ?? null) ?? 0.0;

            $part = self::split($montant, $taux, $comprise);

            $base  += $part['base'];
            $taxe  += $part['tax'];
            $total += $part['total'];

            // Clé stable : « 10 » et « 10.0 » doivent se ranger ensemble,
            // sinon la déclaration compterait deux fois le même taux.
            //
            // PHP retransforme une clé numérique en entier (10), tandis qu'un
            // taux fractionnaire reste une chaîne ('5.5'). Les lectures par
            // clé fonctionnent dans les deux cas — PHP normalise aussi à la
            // lecture — mais array_keys() rend donc un mélange : comparer les
            // clés en s'attendant à des chaînes échouerait.
            $cle = rtrim(rtrim(number_format($taux, 3, '.', ''), '0'), '.');
            if (! isset($parTaux[$cle])) {
                $parTaux[$cle] = ['rate' => $taux, 'base' => 0.0, 'tax' => 0.0];
            }
            $parTaux[$cle]['base'] = round($parTaux[$cle]['base'] + $part['base'], 2);
            $parTaux[$cle]['tax']  = round($parTaux[$cle]['tax'] + $part['tax'], 2);
        }

        krsort($parTaux, SORT_NUMERIC); // le taux le plus élevé en premier

        return [
            'base'   => round($base, 2),
            'tax'    => round($taxe, 2),
            'total'  => round($total, 2),
            'byRate' => $parTaux,
        ];
    }

    /** « TCA 10 % » — ce qui s'imprime sur le reçu. PUR. */
    public static function label(?string $nom, ?float $taux): string
    {
        $nom = trim((string) ($nom ?: 'Taxe'));
        $taux = self::rate($taux);

        if ($taux === null) {
            return $nom;
        }

        return $nom.' '.rtrim(rtrim(number_format($taux, 2, '.', ''), '0'), '.').' %';
    }
}
