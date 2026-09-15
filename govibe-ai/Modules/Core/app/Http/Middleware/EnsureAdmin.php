<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panèl la mande yon kont administratè.
 *
 * De kondisyon, pa youn: konekte EPI `is_admin`. Yon kont ki konekte men ki
 * pa administratè pa dwe wè kòmand kliyan yo — non, nimewo WhatsApp, peman.
 * Nou voye l sou konekte a olye nou bay yon 403 ki konfime paj la egziste.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_admin !== true) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
