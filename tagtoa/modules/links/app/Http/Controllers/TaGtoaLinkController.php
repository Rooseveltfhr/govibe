<?php

namespace App\Http\Controllers;

use App\Models\TaGtoaLink;
use App\Models\TaGtoaLinkPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * TAGTOA LINKS — pages publiques (Linktree-style).
 *
 * Routes :
 *   GET /links/{alias}        -> show
 *   GET /links/go/{link}      -> go  (redirection + comptage de clics)
 */
class TaGtoaLinkController extends Controller
{
    public function show(string $alias): View
    {
        $page = TaGtoaLinkPage::where('alias', $alias)
            ->where('is_active', true)
            ->with(['activeLinks', 'payPage', 'media'])
            ->firstOrFail();

        $page->incrementQuietly('views');

        return view('tagtoa.links.show', [
            'page'  => $page,
            'links' => $page->activeLinks,
        ]);
    }

    /** Redirige vers l'URL du lien en comptant le clic. */
    public function go(int $link): RedirectResponse
    {
        $link = TaGtoaLink::where('is_active', true)->findOrFail($link);
        $link->incrementQuietly('clicks');

        return redirect()->away($link->url);
    }
}
