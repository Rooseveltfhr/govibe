<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DomaineApplication
{
    /**
     * Isole l'ERP et le portail sur app.govibeht.com.
     *
     * Le contrôle ne s'active que lorsque GOVIBE_DOMAINE_APP est renseigné.
     * Tant qu'il est vide, tout répond comme aujourd'hui : une isolation posée
     * avant que le sous-domaine ne résolve enfermerait l'équipe dehors de son
     * propre ERP, sans moyen d'y revenir pour la lever.
     *
     * Le trafic mal aiguillé est redirigé, pas refusé : un lien en circulation
     * doit continuer de mener quelque part.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domaine = config('govibe.domaine_app');

        if (! $domaine || $this->hoteAutorise($request, $domaine)) {
            return $next($request);
        }

        return redirect()->away(
            'https://'.$domaine.'/'.ltrim($request->getRequestUri(), '/'),
            301
        );
    }

    private function hoteAutorise(Request $request, string $domaine): bool
    {
        $hote = strtolower($request->getHost());

        // Le domaine nu et son www sont acceptés ; le développement local
        // conserve son accès, sinon plus personne ne peut travailler.
        return $hote === strtolower($domaine)
            || $hote === 'www.'.strtolower($domaine)
            || in_array($hote, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($hote, '.test');
    }
}
