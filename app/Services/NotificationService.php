<?php

namespace App\Services;

use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service unifié : WhatsApp (CallMeBot gratuit / Twilio) + Email SMTP.
 *
 * Drivers WhatsApp (config WHATSAPP_DRIVER dans .env) :
 *   callmebot  — gratuit, le destinataire doit activer via wa.me/+34698199624?text=join%20en%20X
 *   twilio     — production, nécessite TWILIO_ACCOUNT_SID + TWILIO_AUTH_TOKEN + TWILIO_WA_FROM
 *   log        — test local, écrit dans storage/logs/laravel.log
 */
class NotificationService
{
    public function __construct(
        private readonly string $waDriver      = '',
        private readonly string $callmebotKey  = '',
        private readonly string $twilioSid     = '',
        private readonly string $twilioToken   = '',
        private readonly string $twilioFrom    = '',
    ) {
    }

    /* ── WhatsApp ──────────────────────────────────────────────────── */

    public function whatsapp(
        string  $phone,
        string  $message,
        ?string $recipientName = null,
        ?string $contextType   = null,
        ?int    $contextId     = null,
    ): bool {
        $phone   = $this->normalizePhone($phone);
        $log     = $this->startLog('whatsapp', $phone, $recipientName, 'WhatsApp', $message, $contextType, $contextId);
        $success = false;
        $error   = null;

        try {
            $driver = $this->waDriver ?: config('services.whatsapp.driver', 'log');

            $success = match ($driver) {
                'callmebot' => $this->sendCallMeBot($phone, $message),
                'twilio'    => $this->sendTwilio($phone, $message),
                default     => $this->sendLog('WhatsApp', $phone, $message),
            };
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('WhatsApp send failed', ['phone' => $phone, 'error' => $error]);
        }

        $this->finishLog($log, $success, $error);
        return $success;
    }

    /* ── Email ─────────────────────────────────────────────────────── */

    public function email(
        string  $to,
        string  $name,
        string  $subject,
        string  $htmlBody,
        ?string $contextType = null,
        ?int    $contextId   = null,
    ): bool {
        $log     = $this->startLog('email', $to, $name, $subject, strip_tags($htmlBody), $contextType, $contextId);
        $success = false;
        $error   = null;

        try {
            Mail::html($htmlBody, function ($mail) use ($to, $name, $subject) {
                $mail->to($to, $name)
                     ->subject($subject)
                     ->from(config('mail.from.address'), config('mail.from.name'));
            });
            $success = true;
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            Log::error('Email send failed', ['to' => $to, 'error' => $error]);
        }

        $this->finishLog($log, $success, $error);
        return $success;
    }

    /* ── Helpers métier ────────────────────────────────────────────── */

    /** Envoyer la confirmation d'inscription Bootcamp. */
    public function bootcampConfirmation(\App\Models\BootcampRegistration $reg): void
    {
        $subject = 'Confirmation d\'inscription — GOVIBE AI Bootcamp 2026';
        $body    = $this->templateBootcampConfirm($reg);

        if ($reg->email) {
            $this->email($reg->email, $reg->full_name, $subject, $body, BootcampRegistration::class, $reg->id);
        }
        if ($reg->phone) {
            $msg = "✅ GOVIBE AI Bootcamp 2026\n"
                ."Bonjour {$reg->full_name} ! Votre inscription est reçue.\n"
                ."Module : {$reg->module_label}\n"
                ."Nous vérifierons votre paiement sous 24h.\n"
                ."govibeht.com";
            $this->whatsapp($reg->phone, $msg, $reg->full_name, BootcampRegistration::class, $reg->id);
        }
    }

    /** Notifier l'approbation d'une inscription Bootcamp. */
    public function bootcampApproval(\App\Models\BootcampRegistration $reg): void
    {
        $subject = '🎉 Paiement confirmé — GOVIBE AI Bootcamp 2026';
        $body    = $this->templateBootcampApproval($reg);

        if ($reg->email) {
            $this->email($reg->email, $reg->full_name, $subject, $body, BootcampRegistration::class, $reg->id);
        }
        if ($reg->phone) {
            $msg = "🎉 GOVIBE AI Bootcamp 2026\n"
                ."Félicitations {$reg->full_name} !\n"
                ."Votre paiement est validé ✅\n"
                ."Vous allez recevoir vos accès par email.\n"
                ."Rejoignez la communauté : govibeht.com";
            $this->whatsapp($reg->phone, $msg, $reg->full_name, BootcampRegistration::class, $reg->id);
        }
    }

