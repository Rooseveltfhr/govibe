<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée (ou met à jour) le compte super_admin initial.
 *
 * Le mot de passe n'est jamais codé en dur : il vient de MSN_ADMIN_PASSWORD
 * (.env) ou, à défaut, d'un mot de passe aléatoire affiché une seule fois
 * dans la sortie de la commande — jamais un défaut prévisible en prod.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('MSN_ADMIN_EMAIL', 'admin@molesaintnicolas.com');
        $password = env('MSN_ADMIN_PASSWORD');
        $generated = $password === null;
        $password ??= Str::password(20);

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => 'Administrateur Môle-Saint-Nicolas', 'password' => $password]
        );

        $user->syncRoles(['super_admin']);

        if ($generated) {
            $this->command?->warn("Compte super_admin créé : {$email} / mot de passe généré : {$password}");
            $this->command?->warn('Notez-le maintenant et changez-le après la première connexion — il ne sera plus jamais affiché.');
        }
    }
}
