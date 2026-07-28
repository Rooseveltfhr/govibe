<?php

namespace Modules\Tagtoa\App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Site\Site;

/**
 * TAGTOA SITE — site web public (vitrine), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $site = Site::where('alias', $alias)->where('is_published', true)
            ->with(['menu', 'payPage', 'linkPage'])
            ->firstOrFail();

        $site->incrementQuietly('views');

        return view('tagtoa::site.show', ['site' => $site]);
    }
}
