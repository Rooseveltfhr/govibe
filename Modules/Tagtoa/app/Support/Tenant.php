<?php

namespace Modules\Tagtoa\App\Support;

use Modules\Tagtoa\App\Models\Business\Business;

/**
 * TAGTOA — quel COMMERCE est en train d'être utilisé.
 *
 * Un compte TAGTOA peut tenir plusieurs commerces : la même personne ouvre une
 * boulangerie, puis un bar. L'unité du système est donc le commerce, pas le
 * compte, et c'est son identifiant que renvoie `id()`.
 *
 * Conséquence voulue : les 28 modèles isolés par BelongsToTenant se retrouvent
 * cloisonnés PAR COMMERCE sans une seule ligne de code modifiée.
 *
 * Rien n'est à réécrire en base : le premier commerce d'un marchand existant
 * reçoit pour identifiant son ancien `tenant_id`, donc toutes ses données
 * continuent de correspondre telles quelles.
 *
 * `account()` reste le pont vers le compte (helpers Biztap quand ils existent),
 * et sert à savoir quels commerces cette personne a le droit d'ouvrir.
 */
class Tenant
{
    /** Nom de la portée automatique posée par BelongsToTenant. */
    public const SCOPE = 'tagtoa_tenant';

    /** Clé de session portant le commerce choisi. */
    public const SESSION_KEY = 'tagtoa_business';

    /**
     * Cache par REQUÊTE, INDEXÉ PAR COMPTE.
     *
     * `id()` est appelé par la portée automatique à chaque requête Eloquent :
     * sans cache, afficher trente produits interrogerait trente fois la table
     * des commerces.
     *
     * L'indexation par compte n'est pas un détail. Un cache global garderait la
     * valeur résolue AVANT la connexion — donc `null`, donc aucune isolation —
     * pour tout le reste de la requête. Changer de compte doit invalider le
     * cache tout seul, sans que personne ait à y penser.
     *
     * La clé '' représente « aucun compte » (page publique, webhook, console).
     *
     * @var array<string, string|null>
     */
    private static array $resolved = [];

    /**
     * Commerce courant. Dans l'ordre :
     *   1. celui que la personne a choisi, s'il lui appartient toujours ;
     *   2. sinon son premier commerce actif ;
     *   3. sinon le compte lui-même — un marchand qui n'a pas encore créé son
     *      commerce continue de travailler avec ses données existantes.
     */
    public static function id(): ?string
    {
        // account() ne touche pas la base : l'appeler à chaque fois est gratuit,
        // et c'est ce qui fait qu'un changement de compte se voit tout de suite.
        $cle = self::account() ?? '';

        if (array_key_exists($cle, self::$resolved)) {
            return self::$resolved[$cle];
        }

        return self::$resolved[$cle] = self::resolve();
    }

    private static function resolve(): ?string
    {
        $account = self::account();
        if ($account === null) {
            return null; // page publique, webhook, console
        }

        try {
            $choisi = self::sessionBusiness();
            if ($choisi !== null) {
                $ok = Business::where('account_id', $account)->where('is_active', true)
                    ->whereKey($choisi)->exists();
                if ($ok) {
                    return $choisi;
                }
                // Commerce fermé, cédé, ou session d'un autre compte : on ne
                // suit pas la session les yeux fermés.
                self::forgetSession();
            }

            $premier = Business::where('account_id', $account)->where('is_active', true)
                ->orderBy('created_at')->value('id');

            if ($premier !== null) {
                return (string) $premier;
            }
        } catch (\Throwable $e) {
            // Table absente (déploiement en cours, test sans migration) : on
            // retombe sur le compte plutôt que de tout bloquer.
        }

        return $account;
    }

    /** Le COMPTE connecté — le propriétaire, pas le commerce. */
    public static function account(): ?string
    {
        if (function_exists('getLogInTenantId')) {
            try {
                return getLogInTenantId();
            } catch (\Throwable $e) {
                // helper indisponible : on continue avec l'utilisateur
            }
        }

        return optional(auth()->user())->tenant_id;
    }

    /** Bascule vers un autre commerce du même compte. Refuse le reste. */
    public static function switchTo(string $businessId): bool
    {
        $account = self::account();
        if ($account === null) {
            return false;
        }

        $autorise = Business::where('account_id', $account)->where('is_active', true)
            ->whereKey($businessId)->exists();

        if (! $autorise) {
            return false;
        }

        session([self::SESSION_KEY => $businessId]);
        self::flush();

        return true;
    }

    /** Oublie le commerce choisi (retour au premier). */
    public static function forgetSession(): void
    {
        try {
            session()->forget(self::SESSION_KEY);
        } catch (\Throwable $e) {
            // hors requête HTTP : rien à oublier
        }
        self::flush();
    }

    /**
     * Vide le cache de requête. À appeler après toute écriture qui change le
     * commerce courant — et entre deux tests, sinon le premier contaminerait
     * les suivants.
     */
    public static function flush(): void
    {
        self::$resolved = [];
    }

    private static function sessionBusiness(): ?string
    {
        try {
            $v = session(self::SESSION_KEY);
        } catch (\Throwable $e) {
            return null; // hors requête HTTP
        }

        return is_string($v) && $v !== '' ? $v : null;
    }

    /** Utilisateur connecté. */
    public static function user()
    {
        if (function_exists('getLogInUser')) {
            try {
                return getLogInUser();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return auth()->user();
    }

    /** Devise du commerce courant, sinon celle par défaut. */
    public static function currency(): string
    {
        try {
            $devise = Business::whereKey(self::id())->value('currency');
            if ($devise) {
                return (string) $devise;
            }
        } catch (\Throwable $e) {
            // table absente : on retombe sur la configuration
        }

        return config('tagtoa.default_currency', 'HTG');
    }
}
