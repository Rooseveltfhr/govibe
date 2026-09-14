<?php

namespace Modules\Tagtoa\App\Support\Stand;

/**
 * TAGTOA SMART STAND — ce qui est imprimé SOUS le panneau à gratter.
 *
 * ── Pourquoi cette classe existe ──────────────────────────────────────────
 *
 * Un restaurant de quarante tables achète quarante stands. Avec le parcours de
 * M3, il doit gratter, lire huit caractères, les taper, valider — quarante fois.
 * Plus de deux heures. Personne ne le fait : les stands restent dans le carton,
 * et le marchand s'en va.
 *
 * La réponse n'est PAS d'affaiblir la preuve de possession. Le QR du recto est
 * photographiable — c'est acquis, et c'est pour ça que le secret existe. Si
 * l'activation se contentait du recto, quiconque passe devant un restaurant
 * pourrait réclamer ses stands.
 *
 * La réponse est de rendre le SECRET lui-même lisible par la caméra. Sous le
 * panneau à gratter, on imprime deux fois la même chose :
 *
 *   • les huit caractères lisibles — qui restent la référence ;
 *   • un QR contenant « TG-000041-A3F9K2MP » — identité ET autorité en un seul
 *     module, si bien qu'une seule visée suffit.
 *
 * Gratter puis viser prend cinq secondes. Quarante tables tiennent dans quatre
 * minutes, et pas un bit de sécurité n'est perdu : ce QR-là est sous le panneau,
 * il n'est visible que de celui qui tient l'objet.
 *
 * ── Deux contraintes qui expliquent la forme ──────────────────────────────
 *
 * 1. Le séparateur est un TIRET, pas deux-points. Le composant caméra de TAGTOA
 *    ne retient que [A-Z0-9-] : un deux-points serait avalé en silence, et la
 *    charge utile arriverait collée. Le tiret traverse la chaîne intact.
 *
 * 2. La lecture reste NON AMBIGUË malgré le tiret déjà présent dans
 *    « TG-000041 » : l'identifiant a une forme fixe (lettres, tiret, six
 *    chiffres), donc on le prend en tête et tout le reste est le secret.
 *
 * ⚠️ Ce qui est décidé ici part chez l'imprimeur. Un objet en circulation ne se
 * rappelle pas : toute évolution devra cohabiter avec ce format.
 *
 * ⚠️ La charge utile CONTIENT LE SECRET. Elle ne doit jamais être journalisée,
 * ni apparaître dans un message d'exception, ni transiter par une URL — une URL
 * finit dans un historique de navigateur, un en-tête Referer et un journal
 * d'accès. Elle voyage en corps de requête, et nulle part ailleurs.
 *
 * Classe PURE : aucune dépendance Laravel, testable sans base de données.
 */
class StandScratch
{
    /** Ce qui sépare l'identité de l'autorité dans la charge utile. */
    public const SEPARATOR = '-';

    /**
     * Ce que contient le QR sous le panneau à gratter. PUR.
     *
     * Le secret y est écrit SANS le groupement d'impression : le groupement
     * aide l'œil humain, la caméra n'en a pas besoin, et chaque caractère de
     * moins agrandit les modules du QR — donc sa lisibilité sur un panneau
     * gratté à l'ongle, forcément abîmé.
     */
    public static function payload(string $publicId, string $secret): string
    {
        return StandId::normalizeId($publicId).self::SEPARATOR.StandId::normalizeSecret($secret);
    }

    /**
     * Sépare une lecture en identifiant et secret. PUR.
     *
     * Accepte tout ce qu'un marchand peut réellement produire :
     *   • la lecture caméra              « TG-000041-A3F9K2MP »
     *   • recopiée à la main             « tg 000041 a3f9-k2mp »
     *   • avec le groupement d'impression « TG-000041-A3F9-K2MP »
     *   • l'identifiant seul             → secret null, pas une erreur
     *
     * Renvoie toujours un couple ; à l'appelant de décider ce qui manque.
     *
     * @return array{0:?string,1:?string} [identifiant, secret]
     */
    public static function parse(?string $lecture): array
    {
        $brut = strtoupper(trim((string) $lecture));

        // Une URL complète peut arriver : certains lecteurs de QR rendent
        // l'adresse entière quand la personne a visé le recto par mégarde.
        // On garde ce qui suit le dernier « / » plutôt que d'échouer.
        if (str_contains($brut, '/')) {
            $brut = (string) substr(strrchr($brut, '/') ?: '', 1);
        }

        // Tout sauf l'alphabet imprimable et les séparateurs usuels.
        $brut = preg_replace('/[^A-Z0-9 \-_.:]/', '', $brut) ?? '';

        // L'identifiant a une forme fixe : on le prend en tête, le reste suit.
        if (! preg_match('/^\s*([A-Z]{2,8})[\s\-_.:]*(\d{'.StandId::DIGITS.'})(.*)$/', $brut, $m)) {
            return [null, null];
        }

        $id    = StandId::normalizeId($m[1].'-'.$m[2]);
        $reste = StandId::normalizeSecret($m[3]);

        if (! StandId::isValidId($id)) {
            return [null, null];
        }

        return [$id, $reste === '' ? null : $reste];
    }

    /**
     * Cette lecture porte-t-elle une identité ET une autorité complètes ? PUR.
     *
     * Sert à trancher côté écran entre « continue à scanner » et « il manque le
     * code, demande-le » — sans envoyer au serveur une lecture qu'on sait
     * incomplète.
     */
    public static function isComplete(?string $lecture): bool
    {
        [$id, $secret] = self::parse($lecture);

        return $id !== null && StandId::isValidSecret($secret);
    }

    /**
     * Ce qu'on a le droit d'écrire dans un journal. PUR.
     *
     * L'identifiant est public par conception — il est imprimé au recto. Le
     * secret ne l'est pas : il est remplacé, jamais tronqué. Un secret tronqué
     * reste une fuite, il divise seulement l'espace de recherche.
     */
    public static function safeTrace(?string $lecture): string
    {
        [$id, $secret] = self::parse($lecture);

        if ($id === null) {
            return 'illisible';
        }

        return $id.($secret === null ? '' : self::SEPARATOR.'********');
    }
}
