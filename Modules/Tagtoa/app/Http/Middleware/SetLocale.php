<?php

namespace Modules\Tagtoa\App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Modules\Tagtoa\App\Support\Locale;
use Symfony\Component\HttpFoundation\Response;

/**
 * TAGTOA — sélection de la langue (Kreyòl / Français / English / Español).
 *
 * Ordre de résolution : ?lang= (persisté) → session → cookie → Accept-Language → défaut.
 * Ne touche que la locale de la requête TAGTOA (n'altère aucune donnée).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);
        app()->setLocale($locale);

        // Persiste le choix explicite (?lang=) pour les prochaines visites.
        if (Locale::isSupported($request->query('lang'))) {
            $request->session()->put('tagtoa_locale', $locale);
            Cookie::queue('tagtoa_locale', $locale, 60 * 24 * 365);
        }

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        $candidates = [
            $request->query('lang'),
            $request->session()->get('tagtoa_locale'),
            $request->cookie('tagtoa_locale'),
            $this->fromAcceptLanguage($request),
        ];

        foreach ($candidates as $c) {
            if (Locale::isSupported($c)) {
                return $c;
            }
        }

        return Locale::default();
    }

    /** Première langue supportée annoncée par le navigateur. */
    protected function fromAcceptLanguage(Request $request): ?string
    {
        $header = (string) $request->header('Accept-Language');
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim($part), 0, 2));
            if (Locale::isSupported($code)) {
                return $code;
            }
        }

        return null;
    }
}
