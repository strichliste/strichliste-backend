<?php

namespace App\Service;

class SplitCalculator
{
    /**
     * Equal shares with the remainder going to the first rows so the total
     * stays exact: 1001/3 → [334, 334, 333].
     *
     * @return int[]
     */
    public function distribute(int $totalCents, int $count): array
    {
        $base = intdiv($totalCents, $count);
        $remainder = $totalCents - $base * $count;
        $amounts = array_fill(0, $count, $base);
        for ($i = 0; $i < $remainder; ++$i) {
            ++$amounts[$i];
        }

        return $amounts;
    }
}
