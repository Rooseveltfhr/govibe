<?php

namespace Modules\Tagtoa\App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * TAGTOA — l'isolation entre commerces devient une propriété du modèle.
 *
 * Avant ce trait, chaque requête devait penser à écrire `where('tenant_id', …)`
 * dans 28 contrôleurs. Un seul oubli exposait les données d'un commerce à un
 * autre — et c'est arrivé : la page d'accueil affichait les compteurs de toute
 * la plateforme à chaque marchand.
 *
 * Désormais :
 *   • toute lecture est limitée au commerce courant, sans rien écrire ;
 *   • toute création reçoit son `tenant_id` automatiquement ;
 *   • sortir de cette limite demande un appel EXPLICITE à `allTenants()`,
 *     donc visible en relecture et retrouvable d'un `grep`.
 *
 * Contexte sans commerce courant (page publique visitée par un client, webhook
 * d'une passerelle, commande console, test) : la portée ne s'applique pas.
 * C'est voulu — une page publique doit être lisible par un inconnu, et elle est
 * toujours atteinte par un alias unique, jamais par une liste. La faute que ce
 * trait empêche est celle du tableau de bord, où le commerce EST connu et où
 * l'oubli d'un filtre montre les données du voisin.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(Tenant::SCOPE, function (Builder $query) {
            $tenantId = Tenant::id();
            if ($tenantId === null || $tenantId === '') {
                return; // page publique, webhook, console : voir le bloc ci-dessus
            }

            // Table préfixée : indispensable dès qu'une jointure met deux
            // colonnes `tenant_id` en présence.
            $query->where($query->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function ($model) {
            // Ne jamais écraser une valeur posée volontairement : une commission
            // encaissée par webhook connaît son commerce mieux que la session.
            if (($model->tenant_id ?? null) === null) {
                $model->tenant_id = Tenant::id();
            }
        });
    }

    /**
     * Sortie EXPLICITE de la limite : vue plateforme du fondateur, tâches de
     * maintenance, rapprochements. À n'employer que là où voir tous les
     * commerces est le but recherché, jamais pour « faire passer » une requête.
     */
    public function scopeAllTenants(Builder $query): Builder
    {
        return $query->withoutGlobalScope(Tenant::SCOPE);
    }
}
