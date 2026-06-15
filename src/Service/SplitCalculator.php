<?php

namespace App\Service;

class SplitCalculator
{
    /**
     * Split $total cents into $parts shares whose sum equals $total exactly.
     *
     * The remainder goes to the first rows so the total stays exact:
     * 1001/3 → [334, 334, 333].
     *
     * @return int[]
     */
    public function distribute(int $total, int $parts): array
    {
        $base = intdiv($total, $parts);
        $remainder = $total - $base * $parts;
        $amounts = array_fill(0, $parts, $base);
        for ($i = 0; $i < $remainder; ++$i) {
            ++$amounts[$i];
        }

        return $amounts;
    }
}
