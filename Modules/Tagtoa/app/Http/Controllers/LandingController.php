<?php

namespace Modules\Tagtoa\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * TAGTOA — page d'accueil publique (landing) servie à la racine.
 * Vitrine du produit : modules, démos, connexion. Multilingue.
 */
class LandingController extends Controller
{
    public function index(): View
    {
        return view('tagtoa::landing');
    }
}
