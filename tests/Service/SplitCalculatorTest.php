<?php

namespace App\Tests\Service;

use App\Service\SplitCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure penny-distribution logic extracted from SplitInvoiceController.
 */
class SplitCalculatorTest extends TestCase
{
    private SplitCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new SplitCalculator();
    }

    /**
     * @return iterable<string, array{int, int, int[]}>
     */
    public static function distributeProvider(): iterable
    {
        // remainder lands on the leading rows, exact total preserved
        yield '1001/3' => [1001, 3, [334, 334, 333]];
        yield '1000/7' => [1000, 7, [143, 143, 143, 143, 143, 143, 142]];
        yield '1/3' => [1, 3, [1, 0, 0]];
        yield '100/3' => [100, 3, [34, 33, 33]];

        // exact divisions: no remainder, every share equal
        yield '900/3' => [900, 3, [300, 300, 300]];
        yield '1000/1' => [1000, 1, [1000]];
        yield '500/5' => [500, 5, [100, 100, 100, 100, 100]];

        // zero total spreads zero everywhere
        yield '0/4' => [0, 4, [0, 0, 0, 0]];
    }

    /**
     * @param int[] $expected
     */
    #[DataProvider('distributeProvider')]
    public function testDistributeExactShares(int $total, int $parts, array $expected): void
    {
        $shares = $this->calculator->distribute($total, $parts);

        self::assertSame($expected, $shares);
        self::assertSame($total, array_sum($shares), 'shares must sum back to the exact total');
        self::assertCount($parts, $shares);
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sumProvider(): iterable
    {
        foreach ([1, 2, 3, 7, 10, 13, 99] as $parts) {
            foreach ([1, 99, 100, 101, 1001, 12345, 99999] as $total) {
                yield "{$total}/{$parts}" => [$total, $parts];
            }
        }
    }

    /**
     * For any total/count the shares sum to the exact total and the remainder is
     * front-loaded (each share is non-increasing across the array).
     */
    #[DataProvider('sumProvider')]
    public function testShareSumAndRemainderOrdering(int $total, int $parts): void
    {
        $shares = $this->calculator->distribute($total, $parts);

        self::assertCount($parts, $shares);
        self::assertSame($total, array_sum($shares));

        // remainder cents go to the first rows: the sequence never increases
        for ($i = 1; $i < $parts; ++$i) {
            self::assertLessThanOrEqual($shares[$i - 1], $shares[$i], 'remainder must be front-loaded');
            // any two shares differ by at most 1 cent
            self::assertLessThanOrEqual(1, $shares[$i - 1] - $shares[$i]);
        }
    }
}
