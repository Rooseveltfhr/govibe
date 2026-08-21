<?php

namespace Modules\Tagtoa\App\Support\Pay;

/**
 * TAGTOA Pay — décrit les champs d'identifiants d'un driver À PARTIR de la
 * forme de sa configuration, plutôt qu'avec un formulaire codé en dur par
 * passerelle. Ajouter un driver dans config/config.php suffit donc à le voir
 * apparaître, correctement, dans le super-admin.
 *
 * LOGIQUE PURE : prend un tableau de config, ne touche ni Laravel ni la base.
 *
 * Convention lue dans la config :
 *   'credentials' => [...]  → les identifiants proprement dits
 *   'mode'                  → sélecteur sandbox / live
 *   toute autre clé scalaire (hors 'label') → secret complémentaire
 *                             (ex. webhook_secret, ipn_secret)
 */
class GatewayCredentialFields
{
    public const MODES = ['sandbox', 'live'];

    /** Clés jamais éditables : purement descriptives. */
    private const IGNORED = ['label', 'credentials', 'mode'];

    /**
     * @return array{credentials: string[], extras: string[], has_mode: bool}
     */
    public static function describe(array $driverConfig): array
    {
        $credentials = [];
        if (isset($driverConfig['credentials']) && is_array($driverConfig['credentials'])) {
            $credentials = array_keys($driverConfig['credentials']);
        }

        $extras = [];
        foreach ($driverConfig as $key => $value) {
            if (in_array($key, self::IGNORED, true) || is_array($value)) {
                continue;
            }
            $extras[] = $key;
        }

        return [
            'credentials' => $credentials,
            'extras'      => $extras,
            'has_mode'    => array_key_exists('mode', $driverConfig),
        ];
    }

    /** Libellé lisible pour un nom de champ technique. */
    public static function label(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * PUR — fusionne les identifiants stockés en base PAR-DESSUS la config.
     *
     * La base gagne quand une valeur y est réellement renseignée : c'est ce que
     * le fondateur vient de saisir dans l'interface. Une valeur vide en base
     * n'écrase jamais le .env (sinon enregistrer le formulaire effacerait
     * silencieusement des identifiants déjà en place côté serveur).
     */
    public static function merge(array $driverConfig, ?array $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return $driverConfig;
        }

        $out = $driverConfig;

        if (isset($stored['credentials']) && is_array($stored['credentials'])) {
            foreach ($stored['credentials'] as $key => $value) {
                if (self::filled($value)) {
                    $out['credentials'][$key] = $value;
                }
            }
        }

        foreach ($stored as $key => $value) {
            if ($key === 'credentials' || is_array($value)) {
                continue;
            }
            if (self::filled($value)) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    /**
     * PUR — quelles valeurs conserver après soumission du formulaire.
     *
     * Un champ laissé vide signifie « ne change rien » (on ne réaffiche jamais
     * un secret, donc l'utilisateur ne peut pas le retaper à chaque fois).
     * Pour retirer un identifiant il faut demander explicitement l'effacement.
     */
    public static function applySubmission(array $existing, array $submitted, bool $clear = false): array
    {
        if ($clear) {
            return [];
        }

        $out = $existing;

        foreach ($submitted as $key => $value) {
            if ($key === 'credentials') {
                continue;
            }
            if (self::filled($value)) {
                $out[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        if (isset($submitted['credentials']) && is_array($submitted['credentials'])) {
            foreach ($submitted['credentials'] as $key => $value) {
                if (self::filled($value)) {
                    $out['credentials'][$key] = is_string($value) ? trim($value) : $value;
                }
            }
        }

        return $out;
    }

    /**
     * PUR — état d'affichage d'un champ : rempli ou non, et depuis quelle
     * source. On ne renvoie JAMAIS la valeur elle-même.
     *
     * @return array<string, string> champ => 'db' | 'env' | 'empty'
     */
    public static function sources(array $driverConfig, ?array $stored): array
    {
        $out = [];
        $storedCreds = (is_array($stored) && isset($stored['credentials']) && is_array($stored['credentials']))
            ? $stored['credentials'] : [];

        foreach (($driverConfig['credentials'] ?? []) as $key => $envValue) {
            $out['credentials.'.$key] = self::sourceOf($storedCreds[$key] ?? null, $envValue);
        }

        foreach ($driverConfig as $key => $envValue) {
            if (in_array($key, self::IGNORED, true) || is_array($envValue)) {
                continue;
            }
            $out[$key] = self::sourceOf(is_array($stored) ? ($stored[$key] ?? null) : null, $envValue);
        }

        return $out;
    }

    private static function sourceOf($dbValue, $envValue): string
    {
        if (self::filled($dbValue)) {
            return 'db';
        }

        return self::filled($envValue) ? 'env' : 'empty';
    }

    private static function filled($value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
