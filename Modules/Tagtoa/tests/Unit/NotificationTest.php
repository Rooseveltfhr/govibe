<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Notifications\NotificationService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure des notifications (assemblage de message + validation destinataire).
 */
class NotificationTest extends TestCase
{
    public function test_compose_joins_non_empty_lines(): void
    {
        $msg = NotificationService::compose('Sujet', ['Ligne 1', '', null, 'Ligne 2']);

        $this->assertSame('Sujet', $msg['subject']);
        $this->assertSame("Ligne 1\nLigne 2", $msg['body']);
    }

    public function test_compose_trims_subject_and_lines(): void
    {
        $msg = NotificationService::compose('  Bonjour  ', ['  texte   ']);

        $this->assertSame('Bonjour', $msg['subject']);
        $this->assertSame('  texte', $msg['body']); // rtrim seulement (préserve indentation gauche)
    }

    public function test_compose_keeps_intentional_blank_separators_removed(): void
    {
        // Les lignes vides sont retirées : pas de doubles sauts de ligne accidentels.
        $msg = NotificationService::compose('S', ['a', '', '', 'b']);
        $this->assertSame("a\nb", $msg['body']);
    }

    public function test_valid_recipient(): void
    {
        $this->assertTrue(NotificationService::validRecipient('client@tagtoa.com'));
        $this->assertTrue(NotificationService::validRecipient('  spaced@tagtoa.com  '));
    }

    public function test_invalid_recipient(): void
    {
        $this->assertFalse(NotificationService::validRecipient(null));
        $this->assertFalse(NotificationService::validRecipient(''));
        $this->assertFalse(NotificationService::validRecipient('pas-un-email'));
        $this->assertFalse(NotificationService::validRecipient('a@b'));
    }

    public function test_normalize_phone_local_haiti(): void
    {
        // Numéro local haïtien -> préfixe +509
        $this->assertSame('+50934000000', NotificationService::normalizePhone('3400 0000'));
    }

    public function test_normalize_phone_keeps_plus(): void
    {
        $this->assertSame('+15145551234', NotificationService::normalizePhone('+1 (514) 555-1234'));
    }

    public function test_normalize_phone_existing_country_code(): void
    {
        $this->assertSame('+50938001122', NotificationService::normalizePhone('509 3800 1122'));
    }

    public function test_normalize_phone_empty(): void
    {
        $this->assertNull(NotificationService::normalizePhone(null));
        $this->assertNull(NotificationService::normalizePhone('   '));
    }
}
