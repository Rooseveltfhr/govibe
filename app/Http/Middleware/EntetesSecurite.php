<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité posés sur chaque réponse.
 *
 * Séparer le back-office du site vitrine ne sert à rien si les deux répondent
 * avec les mêmes protections, c'est-à-dire aucune. Ces en-têtes coûtent une
 * ligne et ferment des attaques réelles.
 */
class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);

        // Un fichier téléversé qui se ferait passer pour du HTML ne sera pas
        // interprété comme tel par le navigateur.
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');

        // Le référent complet n'est pas transmis aux sites tiers : une URL de
        // confirmation porte parfois une référence de commande.
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Le back-office ne s'affiche dans aucun cadre. Une page piégée qui
        // superposerait un bouton invisible sur « Approuver le paiement » ferait
        // valider un règlement d'un simple clic.
        $reponse->headers->set(
            'X-Frame-Options',
            $request->is('erp', 'erp/*', 'portail', 'portail/*') ? 'DENY' : 'SAMEORIGIN'
        );

        $this->hsts($request, $reponse);

        return $reponse;
    }

    /**
     * HSTS oblige le navigateur à n'utiliser que HTTPS pour ce domaine, et
     * cette consigne lui reste en mémoire pendant toute la durée annoncée.
     *
     * D'où le réglage explicite, désactivé par défaut : posé trop tôt, ou avec
     * une durée trop longue, il rend un domaine inaccessible en clair pour des
     * mois — y compris pendant un incident de certificat. « includeSubDomains »
     * n'est pas utilisé : il s'appliquerait aussi aux sous-domaines servis pour
     * des tiers, qui n'ont pas forcément HTTPS.
     */
    private function hsts(Request $request, Response $reponse): void
    {
        $jours = (int) config('govibe.hsts_jours', 0);

        if ($jours < 1 || ! $request->secure()) {
            return;
        }

        $reponse->headers->set('Strict-Transport-Security', 'max-age='.($jours * 86400));
    }
}
