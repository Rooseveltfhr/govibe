<?php

namespace Modules\Tagtoa\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Tagtoa\App\Support\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * TAGTOA — le back-office marchand ne s'ouvre JAMAIS sans commerce (CS-11b).
 *
 * La portée automatique (BelongsToTenant) est volontairement ouverte quand
 * `Tenant::id()` est null : une page publique, un webhook ou une commande
 * console n'ont pas de commerce. Mais dans le tableau de bord, le commerce
 * EST censé être connu. Un compte connecté sans `tenant_id` y obtenait donc
 * une portée vide — et voyait les données de TOUS les commerces.
 *
 * Ici, à l'entrée du seul groupe authentifié du marchand : pas de commerce,
 * pas de tableau de bord (403). Les pages publiques, webhooks et l'espace
 * super-admin (cross-commerce par conception) ne sont PAS concernés.
 *
 * Constat de production avant ce correctif (CS-11a) : 0 compte sur 9 sans
 * tenant_id — personne n'est bloqué aujourd'hui ; la faille est fermée avant
 * qu'un tel compte n'apparaisse.
 */
class RequireTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = Tenant::id();
        if ($id === null || $id === '') {
            $message = 'Aucun commerce n\'est associé à ce compte.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 403)
                : response($message, 403);
        }

        return $next($request);
    }
}
