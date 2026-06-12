<?php

namespace App\Tests\Service;

use App\Service\MoneyParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyParserTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, int|null}>
     */
    public static function provideAmounts(): iterable
    {
        yield 'dot decimal' => ['5.99', 599];
        yield 'comma decimal' => ['5,99', 599];
        yield 'single trailing digit' => ['1,5', 150];
        yield 'bare integer' => ['5', 500];
        yield 'zero' => ['0', 0];
        yield 'negative comma decimal' => ['-5,50', -550];
        yield 'surrounding whitespace' => [' 5.50 ', 550];
        yield 'currency symbol stripped' => ['€ 5,00', 500];
        yield 'german thousands with decimals' => ['1.000,50', 100050];
        yield 'mixed separators, rightmost wins' => ['1.234,56', 123456];
        yield 'repeated separator is grouping' => ['1.234.567', 123456700];
        yield 'four trailing digits read as decimals' => ['1.0000', 100];
        yield 'non-string input' => [5, 500];

        // "1.000" is ambiguous (1000 vs 1.0) — refused rather than booking
        // an amount off by x1000
        yield 'ambiguous three trailing digits' => ['1.000', null];
        yield 'not a number' => ['abc', null];
        yield 'empty string' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('provideAmounts')]
    public function testParseToCents(mixed $input, ?int $expected): void
    {
        $this->assertSame($expected, new MoneyParser()->parseToCents($input));
    }

    public function testMajorToCentsRoundsFloatArtifacts(): void
    {
        // 0.29 * 100 is 28.999… in IEEE 754; round() keeps the cents exact
        $this->assertSame(29, MoneyParser::majorToCents(0.29));
        $this->assertSame(150, MoneyParser::majorToCents(1.50));
    }
}
