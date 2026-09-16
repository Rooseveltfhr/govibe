<?php

namespace Modules\Tagtoa\App\Services\Notifications;

use Modules\Tagtoa\App\Models\Loyalty\Transaction;

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

    /** Normalise un numéro en E.164 (défaut Haïti +509). PUR. */
    public static function normalizePhone(?string $phone, string $defaultCc = '509'): ?string
    {
        if ($phone === null) {
            return null;
        }
        $trim = trim($phone);
        $hasPlus = str_starts_with($trim, '+');
        $digits = preg_replace('/\D+/', '', $trim) ?? '';
        if ($digits === '') {
            return null;
        }
        if ($hasPlus) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, $defaultCc)) {
            return '+'.$digits;
        }
        // Numéro local (<= 8 chiffres) → préfixe pays par défaut.
        if (strlen($digits) <= 8) {
            return '+'.$defaultCc.$digits;
        }

        return '+'.$digits;
    }

    /** WhatsApp activé (flag + credentials présents) ? */
    public function whatsappEnabled(): bool
    {
        $cfg = (array) config('tagtoa.notifications.whatsapp', []);

        return (bool) ($cfg['enabled'] ?? false)
            && ! empty($cfg['sid']) && ! empty($cfg['token']) && ! empty($cfg['from']);
    }

    /** Envoi WhatsApp tolérant via Twilio. No-op si désactivé/non configuré. */
    public function whatsapp(?string $to, string $body): bool
    {
        if (! $this->whatsappEnabled()) {
            return false;
        }
        $to = self::normalizePhone($to);
        if (! $to) {
            return false;
        }
        $cfg = (array) config('tagtoa.notifications.whatsapp', []);

        try {
            \Illuminate\Support\Facades\Http::withBasicAuth($cfg['sid'], $cfg['token'])
                ->asForm()
                ->connectTimeout(5)->timeout(10)
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.$cfg['sid'].'/Messages.json', [
                    'From' => 'whatsapp:'.$cfg['from'],
                    'To'   => 'whatsapp:'.$to,
                    'Body' => $body,
                ]);

            return true;
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }

            return false;
        }
    }

    /** Dispatch découplé (queue) d'une notification multi-canal, tolérant. */
    public function push(array $payload): void
    {
        try {
            \Modules\Tagtoa\App\Jobs\SendNotification::dispatch($payload);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }

    /**
     * Compose le message d'un mouvement de carte de fidélité, à partir de
     * FAITS déjà calculés — jamais un objet Eloquent ici, même principe que
     * CustomerSegment::classify() : pur, testable sans base de données.
     *
     * `null` pour un type de mouvement inconnu (aucun cas prévu) : appelant
     * responsable de ne rien envoyer dans ce cas.
     *
     * @param  array{
     *     type: string, reward: bool, cardholder_name: string, program_name: string,
     *     currency: string, amount: float, points_delta: int, balance: float,
     *     points: int, reward_note: ?string,
     * }  $faits
     * @return array{subject:string,body:string}|null
     */
    public static function loyaltyMovementMessage(array $faits): ?array
    {
        $devise = $faits['currency'] ?? '';
        $solde = __('Solde').' : '.number_format((float) ($faits['balance'] ?? 0), 2).' '.$devise;
        $points = __('Points').' : '.number_format((int) ($faits['points'] ?? 0));
        $salutation = __('Bonjour').' '.($faits['cardholder_name'] ?? '').',';
        $programme = ' — '.($faits['program_name'] ?? '');

        return match (true) {
            $faits['type'] === Transaction::TYPE_TOP_UP => self::compose(
                __('Carte rechargée').$programme,
                [
                    $salutation,
                    '',
                    __('Votre carte a été rechargée de :montant.', ['montant' => number_format((float) $faits['amount'], 2).' '.$devise]),
                    $solde,
                    $points,
                ]
            ),
            $faits['type'] === Transaction::TYPE_EARN => self::compose(
                __('Points gagnés').$programme,
                [
                    $salutation,
                    '',
                    __('Vous avez gagné :n points.', ['n' => number_format((int) $faits['points_delta'])]),
                    $points,
                ]
            ),
            $faits['type'] === Transaction::TYPE_REDEEM && ! empty($faits['reward']) => self::compose(
                __('Récompense échangée').$programme,
                [
                    $salutation,
                    '',
                    $faits['reward_note'] ?: __('Votre récompense a été appliquée.'),
                    $solde,
                    $points,
                ]
            ),
            $faits['type'] === Transaction::TYPE_REDEEM => self::compose(
                __('Paiement effectué').$programme,
                [
                    $salutation,
                    '',
                    __('Un paiement de :montant a été débité de votre carte.', ['montant' => number_format((float) $faits['amount'], 2).' '.$devise]),
                    $solde,
                    $points,
                ]
            ),
            default => null,
        };
    }

    /**
     * Notifie un mouvement de carte de fidélité (recharge, points gagnés,
     * paiement, récompense) au TITULAIRE de la carte — jamais au marchand :
     * ce sont des mouvements courants, pas des alertes à traiter.
     *
     * Reçoit la carte ET la transaction déjà enregistrées (jamais un montant
     * recalculé ici) — le message affiché doit être un miroir exact de ce qui
     * a été écrit dans le ledger, pas une seconde source de vérité.
     *
     * Tolérant : aucune exception ne remonte.
     */
    public function notifyLoyaltyMovement($card, $transaction): void
    {
        try {
            $card->loadMissing('program');
            $program = $card->program;
            if (! $program) {
                return;
            }

            $message = self::loyaltyMovementMessage([
                'type' => $transaction->type,
                'reward' => (bool) $transaction->reward_id,
                'cardholder_name' => (string) $card->cardholder_name,
                'program_name' => (string) $program->name,
                'currency' => (string) ($program->currency ?: ''),
                'amount' => (float) $transaction->amount,
                'points_delta' => (int) $transaction->points_delta,
                'balance' => (float) $card->balance,
                'points' => (int) $card->points,
                'reward_note' => $transaction->note,
            ]);

            if ($message === null) {
                return;
            }

            $this->email($card->cardholder_email, $message['subject'], $message['body']);
            $this->whatsapp($card->cardholder_phone, $message['subject']."\n".$message['body']);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
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

            // 3) Confirmation client par WhatsApp (si numéro + canal activé).
            $this->whatsapp($booking->customer_phone, $confirm['subject']."\n".$confirm['body']);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
