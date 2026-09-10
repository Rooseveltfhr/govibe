<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthentifieClient
{
    /**
     * Porte d'entrée du portail : un compte connecté, actif, dont l'adresse
     * est vérifiée.
     *
     * La vérification n'est pas un confort : sans elle, s'inscrire avec
     * l'adresse d'un tiers suffirait à voir ses factures et ses commandes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $compte = auth('client')->user();

        if (! $compte) {
            return redirect()->route('portail.connexion');
        }

        if (! $compte->actif) {
            auth('client')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('portail.connexion')
                ->withErrors(['email' => 'Ce compte est désactivé. Contactez GOVIBE.']);
        }

        if (! $compte->peutVoirSesDonnees()) {
            return redirect()->route('portail.verification.attente');
        }

        return $next($request);
    }
}
