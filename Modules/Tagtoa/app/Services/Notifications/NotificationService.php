<?php

namespace Modules\Tagtoa\App\Services\Notifications;

/**
 * TAGTOA — notifications (e-mail) sur les événements clés.
 *
 * Conception « tolérante & opt-in » :
 *  - Le cœur (compose / validRecipient) est de la LOGIQUE PURE, testable sans Laravel.
 *  - L'envoi réel n'a lieu QUE si `tagtoa.notifications.enabled` est vrai ET qu'un
 *    transporteur mail est configuré côté hôte (SMTP). Sinon, no-op silencieux.
 *  - Tout envoi est encapsulé dans un try/catch : une notification ne doit JAMAIS
 *    casser le parcours public (réservation, commande…).
 *
 * Activation : définir TAGTOA_NOTIFY=true + la config mail Laravel (SMTP) côté VPS.
 */
class NotificationService
{
    /** Assemble un message texte (sujet + corps) à partir de lignes. PUR. */
    public static function compose(string $subject, array $lines): array
    {
        $clean = array_values(array_filter(
            array_map(static fn ($l) => is_string($l) ? rtrim($l) : '', $lines),
            static fn ($l) => $l !== ''
        ));

        return ['subject' => trim($subject), 'body' => implode("\n", $clean)];
    }

    /** Un destinataire e-mail est-il exploitable ? PUR. */
    public static function validRecipient(?string $email): bool
    {
        return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
    }

    /** Les notifications sont-elles activées (flag de config) ? */
    public function enabled(): bool
    {
        return (bool) config('tagtoa.notifications.enabled', false);
    }

    /** Envoi e-mail tolérant. Retourne true si tenté, false si ignoré/échoué. */
    public function email(?string $to, string $subject, string $body): bool
    {
        if (! $this->enabled() || ! self::validRecipient($to)) {
            return false;
        }

        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($to, $subject) {
                $m->to(trim($to))->subject($subject);
            });

            return true;
        } catch (\Throwable $e) {
            // Ne jamais propager : journaliser et continuer.
            if (function_exists('report')) {
                report($e);
            }

            return false;
        }
    }

    /**
     * Notifie un nouveau rendez-vous : alerte au marchand + confirmation au client.
     * Tolérant : aucune exception ne remonte.
     */
    public function notifyNewBooking($booking): void
    {
        try {
            $booking->loadMissing(['page', 'service']);
            $page = $booking->page;
            if (! $page) {
                return;
            }

            $when = optional($booking->starts_at)->format('d/m/Y H:i');
            $service = $booking->service?->name;

            // 1) Alerte marchand
            $merchant = self::compose(
                __('Nouveau rendez-vous').' — '.$page->name,
                [
                    __('Référence').' : '.$booking->reference,
                    __('Date').' : '.$when,
                    $service ? __('Prestation').' : '.$service : null,
                    __('Client').' : '.$booking->customer_name,
                    $booking->customer_phone ? __('Téléphone').' : '.$booking->customer_phone : null,
                    $booking->customer_email ? __('E-mail').' : '.$booking->customer_email : null,
                    $booking->note ? __('Note (optionnel)').' : '.$booking->note : null,
                ]
            );
            $this->email($page->email, $merchant['subject'], $merchant['body']);

            // 2) Confirmation client
            $confirm = self::compose(
                __('Rendez-vous enregistré').' — '.$page->name,
                [
                    __('Bonjour').' '.$booking->customer_name.',',
                    '',
                    __('Votre rendez-vous a bien été enregistré.'),
                    __('Référence').' : '.$booking->reference,
                    __('Date').' : '.$when,
                    $service ? __('Prestation').' : '.$service : null,
                    '',
                    __('Propulsé par').' TAGTOA',
                ]
            );
            $this->email($booking->customer_email, $confirm['subject'], $confirm['body']);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
