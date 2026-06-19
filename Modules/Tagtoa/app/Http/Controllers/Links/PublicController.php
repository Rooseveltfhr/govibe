<?php

namespace Modules\Tagtoa\App\Http\Controllers\Links;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Links\Link;
use Modules\Tagtoa\App\Models\Links\LinkPage;

/**
 * TAGTOA Links — page publique + redirection avec comptage de clics.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $page = LinkPage::where('alias', $alias)->where('is_active', true)
            ->with(['activeLinks', 'payPage'])->firstOrFail();

        $page->incrementQuietly('views');

        return view('tagtoa::links.show', ['page' => $page, 'links' => $page->activeLinks]);
    }

    public function go(int $link): RedirectResponse
    {
        $link = Link::where('is_active', true)->findOrFail($link);
        $link->incrementQuietly('clicks');

        return redirect()->away($link->url);
    }
}