    /** Rappel WhatsApp + email pour une facture en retard. */
    public function invoiceReminder(\App\Models\Invoice $invoice): void
    {
        $client = $invoice->client;
        if (! $client) return;

        $ref  = $invoice->reference;
        $days = now()->diffInDays($invoice->due_date, false);
        $late = abs($days).' jour'.( abs($days) > 1 ? 's' : '');

        if ($client->email) {
            $body = $this->templateInvoiceReminder($invoice, $client, $late, $days < 0);
            $this->email($client->email, $client->name, "Rappel facture {$ref} — GOVIBE", $body, Invoice::class, $invoice->id);
        }
        if ($client->phone) {
            $intro = $days < 0 ? "⚠️ Votre facture {$ref} est en retard de {$late}." : "📋 Rappel : facture {$ref} due dans {$late}.";
            $msg   = "GOVIBE Innovation Hub\n{$intro}\nMontant : HTG ".number_format($invoice->total,0,'.',',')."\nContact : govibeht.com";
            $this->whatsapp($client->phone, $msg, $client->name, Invoice::class, $invoice->id);
        }
    }

    /** Rappel réservation J-1. */
    public function bookingReminder(\App\Models\Booking $booking): void
    {
        $client = $booking->client;
        if (! $client) return;

        $dt  = $booking->start_datetime->format('d/m/Y à H:i');
        $msg = "GOVIBE Coworking\nRappel : votre réservation est demain.\nDate : {$dt}\nRéf. : {$booking->reference}\nGovibeht.com";

        if ($client->phone) {
            $this->whatsapp($client->phone, $msg, $client->name, Booking::class, $booking->id);
        }
        if ($client->email) {
            $body = $this->templateBookingReminder($booking, $client);
            $this->email($client->email, $client->name, "Rappel réservation — {$booking->reference}", $body, Booking::class, $booking->id);
        }
    }

    /* ── Drivers internes ──────────────────────────────────────────── */

    private function sendCallMeBot(string $phone, string $message): bool
    {
        $key = $this->callmebotKey ?: config('services.whatsapp.callmebot_key', '');
        if (empty($key)) {
            return $this->sendLog('CallMeBot', $phone, $message);
        }

        $res = Http::timeout(10)->get('https://api.callmebot.com/whatsapp.php', [
            'phone'  => $phone,
            'text'   => $message,
            'apikey' => $key,
        ]);

        return $res->successful();
    }

