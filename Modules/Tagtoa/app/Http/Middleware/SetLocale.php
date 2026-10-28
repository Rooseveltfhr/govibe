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
 * Ordre de résolution : ?lang= (persisté) → session → cookie → défaut.
 * Ne touche que la locale de la requête TAGTOA (n'altère aucune donnée).
 *
 * SANS Accept-Language. Signalé en testant comme un vrai marchand : un
 * commerçant dont le téléphone est en anglais, qui n'a JAMAIS choisi de
 * langue, se retrouvait avec un tableau de bord mélangé — la plupart des
 * textes TAGTOA sont codés en dur en français (utilisés directement comme
 * clés de traduction), seuls quelques-uns ont une entrée dans en.json.
 * L'en-tête du navigateur n'est qu'une supposition ; un marchand qui arrive
 * sans l'avoir choisie doit voir UNE langue cohérente (le français, celle
 * dans laquelle tout le reste du produit est écrit), pas un mélange. Le
 * sélecteur de langue (voir partials/lang.blade.php, présent sur le tableau
 * de bord ET sur le menu public) reste le moyen normal de changer — explicite,
 * jamais deviné.
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
        ];

        foreach ($candidates as $c) {
            if (Locale::isSupported($c)) {
                return $c;
            }
        }

        return Locale::default();
    }
}
