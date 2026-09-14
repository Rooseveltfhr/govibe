<?php

namespace Modules\Tagtoa\App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * TAGTOA — assets tiers AUTO-HÉBERGÉS (souverains).
 *
 * Sert des librairies vendored depuis notre propre origine plutôt qu'un CDN,
 * pour supprimer tout point de défaillance externe sur les fonctionnalités
 * critiques (ex. scan de billets à l'entrée d'un événement où le wifi du lieu
 * est faible/absent — un CDN injoignable = scanner cassé). Bonus : pas de fuite
 * vers un tiers, chargement plus rapide (même origine), cache immuable.
 *
 * Route PUBLIQUE (pas d'auth) : le terminal staff terrain et le scanner
 * organisateur en ont besoin sans passer par le middleware back-office.
 */
class AssetController extends Controller
{
    /**
     * Liste blanche fichier → type MIME. Empêche tout accès hors de ces fichiers
     * (pas de traversée de chemin possible : la clé est comparée à l'identique).
     */
    protected const ASSETS = [
        'html5-qrcode.min.js'      => 'application/javascript; charset=utf-8',
        // Le scanner TAGTOA lui-même : un seul composant pour la caisse, la
        // fiche article et le stock (voir resources/assets/tagtoa/).
        'tagtoa-scanner.js'        => 'application/javascript; charset=utf-8',
        // Font Awesome 6.5.1 auto-hébergé (CSS + webfonts woff2) — plus de CDN externe.
        'fontawesome-6.5.1.css'    => 'text/css; charset=utf-8',
        'fa-solid-900.woff2'       => 'font/woff2',
        'fa-brands-400.woff2'      => 'font/woff2',
        'fa-regular-400.woff2'     => 'font/woff2',
        'fa-v4compatibility.woff2' => 'font/woff2',
        // Polices Google Fonts auto-hébergées (Anton, Nunito, Space Grotesk) —
        // plus de dépendance à fonts.googleapis.com/fonts.gstatic.com (internet
        // faible en Haïti : un hôte tiers en moins à joindre par page).
        'tagtoa-fonts.css'         => 'text/css; charset=utf-8',
        'anton-400.woff2'          => 'font/woff2',
        'nunito-400-800.woff2'     => 'font/woff2',
        'spacegrotesk-500-700.woff2' => 'font/woff2',
    ];

    public function vendor(string $file): Response|BinaryFileResponse
    {
        $mime = self::ASSETS[$file] ?? null;
        if ($mime === null) {
            abort(404);
        }

        // Les nôtres vivent à part de ce qui est vendored : ce sont deux
        // cycles de vie différents (l'un se met à jour avec TAGTOA, l'autre
        // avec sa librairie d'origine).
        $base = __DIR__.'/../../../../resources/assets/';

        $notre = is_file($base.'tagtoa/'.$file);
        $path  = $notre ? $base.'tagtoa/'.$file : $base.'vendor/'.$file;

        if (! is_file($path)) {
            abort(404);
        }

        // Les fichiers vendored portent leur version dans leur nom : un an de
        // cache est sûr. Les nôtres n'en portent pas et évoluent avec le
        // module — les figer rendrait toute correction invisible jusqu'à ce
        // que le commerçant vide son navigateur, ce qu'il ne fera pas.
        $cache = $notre
            ? 'public, max-age=3600, must-revalidate'
            : 'public, max-age=31536000, immutable';

        return response()->file($path, [
            'Content-Type'  => $mime,
            'Cache-Control' => $cache,
        ]);
    }
}
