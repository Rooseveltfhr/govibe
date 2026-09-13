<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sépare le back-office du site vitrine : l'ERP et le portail répondent sur
 * app.govibeht.com, les pages publiques sur govibeht.com.
 *
 * Ce que la séparation apporte réellement : le cookie de session du back-office
 * cesse d'être envoyé au site public (SESSION_DOMAIN doit rester vide pour que
 * les cookies soient liés à l'hôte), et l'hôte applicatif peut recevoir des
 * règles que le site public ne supporterait pas — filtrage d'adresses, limites
 * plus sévères, en-têtes plus stricts.
 *
 * Ce qu'elle n'apporte pas : c'est la même application, le même code et la même
 * base. Une faille reste atteignable des deux côtés. Ce qui protège l'ERP est
 * l'authentification, pas l'adresse.
 *
 * Le contrôle ne s'active que lorsque GOVIBE_DOMAINE_APP est renseigné. Tant
 * qu'il est vide, tout répond comme avant : une isolation posée avant que le
 * sous-domaine ne résolve enfermerait l'équipe dehors de son propre ERP, sans
 * moyen d'y revenir pour la lever.
 */
class DomaineApplication
{
    /** Ce qui n'existe que sur le domaine applicatif. */
    private const CHEMINS_APP = ['erp', 'erp/*', 'portail', 'portail/*'];

    /**
     * Ce qui doit répondre sur les deux hôtes, sans jamais être redirigé.
     *
     * « up » est la sonde de disponibilité : la rediriger ferait croire à une
     * panne selon l'hôte surveillé. Les retours de passerelle sont joints par
     * des serveurs tiers qui ne suivent pas toujours une redirection — et un
     * verdict de paiement perdu est un paiement encaissé que personne ne
     * constate.
     */
    private const CHEMINS_PARTAGES = ['up', 'paiement/retour/*', 'paiement/notification/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $app = config('govibe.domaine_app');

        if (! $app || $this->estHoteDeTravail($request)) {
            return $next($request);
        }

        if ($request->is(...self::CHEMINS_PARTAGES)) {
            return $next($request);
        }

        $surLApp = $this->memeHote($request->getHost(), $app);
        $reserveALApp = $request->is(...self::CHEMINS_APP);

        // Back-office demandé sur le site vitrine : un lien en circulation, un
        // favori, un signet. Il doit continuer de mener quelque part.
        if ($reserveALApp && ! $surLApp) {
            return $this->rediriger($request, $app);
        }

        // Page publique demandée sur le domaine applicatif : sans cette règle,
        // le site entier répondrait à deux adresses, et les moteurs de
        // recherche verraient deux fois le même contenu.
        if (! $reserveALApp && $surLApp) {
            $vitrine = config('govibe.domaine_vitrine');

            // Uniquement en GET. Une requête qui porte un corps — formulaire,
            // notification de passerelle — ne se redirige pas sans risque :
            // mieux vaut la servir que la perdre.
            if ($vitrine && $request->isMethodSafe()) {
                return $this->rediriger($request, $vitrine);
            }
        }

        return $next($request);
    }

    /**
     * Redirige en conservant le chemin et la chaîne de requête.
     *
     * 302 et non 301 : une redirection permanente reste dans le cache du
     * navigateur longtemps après qu'on l'ait corrigée. Une erreur de domaine
     * deviendrait alors impossible à rattraper pour les visiteurs concernés.
     *
     * 308 pour les méthodes qui portent un corps : un 301 ou un 302 sur un POST
     * le transforme en GET et jette la saisie. L'utilisateur voit son
     * formulaire échouer sans comprendre pourquoi.
     */
    private function rediriger(Request $request, string $hote): Response
    {
        $code = $request->isMethodSafe() ? 302 : 308;

        return redirect()->away('https://'.$hote.$request->getRequestUri(), $code);
    }

    private function memeHote(string $hote, string $domaine): bool
    {
        $hote = strtolower($hote);
        $domaine = strtolower($domaine);

        return $hote === $domaine || $hote === 'www.'.$domaine;
    }

    /**
     * Le développement local garde son accès : sinon plus personne ne peut
     * travailler sur la machine où le sous-domaine n'existe pas.
     */
    private function estHoteDeTravail(Request $request): bool
    {
        $hote = strtolower($request->getHost());

        return in_array($hote, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($hote, '.test')
            || str_ends_with($hote, '.localhost');
    }
}
