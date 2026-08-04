<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Support\LocaleNegotiator;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = (array) config('govibe.locales', []);

        $negotiator = new LocaleNegotiator(
            $supported,
            (string) config('govibe.default_locale', 'fr'),
        );

        $queryLang = $request->query('lang');
        $queryLang = is_string($queryLang) ? $queryLang : null;

        $session = $request->hasSession() ? $request->session()->get('locale') : null;
        $cookie = $request->cookie('locale');

        $locale = $negotiator->resolve(
            $queryLang,
            is_string($session) ? $session : null,
            is_string($cookie) ? $cookie : null,
            $request->header('Accept-Language'),
        );

        app()->setLocale($locale);

        // Mémorise un choix explicite (?lang=) pour les visites suivantes.
        if ($queryLang !== null && $negotiator->isSupported($queryLang)) {
            if ($request->hasSession()) {
                $request->session()->put('locale', $locale);
            }

            $response = $next($request);
            $response->headers->setCookie(cookie('locale', $locale, 60 * 24 * 365));

            return $response;
        }

        return $next($request);
    }
}
