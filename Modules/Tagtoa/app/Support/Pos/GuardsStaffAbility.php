<?php

namespace Modules\Tagtoa\App\Support\Pos;

use Modules\Tagtoa\App\Models\Staff\Staff;

/**
 * TAGTOA POS — bloquer une action que le rôle connecté n'a pas le droit de
 * faire.
 *
 * `StaffAccess::can()` existait déjà (rôles, droits) mais n'était vérifié
 * NULLE PART côté serveur : un caissier pouvait rembourser, modifier ou
 * supprimer le catalogue en appelant directement la route, sans que rien ne
 * s'y oppose — seul le formulaire le lui cachait.
 *
 * AUCUN employé connecté ne veut PAS dire « personne » : cela veut dire que
 * c'est le patron qui travaille directement, sans avoir ouvert de session
 * employé. Le compte TAGTOA lui-même est déjà authentifié par le garde
 * `role:admin|super_admin` sur ces routes — c'est LUI la véritable frontière
 * de sécurité. La restriction par rôle ne s'applique donc que lorsqu'un
 * employé identifié est effectivement aux commandes.
 */
trait GuardsStaffAbility
{
    protected function denyUnless(?Staff $staff, string $ability): void
    {
        abort_if($staff && ! $staff->can($ability), 403, __('Vous n\'avez pas le droit de faire cela.'));
    }
}
