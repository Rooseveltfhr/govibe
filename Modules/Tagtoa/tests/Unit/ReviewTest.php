<?php

namespace Modules\Tagtoa\Tests\Unit;

use Modules\Tagtoa\App\Services\Review\ReviewService;
use PHPUnit\Framework\TestCase;

/**
 * Logique pure des avis : moyenne, bornage de note, répartition.
 */
class ReviewTest extends TestCase
{
    public function test_average_basic(): void
    {
        $this->assertSame(4.0, ReviewService::average([5, 3, 4]));
    }

    public function test_average_rounds_one_decimal(): void
    {
        // (5+4+4)/3 = 4.333… → 4.3
        $this->assertSame(4.3, ReviewService::average([5, 4, 4]));
    }

    public function test_average_ignores_out_of_range(): void
    {
        // 0, 6 et 9 ignorés ; reste [5,5] => 5.0
        $this->assertSame(5.0, ReviewService::average([5, 5, 0, 6, 9]));
    }

    public function test_average_empty_is_zero(): void
    {
        $this->assertSame(0.0, ReviewService::average([]));
    }

    public function test_clamp_rating(): void
    {
        $this->assertSame(1, ReviewService::clampRating(0));
        $this->assertSame(1, ReviewService::clampRating(-3));
        $this->assertSame(5, ReviewService::clampRating(6));
        $this->assertSame(3, ReviewService::clampRating(3));
        $this->assertSame(4, ReviewService::clampRating('4'));
    }

    public function test_distribution(): void
    {
        $d = ReviewService::distribution([5, 5, 4, 1, 7]);
        $this->assertSame(2, $d[5]);
        $this->assertSame(1, $d[4]);
        $this->assertSame(0, $d[3]);
        $this->assertSame(0, $d[2]);
        $this->assertSame(1, $d[1]);
    }
}
