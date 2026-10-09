<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public const SUPPORTED = ['fr', 'en', 'ht'];

    public function handle(Request $request, Closure $next)
    {
        $locale = $request->query('lang');

        if ($locale && in_array($locale, self::SUPPORTED, true)) {
            $request->session()->put('locale', $locale);
        }

        $locale = $request->session()->get('locale');

        if (! $locale) {
            $header = (string) $request->header('Accept-Language');
            foreach (self::SUPPORTED as $candidate) {
                if (str_starts_with($header, $candidate)) {
                    $locale = $candidate;
                    break;
                }
            }
        }

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'fr');

        return $next($request);
    }
}
