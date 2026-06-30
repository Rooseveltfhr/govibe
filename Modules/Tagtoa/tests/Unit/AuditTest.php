<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Audit\AuditService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure du journal d'audit : libellés d'action.
 */
class AuditTest extends TestCase
{
    public function test_known_action_label(): void
    {
        $this->assertSame('Avis publié', AuditService::actionLabel('review.approved'));
        $this->assertSame('Rendez-vous honoré', AuditService::actionLabel('booking.completed'));
        $this->assertSame('Commissions réglées', AuditService::actionLabel('billing.settled'));
    }

    public function test_unknown_action_falls_back_to_raw(): void
    {
        $this->assertSame('foo.bar', AuditService::actionLabel('foo.bar'));
    }

    public function test_all_labels_are_non_empty(): void
    {
        foreach (AuditService::LABELS as $key => $label) {
            $this->assertNotSame('', $label, "Libellé vide pour $key");
        }
    }
}
