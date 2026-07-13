<?php

namespace Modules\Tagtoa\App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * TAGTOA — page d'accueil publique (landing) servie à la racine.
 * Vitrine du produit : modules, démos, connexion. Multilingue.
 *
 * Un utilisateur DÉJÀ connecté est envoyé directement au hub TAGTOA :
 * une seule expérience d'admin pour tout le SaaS (Biztap + TAGTOA).
 */
class LandingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect('/tagtoa/home');
        }

        return view('tagtoa::landing');
    }
}
