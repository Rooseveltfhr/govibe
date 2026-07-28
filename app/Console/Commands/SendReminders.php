<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\Invoice;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Commande : php artisan notify:reminders
 *
 * Planifier dans routes/console.php ou app/Console/Kernel.php :
 *   Schedule::command('notify:reminders')->dailyAt('08:00');
 */
class SendReminders extends Command
{
    protected $signature   = 'notify:reminders {--dry-run : Simuler sans envoyer}';
    protected $description = 'Envoyer les rappels automatiques : factures en retard et réservations J-1';

    public function handle(NotificationService $notif): int
    {
        $dry = $this->option('dry-run');

        /* ── Réservations J-1 ─────────────────────────────────────── */
        $bookings = Booking::with('client')
            ->where('status', 'confirmed')
            ->whereDate('start_datetime', now()->addDay()->toDateString())
            ->get();

        $this->info("Réservations demain : {$bookings->count()}");
        foreach ($bookings as $b) {
            if ($b->client) {
                $this->line("  → {$b->reference} · {$b->client->name}");
                if (! $dry) {
                    $notif->bookingReminder($b);
                }
            }
        }

        /* ── Factures en retard (> 0 jours) ──────────────────────── */
        $invoices = Invoice::with('client')
            ->whereIn('status', ['sent', 'partial'])
            ->where('due_date', '<', now()->toDateString())
            ->get();

        $this->info("Factures en retard : {$invoices->count()}");
        foreach ($invoices as $inv) {
            if ($inv->client) {
                $days = now()->diffInDays($inv->due_date);
                $this->line("  → {$inv->reference} · {$inv->client->name} · {$days}j de retard");
                // Rappel seulement à J+1, J+7, J+15, J+30 pour éviter le spam
                if (! $dry && in_array($days, [1, 7, 15, 30])) {
                    $notif->invoiceReminder($inv);
                }
            }
        }

        $this->info($dry ? 'Dry-run terminé — aucun message envoyé.' : 'Rappels envoyés.');
        return self::SUCCESS;
    }
}
