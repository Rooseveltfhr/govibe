<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Event\TicketImportService;
use PHPUnit\Framework\TestCase;

/**
 * Import de billets pré-imprimés (hors système) — parsing CSV (logique pure).
 */
class TicketImportTest extends TestCase
{
    public function test_parses_code_and_serial(): void
    {
        $res = TicketImportService::parseCsv("696bb99d76e28407,25H165407\nabc123,ZZ9\n");

        $this->assertSame(0, $res['skipped']);
        $this->assertCount(2, $res['rows']);
        $this->assertSame(['code' => '696bb99d76e28407', 'serial' => '25H165407'], $res['rows'][0]);
        $this->assertSame(['code' => 'abc123', 'serial' => 'ZZ9'], $res['rows'][1]);
    }

    public function test_serial_is_optional(): void
    {
        $res = TicketImportService::parseCsv("onlycode1\nonlycode2\n");

        $this->assertCount(2, $res['rows']);
        $this->assertNull($res['rows'][0]['serial']);
    }

    public function test_skips_header_row(): void
    {
        $res = TicketImportService::parseCsv("code,serial\n696bb99d76e28407,25H165407\n");

        $this->assertCount(1, $res['rows']);
        $this->assertSame('696bb99d76e28407', $res['rows'][0]['code']);
    }

    public function test_skips_empty_lines_and_missing_codes(): void
    {
        $res = TicketImportService::parseCsv("valid1\n\n,ORPHAN-SERIAL\n   \nvalid2\n");

        $this->assertCount(2, $res['rows']);
        $this->assertSame(1, $res['skipped']); // ",ORPHAN-SERIAL" (code vide) — les lignes blanches sont ignorées silencieusement
    }

    public function test_dedupes_within_file(): void
    {
        $res = TicketImportService::parseCsv("DUP,S1\nDUP,S2\nUNIQUE,S3\n");

        $this->assertCount(2, $res['rows']);
        $this->assertSame(1, $res['skipped']);
        $this->assertSame('DUP', $res['rows'][0]['code']);
        $this->assertSame('S1', $res['rows'][0]['serial']); // premier gagne
    }
}
