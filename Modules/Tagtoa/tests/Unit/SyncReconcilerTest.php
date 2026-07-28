<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Event\SyncReconciler;
use PHPUnit\Framework\TestCase;

/**
 * Réconciliation offline → serveur (idempotence par client_uuid, conflits).
 */
class SyncReconcilerTest extends TestCase
{
    public function test_partition_filters_seen_uuids(): void
    {
        $records = [
            ['client_uuid' => 'a', 'x' => 1],
            ['client_uuid' => 'b', 'x' => 2],
            ['client_uuid' => 'c', 'x' => 3],
        ];

        $res = SyncReconciler::partition($records, ['b']);

        $this->assertCount(2, $res['fresh']);
        $this->assertCount(1, $res['duplicates']);
        $this->assertSame('b', $res['duplicates'][0]['client_uuid']);
    }

    public function test_partition_dedupes_within_batch(): void
    {
        $records = [
            ['client_uuid' => 'a'],
            ['client_uuid' => 'a'], // répété dans le même lot
            ['client_uuid' => 'd'],
        ];

        $res = SyncReconciler::partition($records, []);

        $this->assertCount(2, $res['fresh']);      // a (1×) + d
        $this->assertCount(1, $res['duplicates']); // le 2e « a »
    }

    public function test_records_without_uuid_pass_through(): void
    {
        $res = SyncReconciler::partition([['x' => 1], ['x' => 2]], ['a']);

        $this->assertCount(2, $res['fresh']);
        $this->assertCount(0, $res['duplicates']);
    }

    public function test_duplicate_entry_detection(): void
    {
        $this->assertTrue(SyncReconciler::isDuplicateEntry(true, 'in'));
        $this->assertFalse(SyncReconciler::isDuplicateEntry(false, 'in'));
        $this->assertFalse(SyncReconciler::isDuplicateEntry(true, 'out')); // sortie ≠ conflit d'entrée
    }
}
