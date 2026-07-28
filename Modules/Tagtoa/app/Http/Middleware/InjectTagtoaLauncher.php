<?php

namespace Modules\Tagtoa\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * TAGTOA — injecte un bouton flottant « TAGTOA » dans les pages d'admin Biztap
 * (/sadmin…) pour offrir un accès direct au hub TAGTOA (/tagtoa/home).
 *
 * Non invasif : n'édite AUCUNE vue du cœur Biztap (qui n'est pas dans le dépôt).
 * On post-traite la réponse HTML et on insère un lien avant `</body>`. Tolérant :
 * toute condition non remplie → la réponse passe intacte.
 */
class InjectTagtoaLauncher
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if (! $this->shouldInject($request, $response)) {
                return $response;
            }

            $html = $response->getContent();
            if (! is_string($html) || stripos($html, '</body>') === false) {
                return $response;
            }
            if (str_contains($html, 'tagtoa-launcher')) {
                return $response; // déjà injecté
            }

            $response->setContent(str_ireplace('</body>', $this->button().'</body>', $html));
        } catch (\Throwable $e) {
            // jamais casser une page d'admin pour un bouton
        }

        return $response;
    }

    /** N'injecte que sur les pages HTML d'admin Biztap, pour un utilisateur connecté. */
    protected function shouldInject(Request $request, Response $response): bool
    {
        if ($request->ajax() || $request->wantsJson()) {
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }
        if (! ($request->is('sadmin') || $request->is('sadmin/*') || $request->is('admin') || $request->is('admin/*'))) {
            return false;
        }

        return auth()->check();
    }

    /** Le bouton flottant (styles inline pour ne dépendre d'aucun CSS du cœur). */
    protected function button(): string
    {
        $url = e(url('/tagtoa/home'));
        $label = e(__('Aller sur TAGTOA'));

        return <<<HTML
<a id="tagtoa-launcher" href="{$url}" title="{$label}"
   style="position:fixed;right:18px;bottom:18px;z-index:2147483000;display:inline-flex;align-items:center;gap:8px;
          background:#2cb809;color:#fff;font:600 14px/1 system-ui,-apple-system,Segoe UI,Roboto,sans-serif;
          text-decoration:none;padding:12px 16px;border-radius:999px;box-shadow:0 6px 20px rgba(0,0,0,.22)">
   <span style="font-size:16px">&#8734;</span><span>TAGTOA</span>
</a>
HTML;
    }
}
