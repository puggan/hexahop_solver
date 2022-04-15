<?php

namespace Puggan\Solver\Entities;

use JetBrains\PhpStorm\Pure;

class Projectile extends Point
{
    public const DIR_SW = 4;
    public const DIR_SE = 2;
    public const DIR_J = 6;
    public const DIR_N = 0;
    public const DIR_S = 3;
    public const DIR_NW = 5;
    public const DIR_NE = 1;

    public int $dir;
    public int $length;

    #[Pure]
    public function __construct(int $x, int $y, int $z, int $dir, int $length = 1)
    {
        $this->dir = $dir;
        $this->length = $length;
        parent::__construct($x, $y, $z);
    }

    /**
     * @return false|Projectile
     */
    #[Pure]
    public static function BetweenPoints(
        Point $base,
        Point $target,
        bool|int $override_distance = false
    ): bool|Projectile
    {
        $delta_x = $target->x - $base->x;
        $delta_y = $target->y - $base->y;
        if (!$delta_x) {
            if (!$delta_y) {
                return self::PointDir($base, self::DIR_J, $override_distance ?: 0);
            }
            if ($delta_y < 0) {
                return self::PointDir($base, self::DIR_N, $override_distance ?: -$delta_y);
            }
            return self::PointDir($base, self::DIR_S, $override_distance ?: $delta_y);
        }
        if (!$delta_y) {
            if ($delta_x < 0) {
                return self::PointDir($base, self::DIR_NW, $override_distance ?: -$delta_x);
            }
            return self::PointDir($base, self::DIR_SE, $override_distance ?: $delta_x);
        }
        if ($delta_x + $delta_y !== 0) {
            return false;
        }
        if ($delta_x < 0) {
            return self::PointDir($base, self::DIR_SW, $override_distance ?: -$delta_x);
        }
        return self::PointDir($base, self::DIR_NE, $override_distance ?: $delta_x);
    }

    #[Pure]
    public static function PointDir(
        Point $point,
        int $dir,
        int $length = 1
    ): Projectile
    {
        return new self($point->x, $point->y, $point->z, $dir, $length);
    }

    /**
     * @return false|int
     */
    public function dirDistance(Point $point): bool|int
    {
        $delta_x = $point->x - $this->x;
        $delta_y = $point->y - $this->y;
        switch ($this->dir) {
            case self::DIR_N:
                if ($delta_x) {
                    return false;
                }
                return -$delta_y;

            case self::DIR_NE:
                if ($delta_x + $delta_y !== 0) {
                    return false;
                }
                return $delta_x;

            case self::DIR_SE:
                if ($delta_y) {
                    return false;
                }
                return $delta_x;

            case self::DIR_S:
                if ($delta_x) {
                    return false;
                }
                return $delta_y;

            case self::DIR_SW:
                if ($delta_x + $delta_y !== 0) {
                    return false;
                }
                return $delta_y;

            case self::DIR_NW:
                if ($delta_y) {
                    return false;
                }
                return -$delta_x;

            default:
                if ($delta_x || $delta_y) {
                    return false;
                }
                return 0;
        }
    }

    public function __toString(): string
    {
        return $this->x . ':' . $this->y . ':' . $this->z . ':' . $this->dir . ($this->length !== 1 ? ':' . $this->length : '');
    }
}
