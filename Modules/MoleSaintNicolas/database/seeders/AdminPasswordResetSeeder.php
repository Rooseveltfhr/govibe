<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Réinitialisation ponctuelle du mot de passe admin, pour le cas où le
 * compte est verrouillé et qu'aucun SMTP n'est configuré en production
 * (donc le lien "mot de passe oublié" ne peut être livré par email).
 *
 * Déclenchée en remplissant MSN_ADMIN_PASSWORD_RESET dans le .env du
 * serveur, puis en relançant un déploiement. Ne s'applique qu'une seule
 * fois : un fichier témoin dans storage/ (jamais synchronisé par
 * remote-deploy.sh, donc jamais écrasé) empêche toute réapplication, même
 * si la variable reste renseignée dans le .env après coup.
 */
class AdminPasswordResetSeeder extends Seeder
{
    public function run(): void
    {
        $newPassword = env('MSN_ADMIN_PASSWORD_RESET');

        if (! $newPassword) {
            return;
        }

        $sentinel = storage_path('app/.admin-password-reset-applied');

        if (file_exists($sentinel)) {
            return;
        }

        $email = env('MSN_ADMIN_EMAIL', 'admin@molesaintnicolas.com');
        $user = User::firstWhere('email', $email);

        if ($user) {
            $user->update(['password' => $newPassword]);
            $this->command?->warn("Mot de passe de {$email} réinitialisé depuis MSN_ADMIN_PASSWORD_RESET.");
        }

        file_put_contents($sentinel, now()->toDateTimeString());
    }
}
