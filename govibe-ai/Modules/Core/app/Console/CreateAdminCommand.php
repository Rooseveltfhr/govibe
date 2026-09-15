<?php

namespace Modules\Core\Console;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Kreye (oswa mete ajou) yon administratè.
 *
 * Pa gen enskripsyon piblik: yon paj enskripsyon sou yon panèl ki montre
 * kòmand kliyan yo ta vle di nenpòt moun ka fè tèt li administratè. Premye
 * kont lan pase isit la, nan liy kòmand, sou sèvè a.
 *
 * Modpas la pa nan siyati kòmand lan: yon modpas nan yon liy kòmand rete
 * nan istorik shell la. Kòmand lan mande l.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'govibe:admin {email} {--name=} {--password=}';

    protected $description = 'Kreye oswa mete ajou yon kont administratè';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Imèl la pa valab: {$email}");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: $this->secret('Modpas'));

        if (strlen($password) < 12) {
            $this->error('Modpas la dwe gen omwen 12 karaktè.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) ($this->option('name') ?: strstr($email, '@', true) ?: $email),
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );

        $this->info("Administratè pare: {$user->email}");
        $this->line('Konekte sou /admin/login');

        return self::SUCCESS;
    }
}
