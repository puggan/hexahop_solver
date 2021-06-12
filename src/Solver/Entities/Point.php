<?php
/** @noinspection TypoSafeNamingInspection */

namespace Puggan\Solver\Entities;

use JetBrains\PhpStorm\Pure;

/**
 * Class Point
 * @package PHPDoc
 * @property int x
 * @property int y
 * @property int z
 */
class Point
{
    /** @var int */
    public int $x;
    /** @var int */
    public int $y;
    /** @var int */
    public int $z;

    /**
     * Point constructor.
     *
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function __construct(int $x, int $y, int $z)
    {
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
    }

    /**
     * @param Point $point
     *
     * @return Point
     */
    #[Pure] public static function copy(Point $point): Point
    {
        return new self($point->x, $point->y, $point->z);
    }

    public function __toString(): string
    {
        return $this->x . ':' . $this->y . ':' . $this->z;
    }

    /**
     * @param Point[] $points
     * @return Point[]
     */
    public static function unique(array $points): array {
        $uniquePoints = [];
        foreach($points as $point) {
            $uniquePoints[(string) $point] = $point;
        }
        return array_values($uniquePoints);
    }
}
