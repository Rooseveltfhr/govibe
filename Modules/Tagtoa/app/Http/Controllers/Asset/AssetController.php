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
        'html5-qrcode.min.js' => 'application/javascript; charset=utf-8',
    ];

    public function vendor(string $file): Response|BinaryFileResponse
    {
        $mime = self::ASSETS[$file] ?? null;
        if ($mime === null) {
            abort(404);
        }

        $path = __DIR__.'/../../../../resources/assets/vendor/'.$file;
        if (! is_file($path)) {
            abort(404);
        }

        // Immuable : le nom de fichier porte la version, on peut cacher 1 an.
        return response()->file($path, [
            'Content-Type'  => $mime,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
