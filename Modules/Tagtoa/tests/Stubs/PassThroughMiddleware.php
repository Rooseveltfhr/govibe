<?php

namespace Modules\Tagtoa\Tests\Stubs;

use Closure;
use Illuminate\Http\Request;

/**
 * Remplace, le temps des tests, les middlewares du projet hôte
 * (`valid.user`, `role:…`, `multi_tenant`) qui ne sont pas dans ce dépôt.
 *
 * Il laisse simplement passer. Ce que ces middlewares garantissent en
 * production — authentification, rôle, commerce courant — est vérifié
 * autrement dans les tests : en ouvrant une vraie session et en s'appuyant sur
 * la portée automatique. L'objet ici est de pouvoir charger les vraies routes.
 */
class PassThroughMiddleware
{
    public function handle(Request $request, Closure $next, ...$parameters)
    {
        return $next($request);
    }
}
