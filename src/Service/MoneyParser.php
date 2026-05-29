<?php

namespace App\Service;

/**
 * Parses a user-supplied money string into integer cents.
 *
 * Handles locale-dependent decimal separators (comma vs dot) so that a German
 * operator typing `5,99` and an English one typing `5.99` both produce 599c.
 * Invalid / unparseable input returns null — callers must handle this.
 */
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
            // Both separators appear: the rightmost is the decimal mark, the
            // other is thousands grouping (e.g. "1.234,56" or "1,234.56").
            $decimalAt = max(strrpos($clean, '.'), strrpos($clean, ','));
            $clean = preg_replace('/[.,]/', '', substr($clean, 0, $decimalAt))
                   . '.' . substr($clean, $decimalAt + 1);
        } else {
            $sep = $dotCount > 0 ? '.' : ($commaCount > 0 ? ',' : null);
            if ($sep !== null) {
                if ($dotCount + $commaCount > 1) {
                    // Repeated single separator can only be thousands grouping
                    // (e.g. "1.234.567" / "1,234,567") — strip it entirely.
                    $clean = str_replace($sep, '', $clean);
                } else {
                    $digitsAfter = strlen($clean) - strrpos($clean, $sep) - 1;
                    if ($digitsAfter === 3) {
                        // Ambiguous: "1.000" / "1,000" could mean 1000 (grouping)
                        // or 1.000 (decimal). Refuse rather than silently booking
                        // an amount that is off by a factor of 1000.
                        return null;
                    }
                    // 1, 2 or 4+ trailing digits => the separator is the decimal mark.
                    $clean = str_replace($sep, '.', $clean);
                }
            }
        }

        if (!is_numeric($clean)) {
            return null;
        }
        return self::majorToCents((float) $clean);
    }

    /**
     * Converts a major-unit float (already parsed, e.g. from Symfony's MoneyType)
     * to integer cents. Uses round() to dodge 1.50 * 100 = 149 float artifacts.
     */
    public static function majorToCents(float $major): int {
        return (int) round($major * 100);
    }
}
