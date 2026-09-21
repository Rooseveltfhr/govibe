<?php

namespace Modules\Tagtoa\App\Support\Loyalty;

/**
 * TAGTOA LOYALTY — dans quel groupe ranger un porteur de carte.
 *
 * Le marchand qui ouvre sa liste de cartes se pose toujours la même question,
 * sous des formes différentes : « qui dois-je relancer, et qui dois-je
 * remercier ? ». Sans segments, il la lit ligne par ligne sur cinq cents
 * cartes ; avec eux, il filtre en un geste.
 *
 * ── L'ORDRE DES RÈGLES EST LA RÈGLE ──────────────────────────────────────
 *
 * Une carte peut cocher plusieurs cases à la fois — VIP par ses points ET
 * inactive depuis trois mois, c'est le client qui achetait beaucoup et qui a
 * disparu, le plus important à rappeler. L'ordre ci-dessous n'est donc pas
 * arbitraire : INACTIF passe AVANT VIP, parce que « ce client fidèle s'est
 * arrêté » est l'alerte qui compte le plus pour un commerce, et la cacher
 * derrière une étiquette flatteuse serait le contraire du but d'un segment.
 *
 * Classe PURE : aucune dépendance Laravel, aucune requête. Elle reçoit des
 * FAITS déjà calculés (points, ancienneté, dernier mouvement) et rend un mot.
 * C'est ce qui la rend testable sans base de données, et c'est aussi ce qui
 * empêche un jour une requête coûteuse de se glisser dans une simple
 * comparaison de nombres.
 */
class CustomerSegment
{
    public const NOUVEAU = 'nouveau';
    public const INACTIF = 'inactif';
    public const VIP     = 'vip';
    public const ACTIF   = 'actif';

    public const LABELS = [
        self::NOUVEAU => 'Nouveau',
        self::INACTIF => 'Inactif',
        self::VIP      => 'VIP',
        self::ACTIF    => 'Actif',
    ];

    /** Au-delà, un client a prouvé sa fidélité — pas au premier point gagné. */
    public const SEUIL_VIP_POINTS = 500;

    /** En-deçà, la carte vient d'être émise : encore rien à en dire. */
    public const JOURS_NOUVEAU = 14;

    /** Au-delà, un client qu'on ne voit plus est un client qu'on a perdu. */
    public const JOURS_INACTIF = 90;

    /**
     * Classe une carte selon ses faits.
     *
     * @param  array{points:int, issued_days_ago:int, last_transaction_days_ago:?int}  $faits
     *         `last_transaction_days_ago` est null si la carte n'a JAMAIS
     *         connu le moindre mouvement — pas « il y a longtemps », JAMAIS.
     */
    public static function classify(array $faits): string
    {
        $points = (int) ($faits['points'] ?? 0);
        $emiseDepuis = (int) ($faits['issued_days_ago'] ?? 0);
        $dernierMouvement = $faits['last_transaction_days_ago'] ?? null;

        // 1. INACTIF d'abord — l'alerte qui compte le plus, quel que soit le
        //    reste. Une carte émise il y a un an et jamais utilisée n'est pas
        //    « nouvelle » : elle a eu tout le temps de servir, et n'a pas servi.
        if ($dernierMouvement === null) {
            if ($emiseDepuis > self::JOURS_NOUVEAU) {
                return self::INACTIF;
            }
        } elseif ($dernierMouvement > self::JOURS_INACTIF) {
            return self::INACTIF;
        }

        // 2. NOUVEAU — émise récemment, et pas encore assez de recul pour
        //    juger. Une carte émise hier avec déjà 600 points (un premier
        //    grand achat) reste NOUVELLE : un seul geste ne fait pas un VIP,
        //    et le marchand veut savoir qui vient d'arriver, pas le confondre
        //    avec un habitué de longue date.
        if ($emiseDepuis <= self::JOURS_NOUVEAU) {
            return self::NOUVEAU;
        }

        // 3. VIP — a prouvé sa fidélité dans la durée.
        if ($points >= self::SEUIL_VIP_POINTS) {
            return self::VIP;
        }

        // 4. Le reste : sert, sans se distinguer par un extrême.
        return self::ACTIF;
    }

    public static function label(string $segment): string
    {
        return self::LABELS[$segment] ?? $segment;
    }
}
