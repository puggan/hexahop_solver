<?php

	namespace Puggan\Solver\Entities;

	use Puggan\Solver\HexaHop\HexaHopMap;

	/**
	 * Class Projectile
	 * @package PHPDoc
	 * @property int dir
	 */
	class Projectile extends Point
	{
		/** @var int */
		public $dir;

		/** @var int */
		public $length;

		/**
		 * Projectile constructor.
		 *
		 * @param int $x
		 * @param int $y
		 * @param int $z
		 * @param int $dir
		 * @param int $length
		 */
		public function __construct($x, $y, $z, $dir, $length = 1)
		{
			$this->dir = $dir;
			$this->length = $length;
			parent::__construct($x, $y, $z);
		}

		/**
		 * @param Point $point
		 * @param int $dir
		 * @param int $length
		 *
		 * @return Projectile
		 */
		public static function PointDir($point, $dir, $length = 1) : Projectile
		{
			return new self($point->x, $point->y, $point->z, $dir, $length);
		}

		/**
		 * @param Point $point
		 *
		 * @return false|int
		 */
		public function dirDistance($point)
		{
			$delta_x = $point->x - $this->x;
			$delta_y = $point->y - $this->y;
			switch($this->dir)
			{
				case HexaHopMap::DIR_N:
					if($delta_x)
					{
						return FALSE;
					}
					return -$delta_y;

				case HexaHopMap::DIR_NE:
					if($delta_x + $delta_y !== 0) return false;
					return $delta_x;

				case HexaHopMap::DIR_SE:
					if($delta_y)
					{
						return FALSE;
					}
					return $delta_x;

				case HexaHopMap::DIR_S:
					if($delta_x)
					{
						return FALSE;
					}
					return $delta_y;

				case HexaHopMap::DIR_SW:
					if($delta_x + $delta_y !== 0) return false;
					return $delta_y;

				case HexaHopMap::DIR_NW:
					if($delta_y)
					{
						return FALSE;
					}
					return -$delta_x;

				default:
					if($delta_x || $delta_y)
					{
						return FALSE;
					}
					return 0;
			}
		}

		/**
		 * @param Point base
		 * @param Point target
		 * @param false|int $override_distance
		 *
		 * @return false|Projectile
		 */
		public static function BetweenPoints($base, $target, $override_distance = false)
		{
			$delta_x = $target->x - $base->x;
			$delta_y = $target->y - $base->y;
			if(!$delta_x)
			{
				if(!$delta_y)
				{
					return self::PointDir($base, HexaHopMap::DIR_J, $override_distance ?: 0);
				}
				if($delta_y < 0)
				{
					return self::PointDir($base, HexaHopMap::DIR_N, $override_distance ?: -$delta_y);
				}
				return self::PointDir($base, HexaHopMap::DIR_S, $override_distance ?: $delta_y);
			}
			if(!$delta_y)
			{
				if($delta_x < 0)
				{
					return self::PointDir($base, HexaHopMap::DIR_NW, $override_distance ?: -$delta_x);
				}
				return self::PointDir($base, HexaHopMap::DIR_SE, $override_distance ?: $delta_x);
			}
			if($delta_x + $delta_y !== 0) return false;
			if($delta_x < 0)
			{
				return self::PointDir($base, HexaHopMap::DIR_SW, $override_distance ?: -$delta_x);
			}
			return self::PointDir($base, HexaHopMap::DIR_NE, $override_distance ?: $delta_x);
		}

		public function __toString() : string
		{
			return $this->x . ':' . $this->y . ':' . $this->z . ':' . $this->dir . ($this->length !== 1 ? ':' . $this->length: '');
		}

	}
