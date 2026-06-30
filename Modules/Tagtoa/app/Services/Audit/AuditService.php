<?php

namespace Modules\Tagtoa\App\Services\Audit;

use Modules\Tagtoa\App\Models\Audit\AuditLog;
use Modules\Tagtoa\App\Support\Tenant;

/**
 * TAGTOA AUDIT — enregistre les actions sensibles du marchand.
 *
 * Tolérant : journaliser ne doit jamais casser l'action métier (try/catch).
 * La table des libellés (actionLabel) est de la LOGIQUE PURE (testable).
 */
class AuditService
{
    /** action => libellé lisible (clé i18n côté affichage). */
    public const LABELS = [
        'review.approved'   => 'Avis publié',
        'review.rejected'   => 'Avis rejeté',
        'review.deleted'    => 'Avis supprimé',
        'review.replied'    => 'Réponse à un avis',
        'booking.completed' => 'Rendez-vous honoré',
        'booking.cancelled' => 'Rendez-vous annulé',
        'booking.confirmed' => 'Rendez-vous confirmé',
        'order.paid'        => 'Commande encaissée',
        'billing.settled'   => 'Commissions réglées',
        'billing.updated'   => 'Réglages de revenu modifiés',
        'plan.changed'      => 'Forfait modifié',
    ];

    /** Libellé lisible d'une action (repli = action brute). LOGIQUE PURE. */
    public static function actionLabel(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * Journalise une action. Tolérant : aucune exception ne remonte.
     */
    public function log(string $action, $subject = null, ?string $description = null): void
    {
        try {
            $user = Tenant::user();
            $ip = null;
            try {
                $ip = request()?->ip();
            } catch (\Throwable $e) {
                // pas de requête (CLI) : ignore
            }

            AuditLog::create([
                'tenant_id'    => Tenant::id(),
                'user_id'      => $user->id ?? null,
                'user_name'    => $user->name ?? null,
                'action'       => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => is_object($subject) ? ($subject->id ?? null) : null,
                'description'  => $description,
                'ip'           => $ip,
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('report')) {
                report($e);
            }
        }
    }
}
