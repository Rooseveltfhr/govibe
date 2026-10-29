<?php

namespace Modules\Tagtoa\App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;

/**
 * TAGTOA Links — page publique + redirection avec comptage de clics.
 */
class PublicController extends Controller
{
    /** Cache des DONNÉES seulement (jamais le HTML rendu — voir Menu\PublicController). */
    private const PUBLIC_CACHE_TTL = 20;

    public function show(string $alias): View
    {
        $page = Cache::remember("tagtoa:links:show:$alias", self::PUBLIC_CACHE_TTL, function () use ($alias) {
            $page = LinkPage::where('alias', $alias)->where('is_active', true)
                ->with(['activeLinks', 'payPage'])->firstOrFail();

            $page->incrementQuietly('views');

            return $page;
        });

        return view('tagtoa::links.show', ['page' => $page, 'links' => $page->activeLinks]);
    }

    /**
     * Redirection + comptage de clics.
     *
     * La page parente décide AUSSI. Sans elle dans la condition, désactiver
     * une page de liens ne désactivait que `/links/{alias}` : chacun de ses
     * liens restait joignable un par un, indéfiniment. Un marchand qui
     * retire sa page — parce qu'un lien a été compromis, ou qu'il a quitté
     * TAGTOA — croyait avoir tout fermé alors que rien ne l'était.
     */
    public function go(int $link): RedirectResponse
    {
        $link = Link::where('is_active', true)
            ->whereHas('page', fn ($q) => $q->where('is_active', true))
            ->findOrFail($link);
        $link->incrementQuietly('clicks');

        return redirect()->away($link->url);
    }
}
