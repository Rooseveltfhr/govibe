<?php

namespace Modules\Tagtoa\App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Site\Site;

/**
 * TAGTOA SITE — site web public (vitrine), pas d'auth.
 */
class PublicController extends Controller
{
    /** Cache des DONNÉES seulement (jamais le HTML rendu — voir Menu\PublicController). */
    private const PUBLIC_CACHE_TTL = 20;

    public function show(string $alias): View
    {
        $site = Cache::remember("tagtoa:site:show:$alias", self::PUBLIC_CACHE_TTL, function () use ($alias) {
            $site = Site::where('alias', $alias)->where('is_published', true)
                ->with(['menu', 'payPage', 'linkPage'])
                ->firstOrFail();

            $site->incrementQuietly('views');

            return $site;
        });

        return view('tagtoa::site.show', ['site' => $site]);
    }
}
