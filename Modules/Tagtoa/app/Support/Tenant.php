<?php

namespace Modules\Tagtoa\App\Support;

/**
 * Pont vers les helpers globaux de Biztap (saas_vcard) — réutilise l'existant
 * sans le copier, et reste tolérant si un helper n'existe pas (dev/test).
 */
class Tenant
{
    /** Identifiant du tenant courant (stancl/tenancy via helper Biztap). */
    public static function id(): ?string
    {
        if (function_exists('getLogInTenantId')) {
            try {
                return getLogInTenantId();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return optional(auth()->user())->tenant_id;
    }

    /** Utilisateur connecté. */
    public static function user()
    {
        if (function_exists('getLogInUser')) {
            try {
                return getLogInUser();
            } catch (\Throwable $e) {
                // ignore
            }
        }

        return auth()->user();
    }

    /** Devise par défaut. */
    public static function currency(): string
    {
        return config('tagtoa.default_currency', 'HTG');
    }
}
