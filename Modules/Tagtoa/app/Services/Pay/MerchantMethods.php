<?php

namespace Modules\Tagtoa\App\Services\Pay;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Modules\Tagtoa\App\Models\Pay\PaymentPage;

/**
 * TAGTOA Pay — moyens de paiement du MARCHAND, configurés une seule fois.
 *
 * Ils vivent sur une page « bibliothèque » technique (une par marchand), jamais
 * listée ni publiquement accessible. Tous les liens de paiement du marchand
 * présentent ces moyens-là : créer un lien ne demande plus de ressaisir un
 * numéro de compte, et corriger un numéro le corrige partout d'un coup.
 */
class MerchantMethods
{
    /** Bibliothèque du marchand, créée à la volée si elle n'existe pas encore. */
    public function library(?string $tenantId): PaymentPage
    {
        $page = PaymentPage::where('is_library', true)
            ->where(fn ($q) => $tenantId === null
                ? $q->whereNull('tenant_id')
                : $q->where('tenant_id', $tenantId))
            ->first();

        if ($page) {
            return $page;
        }

        return PaymentPage::create([
            'tenant_id'        => $tenantId,
            'title'            => __('Moyens de paiement'),
            'alias'            => $this->uniqueAlias(),
            'default_currency' => 'HTG',
            'is_active'        => false, // jamais publique
            'is_library'       => true,
        ]);
    }

    /** Moyens ACTIFS du marchand, dans l'ordre d'affichage. */
    public function active(?string $tenantId): Collection
    {
        return $this->library($tenantId)
            ->methods()->where('is_active', true)->orderBy('sort')->get();
    }

    /** Tous les moyens configurés (actifs ou non), indexés par type. */
    public function allByType(?string $tenantId): Collection
    {
        return $this->library($tenantId)->methods()->get()->keyBy('type');
    }

    private function uniqueAlias(): string
    {
        do {
            $alias = 'lib-'.Str::lower(Str::random(18));
        } while (PaymentPage::where('alias', $alias)->exists());

        return $alias;
    }
}
