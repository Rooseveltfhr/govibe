<?php

namespace Modules\Tagtoa\App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Modules\Tagtoa\App\Models\Menu\Menu;

/**
 * TAGTOA MENU — page publique (NFC / QR), pas d'auth.
 */
class PublicController extends Controller
{
    public function show(string $alias): View
    {
        $menu = Menu::where('alias', $alias)->where('is_active', true)
            ->with(['payPage', 'activeCategories.availableItems'])
            ->firstOrFail();

        $menu->incrementQuietly('views');

        // Catégories non vides uniquement.
        $categories = $menu->activeCategories->filter(fn ($c) => $c->availableItems->isNotEmpty())->values();

        return view('tagtoa::menu.show', ['menu' => $menu, 'categories' => $categories]);
    }
}
