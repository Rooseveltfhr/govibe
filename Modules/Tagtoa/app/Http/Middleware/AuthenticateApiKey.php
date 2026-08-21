<?php

namespace Modules\Tagtoa\App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Tagtoa\App\Models\Api\ApiKey;
use Modules\Tagtoa\App\Support\Api\ApiToken;

/**
 * TAGTOA API — authentification par clé développeur.
 *
 * Contexte API : PAS de session, donc on ne peut pas utiliser Tenant::id() (qui
 * dépend de l'utilisateur connecté). Le tenant est déterminé UNIQUEMENT par la
 * clé présentée, puis exposé aux contrôleurs via les attributs de la requête.
 *
 * Réponses volontairement identiques (401, même message) que la clé soit
 * inconnue, mal formée ou révoquée : on n'aide pas un attaquant à distinguer
 * « cette clé a existé » de « cette clé n'existe pas ».
 */
class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $token = ApiToken::fromHeader($request->header('Authorization'))
            ?: $request->header('X-Tagtoa-Key');

        $prefix = ApiToken::prefixOf($token);
        if ($prefix === null) {
            return $this->unauthorized();
        }

        $key = ApiKey::where('key_prefix', $prefix)->first();
        if (! $key || ! $key->isActive() || ! ApiToken::matches($token, $key->key_hash)) {
            return $this->unauthorized();
        }

        // Trace d'usage — tolérante : ne doit jamais faire échouer un appel.
        try {
            $key->forceFill(['last_used_at' => now()])->saveQuietly();
        } catch (\Throwable $e) {
            // ignore
        }

        $request->attributes->set('tagtoa_api_key', $key);
        $request->attributes->set('tagtoa_tenant_id', $key->tenant_id);

        return $next($request);
    }

    protected function unauthorized(): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'error'   => 'unauthorized',
            'message' => 'Clé API invalide, révoquée ou absente.',
        ], 401);
    }
}
