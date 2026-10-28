<?php

namespace Modules\Tagtoa\App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * TAGTOA — vide OPcache depuis un VRAI worker PHP-FPM, appelée par
 * deploy/remote-deploy.sh juste après `php artisan up`.
 *
 * Pourquoi cette route existe : un rsync + une migration réussis ne
 * garantissent PAS qu'un worker PHP-FPM déjà démarré voit le nouveau code —
 * si OPcache est configuré avec validate_timestamps=0 (courant en
 * production, pour la performance), chaque worker continue de servir le
 * bytecode compilé AVANT le déploiement jusqu'à son propre redémarrage.
 * `php artisan` (CLI) a son propre OPcache, séparé de celui des workers
 * FPM : l'appeler en CLI ne vide JAMAIS le cache que ces workers utilisent
 * réellement pour répondre aux requêtes web. Sans accès SSH pour redémarrer
 * php-fpm, le seul moyen de vider le cache d'un worker FPM est une requête
 * HTTP qui ATTERRIT sur ce worker et appelle opcache_reset() depuis
 * l'intérieur de son propre processus.
 *
 * Désactivée par défaut (jeton vide = 404, échec fermé) : définir
 * TAGTOA_OPCACHE_RESET_TOKEN sur le VPS pour l'activer.
 */
class OpcacheController extends Controller
{
    public function reset(string $token): JsonResponse
    {
        $expected = (string) config('tagtoa.ops.opcache_reset_token', '');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $cleared = function_exists('opcache_reset') && opcache_reset();

        return response()->json(['ok' => true, 'opcache_cleared' => $cleared]);
    }
}
