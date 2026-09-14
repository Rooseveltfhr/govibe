<?php

namespace Modules\Tagtoa\App\Services\Stand;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Tagtoa\App\Models\Menu\Menu;
use Modules\Tagtoa\App\Models\Stand\Stand;
use Modules\Tagtoa\App\Support\Stand\StandId;
use Modules\Tagtoa\App\Support\Stand\StandState;

/**
 * TAGTOA SMART STAND — où mène un scan.
 *
 * `/s/TG-000001` est un AIGUILLAGE, jamais une destination. C'est toute la
 * valeur du produit : l'objet imprimé ne change plus jamais, et ce qu'il
 * atteint change autant que nécessaire — un menu aujourd'hui, une commande
 * dans un an, un paiement dans trois, sans réimprimer quoi que ce soit.
 *
 * C'EST LA REQUÊTE LA PLUS FRÉQUENTE DE TOUTE LA PLATEFORME. Chaque client
 * attablé la déclenche. Sans cache, une heure de pointe dans mille restaurants
 * tombe simultanément sur la base. Elle est donc servie depuis la mémoire, et
 * le cache est vidé dès que la destination change.
 *
 * Le cache ne garde QUE la destination — jamais le secret, jamais l'état
 * complet du stand.
 */
class StandResolver
{
    /** Une heure : assez pour absorber un service, assez court pour qu'un oubli d'invalidation se répare seul. */
    private const TTL = 3600;

    /* Ce que le scan doit produire. Une chaîne libre finirait par diverger
       entre le service qui la renvoie et la vue qui la lit. */
    public const GO_ACTIVATE = 'activate';  // stand vierge : proposer l'activation
    public const GO_TARGET   = 'target';    // stand actif : la page du commerce
    public const GO_PAUSED   = 'paused';    // suspendu : page sobre, jamais une erreur
    public const GO_CLOSED   = 'closed';    // perdu, retiré, révoqué
    public const GO_UNKNOWN  = 'unknown';   // aucun stand ne porte cet identifiant

    /**
     * Où mène ce scan.
     *
     * Renvoie une destination LÉGÈRE — pas le stand. Mettre un modèle Eloquent
     * en cache le sérialise entier (secret haché compris) et le fige : une
     * ligne modifiée ailleurs continuerait d'être servie telle qu'elle était.
     * Les écrans qui ont besoin de l'objet appellent `stand()`.
     *
     * @return array{go:string, id:?int, url:?string, label:?string}
     */
    public function resolve(?string $publicId): array
    {
        $id = StandId::normalizeId($publicId);

        if (! StandId::isValidId($id)) {
            // Même réponse qu'un stand inexistant : un identifiant mal formé ne
            // doit pas se distinguer d'un identifiant absent, sinon on apprend
            // à l'attaquant à quoi ressemble un identifiant valide.
            return $this->rien();
        }

        $cle = 'tagtoa:stand:'.$id;

        // Le « manque » de cache est aussi notre signal d'activité : compter
        // chaque scan écrirait dans la base sur la requête la plus fréquente de
        // la plateforme, ce qui annulerait exactement le bénéfice du cache.
        $vu = false;
        $destination = Cache::remember($cle, self::TTL, function () use ($id, &$vu) {
            $vu = true;

            return $this->calculer($id);
        });

        if ($vu && $destination['id'] !== null) {
            $this->noterActivite((int) $destination['id']);
        }

        return $destination;
    }

    /**
     * Note qu'un stand a servi.
     *
     * Au plus une écriture par heure et par stand — le rythme du cache. Le
     * compteur ne dit donc PAS « combien de clients ont scanné » mais
     * « combien d'heures ce stand a été utilisé ». C'est ce que le marchand
     * veut vraiment savoir : est-ce que mon stand sert, ou dort-il sur une
     * table ? Un compteur exact coûterait une écriture par client.
     *
     * Tolérant : une panne d'écriture ne doit jamais empêcher un menu de
     * s'ouvrir devant un client.
     */
    private function noterActivite(int $standId): void
    {
        try {
            Stand::whereKey($standId)->update([
                'last_scanned_at' => now(),
                'scan_count'      => DB::raw('scan_count + 1'),
            ]);
        } catch (\Throwable $e) {
            // Le client doit voir son menu, même si la statistique se perd.
        }
    }

    /** Le stand derrière un identifiant, sans cache. Pour les écrans qui écrivent. */
    public function stand(?string $publicId): ?Stand
    {
        $id = StandId::normalizeId($publicId);

        return StandId::isValidId($id)
            ? Stand::with('batch')->byPublicId($id)->first()
            : null;
    }

    /**
     * Vide le cache d'un stand.
     *
     * À appeler après TOUTE écriture qui change la destination : réclamation,
     * changement de module cible, suspension, cession. Un oubli ferait
     * continuer d'afficher l'ancienne page pendant une heure — c'est-à-dire
     * afficher le menu de l'ancien propriétaire au client du nouveau.
     */
    public function forget(?string $publicId): void
    {
        Cache::forget('tagtoa:stand:'.StandId::normalizeId($publicId));
    }

    /* ---------------- interne ---------------- */

    /** @return array{go:string, id:?int, url:?string, label:?string} */
    private function calculer(string $id): array
    {
        $stand = Stand::with('batch')->byPublicId($id)->first();

        if (! $stand) {
            return $this->rien();
        }

        // Objet hors circulation : perdu, endommagé, retiré.
        if (in_array($stand->physical_state, StandState::PHYSICAL_CLOSED, true)
            || $stand->digital_state === StandState::REVOKED) {
            return ['go' => self::GO_CLOSED, 'id' => $stand->id, 'url' => null, 'label' => null];
        }

        if ($stand->digital_state === StandState::SUSPENDED) {
            // JAMAIS une erreur ni une page de paiement : un stand est posé sur
            // une table, devant un client qui a faim. On prévient le
            // commerçant, pas son client.
            return ['go' => self::GO_PAUSED, 'id' => $stand->id, 'url' => null, 'label' => null];
        }

        if ($stand->resolvesToBusiness()) {
            $url = $this->destination($stand);

            // Un stand actif dont la cible a disparu — menu supprimé, commerce
            // fermé — ne doit pas produire un 404 au client. On retombe sur la
            // page sobre, qui au moins ne ressemble pas à une panne.
            return $url
                ? ['go' => self::GO_TARGET, 'id' => $stand->id, 'url' => $url, 'label' => $stand->location_label]
                : ['go' => self::GO_PAUSED, 'id' => $stand->id, 'url' => null, 'label' => null];
        }

        if ($stand->isClaimable()) {
            return ['go' => self::GO_ACTIVATE, 'id' => $stand->id, 'url' => null, 'label' => null];
        }

        return ['go' => self::GO_CLOSED, 'id' => $stand->id, 'url' => null, 'label' => null];
    }

    /**
     * L'adresse publique du commerce.
     *
     * `target_module` dit vers quoi pointer. Aujourd'hui le menu ; demain une
     * page de paiement ou de liens, sans qu'un seul objet soit réimprimé.
     */
    private function destination(Stand $stand): ?string
    {
        $module = $stand->target_module ?: 'menu';

        if ($module === 'menu') {
            $alias = Menu::where('tenant_id', $stand->tenant_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('alias');

            return $alias ? url('/menu/'.$alias) : null;
        }

        // Module non encore branché : on ne devine pas une URL qui n'existe pas.
        return null;
    }

    /** @return array{go:string, id:null, url:null, label:null} */
    private function rien(): array
    {
        return ['go' => self::GO_UNKNOWN, 'id' => null, 'url' => null, 'label' => null];
    }
}
