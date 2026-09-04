<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Rôles CMS de molesaintnicolas.com (voir docs/molesaintnicolas §7).
 * Les permissions fines par entité seront ajoutées module par module ;
 * ce seeder ne pose que les rôles pour que l'admin fondateur existe dès la Phase 1.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['super_admin', 'admin', 'editor', 'moderator', 'partner'] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }
}
