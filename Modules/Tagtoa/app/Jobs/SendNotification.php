<?php

namespace Modules\Tagtoa\App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tagtoa\App\Services\Notifications\NotificationService;

/**
 * TAGTOA — envoi de notification découplé (queue), multi-canal, tolérant.
 *
 * payload = [
 *   'channels' => ['email','whatsapp'],   // défaut ['email']
 *   'email'    => 'client@ex.com',
 *   'phone'    => '+509...',
 *   'subject'  => '...',
 *   'body'     => '...',
 * ]
 * Chaque canal est tolérant côté NotificationService (no-op si désactivé).
 */
class SendNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public array $payload)
    {
    }

    public function handle(NotificationService $svc): void
    {
        $p = $this->payload;
        $channels = $p['channels'] ?? ['email'];
        $subject = $p['subject'] ?? '';
        $body = $p['body'] ?? '';

        foreach ($channels as $ch) {
            if ($ch === 'email') {
                $svc->email($p['email'] ?? null, $subject, $body);
            } elseif ($ch === 'whatsapp') {
                $svc->whatsapp($p['phone'] ?? null, trim($subject."\n".$body));
            }
        }
    }
}
