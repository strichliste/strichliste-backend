<?php

namespace App\Tests\Service;

use App\Service\SplitCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SplitCalculatorTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, int[]}>
     */
    public static function provideSplits(): iterable
    {
        yield 'even split' => [3000, 3, [1000, 1000, 1000]];
        yield 'remainder goes to the first rows' => [1001, 3, [334, 334, 333]];
        yield 'two cents short' => [1000, 3, [334, 333, 333]];
        yield 'single participant' => [999, 1, [999]];
        yield 'more rows than cents' => [2, 3, [1, 1, 0]];
        yield 'zero total' => [0, 3, [0, 0, 0]];
    }

    /**
     * @param int[] $expected
     */
    #[DataProvider('provideSplits')]
    public function testDistribute(int $totalCents, int $count, array $expected): void
    {
        $shares = new SplitCalculator()->distribute($totalCents, $count);

        $this->assertSame($expected, $shares);
        // the invariant the rounding must never break
        $this->assertSame($totalCents, array_sum($shares));
    }
}
