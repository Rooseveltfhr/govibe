<?php

namespace Modules\Tagtoa\App\Services\Staff;

use Illuminate\Support\Collection;
use Modules\Tagtoa\App\Models\Staff\Staff;
use Modules\Tagtoa\App\Services\Event\StaffPinService;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;

/**
 * TAGTOA — création et authentification des employés d'un commerce.
 *
 * Le hachage et la vérification du code reposent sur StaffPinService, déjà
 * écrit et testé pour le contrôle d'accès des événements : un seul endroit sait
 * ce qu'est un PIN valide, et une correction profite aux deux usages.
 */
class StaffService
{
    /** Employés du commerce, actifs d'abord, par nom. */
    public function roster(?string $tenantId): Collection
    {
        return Staff::where('tenant_id', $tenantId)
            ->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * Enregistre un employé. Le code n'est écrit que s'il est fourni : modifier
     * la fiche de Jacqueline ne doit pas l'obliger à changer de code.
     *
     * @param  array  $data  name, email, phone, role, terminal_id, is_active, pin
     */
    public function save(?string $tenantId, array $data, ?Staff $staff = null): Staff
    {
        $attrs = [
            'tenant_id'   => $tenantId,
            'name'        => trim((string) $data['name']),
            'email'       => $data['email'] ?? null,
            'phone'       => $data['phone'] ?? null,
            'role'        => StaffAccess::isValidRole($data['role'] ?? null)
                ? $data['role']
                : StaffAccess::ROLE_CASHIER,   // rôle inconnu ⇒ le moins ouvert
            'terminal_id' => $data['terminal_id'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? true),
        ];

        $pin = (string) ($data['pin'] ?? '');
        if ($pin !== '') {
            $attrs['pin_hash'] = StaffPinService::hashPin($pin);
        }

        if ($staff) {
            $staff->update($attrs);

            return $staff;
        }

        return Staff::create($attrs + ['created_by' => $data['created_by'] ?? null]);
    }

    /**
     * Retrouve l'employé de CE commerce dont le code correspond.
     *
     * Le code seul ne désigne personne : deux employés peuvent avoir choisi
     * « 1234 ». On parcourt donc les employés actifs du commerce et on retient
     * le premier dont le hachage correspond — jamais une recherche par code.
     *
     * Les employés inactifs ne sont jamais candidats : désactiver quelqu'un
     * doit lui fermer la caisse immédiatement.
     */
    public function authenticate(?string $tenantId, string $pin, ?int $terminalId = null): ?Staff
    {
        if (! StaffPinService::isValidPinFormat($pin)) {
            return null;
        }

        $candidats = Staff::where('tenant_id', $tenantId)->where('is_active', true)
            ->when($terminalId, fn ($q) => $q->where(fn ($w) => $w
                ->where('terminal_id', $terminalId)->orWhereNull('terminal_id')))
            ->get();

        foreach ($candidats as $staff) {
            if (StaffPinService::verifyPin($pin, (string) $staff->pin_hash)) {
                $staff->forceFill(['last_login_at' => now()])->save();

                return $staff;
            }
        }

        return null;
    }

    /**
     * Confirme une action sensible (remise, retour, suppression) en redemandant
     * le code de CET employé — pas de n'importe qui. Une caisse reste ouverte
     * sur un comptoir : c'est exactement là que le code protège.
     */
    public function confirms(Staff $staff, string $pin): bool
    {
        return $staff->is_active
            && StaffPinService::verifyPin($pin, (string) $staff->pin_hash);
    }
}
