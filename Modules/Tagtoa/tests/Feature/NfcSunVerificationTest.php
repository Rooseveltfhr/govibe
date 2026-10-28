<?php

namespace Modules\Tagtoa\Tests\Feature;

/*
|--------------------------------------------------------------------------
| TAGTOA EVENT — groundwork NTAG424 (SUN) : wiring opt-in, argent réel
|--------------------------------------------------------------------------
| Signalé par l'audit sécurité + docs/NFC_SECURITY.md : le wallet closed-loop
| authentifiait un tap par UID SEUL — clonable. Le CMAC/anti-rejeu existait
| (AesCmac/Ntag424/SunVerifier) mais n'était câblé nulle part.
|
| Ce test fige DEUX garanties à la fois :
|   1. Un tag ORDINAIRE (sun_required=false, la quasi-totalité aujourd'hui)
|      garde EXACTEMENT son comportement UID-seul — 0 régression.
|   2. Un tag marqué sun_required=true (une vraie puce NTAG424 provisionnée)
|      ne se résout JAMAIS sans un CMAC valide + compteur frais — y compris
|      si la clé globale est absente : pas de repli silencieux sur l'UID.
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Tagtoa\App\Actions\Event\Wallet\IssueNfcTag;
use Modules\Tagtoa\App\Actions\Event\Wallet\OpenEventWalletAccounts;
use Modules\Tagtoa\App\Actions\Event\Wallet\ResolveNfcTag;
use Modules\Tagtoa\App\Models\Event\Event;
use Modules\Tagtoa\App\Models\Event\NfcTag;
use Modules\Tagtoa\App\Models\Event\Ticket;
use Modules\Tagtoa\App\Services\Event\CheckinService;
use Modules\Tagtoa\App\Support\Nfc\Ntag424;
use Modules\Tagtoa\Tests\TestCase;

class NfcSunVerificationTest extends TestCase
{
    use RefreshDatabase;

    // Mêmes vecteurs que SunVerifierTest (pure), pour rester cohérent.
    private const KEY = '00112233445566778899aabbccddeeff';
    private const UID = '04a2b3c4d5e680';

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'title' => 'Sun Test', 'alias' => 'sun-test', 'currency' => 'HTG', 'is_published' => true,
        ]);
        app(OpenEventWalletAccounts::class)->handle($this->event);
    }

    private function enableNtag424(): void
    {
        config(['tagtoa.nfc.ntag424.enabled' => true, 'tagtoa.nfc.ntag424.key' => self::KEY]);
    }

    /** Calcule le CMAC tronqué (hex) qu'un vrai tag produirait pour ce compteur. */
    private function cmacHex(string $ctrHex): string
    {
        return bin2hex(Ntag424::expectedMac(hex2bin(self::KEY), hex2bin(self::UID), hex2bin($ctrHex)));
    }

    public function test_an_ordinary_tag_still_resolves_by_uid_alone(): void
    {
        app(IssueNfcTag::class)->handle($this->event, 'UID-PLAIN-001', ['label' => 'Client']);

        $resolved = app(ResolveNfcTag::class)->handle($this->event, 'UID-PLAIN-001');

        $this->assertNotNull($resolved, 'un tag non protégé doit continuer à se résoudre sans crypto.');
    }

    public function test_an_ordinary_tag_ignores_garbage_ctr_and_cmac(): void
    {
        app(IssueNfcTag::class)->handle($this->event, 'UID-PLAIN-002', ['label' => 'Client']);

        $resolved = app(ResolveNfcTag::class)->handle($this->event, 'UID-PLAIN-002', 'not-hex', 'also-not-hex');

        $this->assertNotNull($resolved, 'un tag non protégé ignore ctr/cmac — ils ne lui sont pas destinés.');
    }

    public function test_a_protected_tag_refuses_resolution_without_a_cmac(): void
    {
        $this->enableNtag424();
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['label' => 'VIP', 'sun_required' => true]);

        $this->assertNull(app(ResolveNfcTag::class)->handle($this->event, self::UID));
    }

    public function test_a_protected_tag_resolves_with_a_valid_fresh_tap(): void
    {
        $this->enableNtag424();
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['label' => 'VIP', 'sun_required' => true]);

        $resolved = app(ResolveNfcTag::class)->handle($this->event, self::UID, '010000', $this->cmacHex('010000'));

        $this->assertNotNull($resolved);
        $this->assertSame(1, NfcTag::where('uid_hash', NfcTag::hashUid(self::UID))->value('last_counter'),
            'le compteur accepté doit être retenu, pour l\'anti-rejeu du prochain tap.');
    }

    public function test_a_protected_tag_rejects_a_replayed_counter(): void
    {
        $this->enableNtag424();
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['label' => 'VIP', 'sun_required' => true]);

        app(ResolveNfcTag::class)->handle($this->event, self::UID, '010000', $this->cmacHex('010000'));
        $replay = app(ResolveNfcTag::class)->handle($this->event, self::UID, '010000', $this->cmacHex('010000'));

        $this->assertNull($replay, 'le même compteur une 2e fois == une capture rejouée.');
    }

    public function test_a_protected_tag_rejects_a_forged_cmac(): void
    {
        $this->enableNtag424();
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['label' => 'VIP', 'sun_required' => true]);

        $resolved = app(ResolveNfcTag::class)->handle($this->event, self::UID, '010000', str_repeat('00', 8));

        $this->assertNull($resolved);
    }

    public function test_a_protected_tag_never_falls_back_to_uid_alone_when_no_key_is_configured(): void
    {
        // La clé globale n'est PAS configurée ici. Un tag "protégé" doit
        // refuser — jamais retomber sur l'UID seul, sinon couper la clé par
        // erreur ferait croire à une carte protégée qu'elle le reste.
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['label' => 'VIP', 'sun_required' => true]);

        $resolved = app(ResolveNfcTag::class)->handle($this->event, self::UID, '010000', $this->cmacHex('010000'));

        $this->assertNull($resolved);
    }

    public function test_checkin_by_tap_enforces_the_same_guarantee(): void
    {
        $this->enableNtag424();
        $ticket = Ticket::create(['event_id' => $this->event->id, 'code' => 'TICKET-SUN-1', 'status' => Ticket::STATUS_VALID]);
        app(IssueNfcTag::class)->handle($this->event, self::UID, ['sun_required' => true, 'ticket_id' => $ticket->id]);

        $checkin = app(CheckinService::class);

        $this->assertNull($checkin->resolveNfcCode($this->event, self::UID), 'sans CMAC, un tag protégé ne donne pas le code du billet.');
        $this->assertSame('TICKET-SUN-1', $checkin->resolveNfcCode($this->event, self::UID, '010000', $this->cmacHex('010000')));
    }
}
