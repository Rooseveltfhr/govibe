<?php

namespace Modules\Tagtoa\App\Services\Business;

use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Business\Business;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA — création et bascule entre les commerces d'un compte.
 *
 * Le tout premier commerce d'un compte reçoit pour identifiant le `tenant_id`
 * historique de ce compte. C'est ce qui permet à un marchand déjà installé de
 * retrouver son menu, ses ventes et ses liens de paiement sans qu'une seule
 * ligne soit réécrite en base. Les suivants reçoivent un ULID.
 */
class BusinessService
{
    /** Commerces d'un compte, le plus ancien d'abord. */
    public function forAccount(?string $accountId): Collection
    {
        if ($accountId === null) {
            return collect();
        }

        return Business::where('account_id', $accountId)
            ->orderBy('created_at')->orderBy('name')->get();
    }

    /** Le compte a-t-il déjà déclaré son commerce ? */
    public function hasAny(?string $accountId): bool
    {
        return $accountId !== null
            && Business::where('account_id', $accountId)->exists();
    }

    /**
     * Enregistre un commerce.
     *
     * @param  array  $data  name, type, categories, sells_products,
     *                       sells_services, address, phone, currency, logo_path
     */
    public function create(?string $accountId, array $data): Business
    {
        $business = Business::create($this->attributes($data) + [
            'id'         => $this->nextId($accountId),
            'account_id' => $accountId,
        ]);

        // Le commerce qu'on vient de créer devient celui sur lequel on travaille.
        Tenant::switchTo($business->id);

        return $business;
    }

    public function update(Business $business, array $data): Business
    {
        $business->update($this->attributes($data));
        Tenant::flush();

        return $business;
    }

    /**
     * Identifiant du prochain commerce.
     *
     * Le PREMIER d'un compte reprend le `tenant_id` historique : les données
     * déjà en base y sont rattachées, et les reprendre sous un nouvel
     * identifiant reviendrait à les perdre de vue. Un ULID sinon.
     */
    protected function nextId(?string $accountId): string
    {
        if ($accountId !== null && ! $this->hasAny($accountId)) {
            return $accountId;
        }

        return Business::newId();
    }

    /** Champs propres, quelle que soit la provenance du formulaire. */
    protected function attributes(array $data): array
    {
        $categories = $data['categories'] ?? [];
        if (is_string($categories)) {
            // Saisie libre séparée par des virgules.
            $categories = preg_split('/\s*,\s*/', $categories) ?: [];
        }
        $categories = array_values(array_filter(array_map(
            fn ($c) => mb_substr(trim((string) $c), 0, 60),
            (array) $categories
        )));

        // Un commerce qui ne vend « ni produit ni service » ne veut rien dire :
        // on retombe sur les produits plutôt que d'enregistrer un commerce muet.
        $produits = (bool) ($data['sells_products'] ?? false);
        $services = (bool) ($data['sells_services'] ?? false);
        if (! $produits && ! $services) {
            $produits = true;
        }

        return [
            'name'           => trim((string) $data['name']),
            'type'           => $data['type'] ?? 'other',
            'categories'     => $categories ?: null,
            'sells_products' => $produits,
            'sells_services' => $services,
            'address'        => $data['address'] ?? null,
            'phone'          => $data['phone'] ?? null,
            'currency'       => strtoupper(trim((string) ($data['currency'] ?? 'HTG'))) ?: 'HTG',
            'is_active'      => (bool) ($data['is_active'] ?? true),
        ] + (array_key_exists('logo_path', $data) ? ['logo_path' => $data['logo_path']] : []);
    }
}