    private function sendTwilio(string $phone, string $message): bool
    {
        $sid   = $this->twilioSid   ?: config('services.twilio.sid', '');
        $token = $this->twilioToken ?: config('services.twilio.token', '');
        $from  = $this->twilioFrom  ?: config('services.twilio.wa_from', '');

        if (empty($sid) || empty($token) || empty($from)) {
            Log::warning('Twilio not configured — falling back to log driver');
            return $this->sendLog('Twilio', $phone, $message);
        }

        $res = Http::timeout(15)
            ->withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => 'whatsapp:'.$from,
                'To'   => 'whatsapp:'.$phone,
                'Body' => $message,
            ]);

        return $res->successful();
    }

    private function sendLog(string $driver, string $phone, string $message): bool
    {
        Log::channel('stack')->info("[{$driver}] WhatsApp → {$phone}: {$message}");
        return true; // always succeeds in log mode
    }

    /* ── Logging ───────────────────────────────────────────────────── */

    private function startLog(string $channel, string $recipient, ?string $name, ?string $subject, string $body, ?string $ctxType, ?int $ctxId): OutboundMessage
    {
        return OutboundMessage::create([
            'channel'        => $channel,
            'recipient'      => $recipient,
            'recipient_name' => $name,
            'subject'        => $subject,
            'body'           => $body,
            'status'         => 'pending',
            'context_type'   => $ctxType,
            'context_id'     => $ctxId,
            'sent_by'        => Auth::id(),
        ]);
    }

    private function finishLog(OutboundMessage $log, bool $success, ?string $error): void
    {
        $log->update([
            'status'  => $success ? 'sent' : 'failed',
            'error'   => $error,
            'sent_at' => $success ? now() : null,
        ]);
    }

    /* ── Templates email HTML ──────────────────────────────────────── */

    private function emailWrap(string $title, string $content): string
    {
        $red  = '#DC2626';
        $dark = '#0A0A0A';
        return "<!DOCTYPE html><html><head><meta charset='utf-8'><style>
body{margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif}
.wrap{max-width:580px;margin:30px auto;background:#fff;border-radius:12px;overflow:hidden}
.hdr{background:{$dark};padding:28px 32px;text-align:center}
.hdr h1{color:#fff;margin:0;font-size:22px;font-weight:700}
.hdr span{color:{$red};font-size:28px;font-weight:900}
.body{padding:32px}
.body p{color:#444;font-size:15px;line-height:1.6;margin:0 0 14px}
.box{background:#f8f8f8;border-radius:8px;padding:20px;margin:20px 0}
.box b{display:block;font-size:13px;color:#888;margin-bottom:4px}
.box span{font-size:16px;color:#111;font-weight:600}
.btn{display:inline-block;background:{$red};color:#fff;text-decoration:none;padding:14px 28px;border-radius:8px;font-weight:700;font-size:15px;margin:16px 0}
.ftr{background:#f4f4f4;padding:20px 32px;text-align:center;color:#999;font-size:12px}
</style></head><body>
<div class='wrap'>
<div class='hdr'><h1><span>GOVIBE</span> Innovation Hub</h1><p style='color:#aaa;margin:6px 0 0;font-size:13px'>{$title}</p></div>
<div class='body'>{$content}</div>
<div class='ftr'>GOVIBE Innovation Hub · Port-au-Prince, Haïti · govibeht.com<br>© ".date('Y')." Tous droits réservés.</div>
</div></body></html>";
    }

    private function templateBootcampConfirm(\App\Models\BootcampRegistration $reg): string
    {
        $content = "<p>Bonjour <strong>{$reg->full_name}</strong>,</p>
<p>Votre inscription au <strong>GOVIBE AI Bootcamp 2026</strong> a bien été reçue !</p>
<div class='box'>
  <b>Module choisi</b><span>{$reg->module_label}</span>
  <b style='margin-top:12px'>Paiement</b><span>".strtoupper($reg->payment_method)." · {$reg->amount} {$reg->currency}</span>
</div>
<p>Nous vérifions votre paiement sous <strong>24 heures</strong>. Vous recevrez une confirmation dès validation.</p>
<a class='btn' href='https://govibeht.com/bootcamp-ai-2026'>Voir la page Bootcamp</a>
<p style='color:#888;font-size:13px'>Des questions ? Écrivez-nous sur WhatsApp ou à govibeht@gmail.com</p>";
        return $this->emailWrap('Confirmation d\'inscription — AI Bootcamp 2026', $content);
    }

    private function templateBootcampApproval(\App\Models\BootcampRegistration $reg): string
    {
        $content = "<p>Bonjour <strong>{$reg->full_name}</strong>,</p>
<p>🎉 <strong>Félicitations !</strong> Votre paiement a été validé et votre inscription est confirmée.</p>
<div class='box'>
  <b>Module</b><span>{$reg->module_label}</span>
  <b style='margin-top:12px'>Statut</b><span style='color:#16A34A'>✓ Approuvé</span>
  <b style='margin-top:12px'>Accès</b><span>Prochainement par email</span>
</div>
<p>Bienvenue dans la communauté GOVIBE AI ! Vous allez recevoir vos accès et les instructions de démarrage très prochainement.</p>
<a class='btn' href='https://govibeht.com/bootcamp-ai-2026'>Accéder à ma formation</a>";
        return $this->emailWrap('Paiement confirmé — AI Bootcamp 2026', $content);
    }

    private function templateInvoiceReminder(\App\Models\Invoice $invoice, mixed $client, string $delay, bool $overdue): string
    {
        $alert = $overdue
            ? "<p style='background:#FEF2F2;border-left:4px solid #DC2626;padding:12px;color:#991B1B'>⚠️ Cette facture est en retard de <strong>{$delay}</strong>.</p>"
            : "<p>📋 Rappel : cette facture arrive à échéance dans <strong>{$delay}</strong>.</p>";
        $content = "<p>Bonjour <strong>{$client->name}</strong>,</p>{$alert}
<div class='box'>
  <b>Référence</b><span>{$invoice->reference}</span>
  <b style='margin-top:12px'>Montant</b><span>HTG ".number_format($invoice->total,0,'.',',')."</span>
  <b style='margin-top:12px'>Échéance</b><span>".$invoice->due_date->format('d/m/Y')."</span>
</div>
<p>Pour tout arrangement ou question, contactez-nous sans hésiter.</p>
<a class='btn' href='https://govibeht.com'>Voir ma facture</a>";
        return $this->emailWrap('Rappel de paiement — '.$invoice->reference, $content);
    }

    private function templateBookingReminder(\App\Models\Booking $booking, mixed $client): string
    {
        $content = "<p>Bonjour <strong>{$client->name}</strong>,</p>
<p>📅 Votre réservation chez <strong>GOVIBE Coworking</strong> est confirmée pour demain.</p>
<div class='box'>
  <b>Référence</b><span>{$booking->reference}</span>
  <b style='margin-top:12px'>Date & heure</b><span>".$booking->start_datetime->format('d/m/Y à H:i')."</span>
  <b style='margin-top:12px'>Fin</b><span>".$booking->end_datetime->format('H:i')."</span>
</div>
<p>Merci de votre confiance. À demain !</p>";
        return $this->emailWrap('Rappel réservation — '.$booking->reference, $content);
    }

    /* ── Utilitaires ───────────────────────────────────────────────── */

    private function normalizePhone(string $phone): string
    {
        // Supprime tout sauf les chiffres et le +
        $clean = preg_replace('/[^\d+]/', '', $phone);
        // Ajoute + si pas présent
        if (! str_starts_with($clean, '+')) {
            $clean = '+509'.$clean; // préfixe Haïti par défaut
        }
        return $clean;
    }
}
