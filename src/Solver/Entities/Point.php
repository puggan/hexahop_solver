<?php
/** @noinspection TypoSafeNamingInspection */

namespace Puggan\Solver\Entities;

use JetBrains\PhpStorm\Pure;

class Point
{
    public int $x;
    public int $y;
    public int $z;

    public function __construct(int $x, int $y, int $z)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    public function __toString(): string
    {
        return $this->x . ':' . $this->y . ':' . $this->z;
    }

    /**
     * @template P of Point
     * @param P[] $points
     * @return array<int, P>
     */
    public static function unique(array $points): array {
        $uniquePoints = [];
        foreach($points as $point) {
            $uniquePoints[(string) $point] = $point;
        }
        return array_values($uniquePoints);
    }
}
