<?php

namespace App\Service;

// accepts both comma and dot decimals ("5,99" and "5.99" are 599 cents)
class MoneyParser {

    /**
     * @return int|null cents, or null if invalid / not-a-number
     */
    public function parseToCents(mixed $input): ?int {
        if ($input === null || $input === '') {
            return null;
        }

        if (!is_string($input)) {
            $input = (string) $input;
        }
        $clean = trim($input);
        $clean = preg_replace('/[\s\x{00a0}\x{202f}€$£¥]/u', '', $clean);
        if ($clean === '' || $clean === null) {
            return null;
        }
        $dotCount = substr_count($clean, '.');
        $commaCount = substr_count($clean, ',');

        if ($dotCount > 0 && $commaCount > 0) {
            // rightmost separator is the decimal mark, the other is grouping ("1.234,56")
            $decimalAt = max(strrpos($clean, '.'), strrpos($clean, ','));
            $clean = preg_replace('/[.,]/', '', substr($clean, 0, $decimalAt))
                   . '.' . substr($clean, $decimalAt + 1);
        } else {
            $sep = $dotCount > 0 ? '.' : ($commaCount > 0 ? ',' : null);
            if ($sep !== null) {
                if ($dotCount + $commaCount > 1) {
                    // repeated separator can only be grouping ("1.234.567")
                    $clean = str_replace($sep, '', $clean);
                } else {
                    $digitsAfter = strlen($clean) - strrpos($clean, $sep) - 1;
                    if ($digitsAfter === 3) {
                        // "1.000" is ambiguous (1000 vs 1.0) — refuse rather than book an amount off by x1000
                        return null;
                    }
                    // 1, 2 or 4+ trailing digits: the separator is the decimal mark
                    $clean = str_replace($sep, '.', $clean);
                }
            }
        }

        if (!is_numeric($clean)) {
            return null;
        }
        return self::majorToCents((float) $clean);
    }

    // round() dodges 1.50 * 100 = 149 float artifacts
    public static function majorToCents(float $major): int {
        return (int) round($major * 100);
    }
}
