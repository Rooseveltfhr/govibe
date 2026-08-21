<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Inscription;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Commande : php artisan birthday:wishes
 *
 * Planifié dans routes/console.php :
 *   Schedule::command('birthday:wishes')->dailyAt('09:00');
 */
class SendBirthdayWishes extends Command
{
    protected $signature   = 'birthday:wishes {--dry-run : Simuler sans envoyer}';
    protected $description = 'Envoyer des vœux d\'anniversaire automatiques aux clients et inscrits';

    public function handle(NotificationService $notif): int
    {
        $dry   = $this->option('dry-run');
        $today = now();
        $sent  = 0;
        $skip  = 0;

        $this->info("🎂 Anniversaires du {$today->format('d/m')}");

        /* ── Clients CRM ──────────────────────────────────────────── */
        $clients = Client::whereNotNull('date_naissance')
            ->whereNotNull('phone')
            ->whereMonth('date_naissance', $today->month)
            ->whereDay('date_naissance', $today->day)
            ->get();

        $this->info("  Clients : {$clients->count()}");

        foreach ($clients as $client) {
            $age     = $today->year - $client->date_naissance->year;
            $message = $this->buildMessage($client->name, $age);
            $this->line("  → Client {$client->name} ({$client->phone})");

            if (! $dry) {
                $notif->whatsapp($client->phone, $message, $client->name, Client::class, $client->id);
                if ($client->email) {
                    $notif->email(
                        $client->email,
                        $client->name,
                        '🎂 Joyeux anniversaire — GOVIBE Innovation Hub',
                        $this->buildEmailBody($client->name, $age),
                        Client::class,
                        $client->id
                    );
                }
            }
            $sent++;
        }

        /* ── Inscrits formations ──────────────────────────────────── */
        $inscrits = Inscription::whereNotNull('date_naissance')
            ->whereNotNull('telephone')
            ->whereMonth('date_naissance', $today->month)
            ->whereDay('date_naissance', $today->day)
            ->get();

        $this->info("  Inscrits formations : {$inscrits->count()}");

        foreach ($inscrits as $ins) {
            $age     = $today->year - $ins->date_naissance->year;
            $message = $this->buildMessage($ins->nom_complet, $age);
            $this->line("  → Inscrit {$ins->nom_complet} ({$ins->telephone})");

            if (! $dry) {
                $notif->whatsapp($ins->telephone, $message, $ins->nom_complet, Inscription::class, $ins->id);
                if ($ins->email) {
                    $notif->email(
                        $ins->email,
                        $ins->nom_complet,
                        '🎂 Joyeux anniversaire — GOVIBE Innovation Hub',
                        $this->buildEmailBody($ins->nom_complet, $age),
                        Inscription::class,
                        $ins->id
                    );
                }
            }
            $sent++;
        }

        $this->info($dry
            ? "Dry-run — {$sent} vœux simulés, aucun envoi réel."
            : "✅ {$sent} vœux envoyés."
        );

        return self::SUCCESS;
    }

    private function buildMessage(string $name, int $age): string
    {
        $prenom = explode(' ', $name)[0];
        return "🎂 *Bonne fête {$prenom} !*\n\n"
             . "Toute l'équipe *GOVIBE Innovation Hub* vous souhaite un très joyeux anniversaire "
             . "et une excellente {$age}ème année !\n\n"
             . "Que cette nouvelle année vous apporte santé, succès, et de belles opportunités. 🎉\n\n"
             . "_— L'équipe GOVIBE_";
    }

    private function buildEmailBody(string $name, int $age): string
    {
        $prenom = explode(' ', $name)[0];
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:sans-serif;background:#f3f4f6;padding:32px">'
             . '<div style="max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08)">'
             . '<div style="background:#DC2626;padding:28px 32px;text-align:center">'
             . '<div style="font-size:48px">🎂</div>'
             . '<h1 style="color:#fff;font-size:22px;margin:12px 0 0">Joyeux Anniversaire !</h1>'
             . '</div>'
             . '<div style="padding:32px">'
             . "<p style=\"font-size:16px;color:#1f2937\">Cher(e) <strong>{$prenom}</strong>,</p>"
             . "<p style=\"color:#374151;line-height:1.7\">Toute l'équipe <strong>GOVIBE Innovation Hub</strong> vous souhaite un très joyeux anniversaire et une excellente <strong>{$age}ème année</strong> !</p>"
             . '<p style="color:#374151;line-height:1.7">Que cette nouvelle année vous apporte santé, succès et de belles opportunités.</p>'
             . '<div style="text-align:center;margin:28px 0">'
             . '<span style="font-size:32px">🎉🌟🎊</span>'
             . '</div>'
             . '</div>'
             . '<div style="background:#f9fafb;padding:16px 32px;text-align:center;border-top:1px solid #e5e7eb">'
             . '<p style="color:#6b7280;font-size:12px;margin:0">GOVIBE Innovation Hub · www.govibeht.com</p>'
             . '</div>'
             . '</div></body></html>';
    }
}
