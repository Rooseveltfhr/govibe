<?php

namespace Modules\Tagtoa\App\Models\Staff;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tagtoa\App\Models\Pos\Terminal;
use Modules\Tagtoa\App\Support\BelongsToTenant;
use Modules\Tagtoa\App\Support\Pos\StaffAccess;

/**
 * TAGTOA — un employé du commerce (patron, gérant, caissier).
 *
 * Appartient à TAGTOA et à rien d'autre : aucune référence au cœur Biztap, pour
 * que le retrait de celui-ci ne remette pas en cause les employés du commerce.
 *
 * Le code d'accès n'existe jamais en clair. `pin_hash` est masqué à la
 * sérialisation : il ne peut donc pas fuir dans une réponse JSON, un journal ou
 * une vue par simple inattention.
 */
class Staff extends Model
{
    use BelongsToTenant;

    protected $table = 'tagtoa_staff';

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'role', 'pin_hash',
        'terminal_id', 'is_active', 'created_by', 'last_login_at',
    ];

    protected $hidden = ['pin_hash'];

    protected $casts = [
        'is_active'     => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /** Caisse habituelle. Un employé peut tourner : la relation est facultative. */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class, 'terminal_id');
    }

    /** Cet employé peut-il faire cette action ? */
    public function can(string $ability): bool
    {
        return $this->is_active && StaffAccess::can($this->role, $ability);
    }

    /** Jusqu'où voit-il les ventes : 'all', 'till' ou 'own'. */
    public function salesScope(): string
    {
        return StaffAccess::salesScope($this->role);
    }

    public function isOwner(): bool
    {
        return $this->role === StaffAccess::ROLE_OWNER;
    }

    /** Libellé du rôle, prêt à afficher. */
    public function getRoleLabelAttribute(): string
    {
        return StaffAccess::ROLES[$this->role] ?? $this->role;
    }

    /**
     * Initiales, pour l'affichage compact des rapports (« qui tenait la caisse »).
     * Un nom vide ne doit pas produire une pastille vide.
     */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }

        $first = mb_strtoupper(mb_substr($parts[0], 0, 1));
        $last  = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1)) : '';

        return $first.$last;
    }
}
