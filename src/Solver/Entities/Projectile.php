<?php

	namespace Puggan\Solver\Entities;

	/**
	 * Class Projectile
	 * @package PHPDoc
	 * @property int dir
	 */
	class Projectile extends Point
	{
		/** @var int */
		public $dir;

		/**
		 * Projectile constructor.
		 *
		 * @param int $x
		 * @param int $y
		 * @param int $z
		 * @param int $dir
		 */
		public function __construct($x, $y, $z, $dir)
		{
			$this->dir = $dir;
			parent::__construct($x, $y, $z);
		}

		/**
		 * @param Point $point
		 * @param int $dir
		 *
		 * @return Projectile
		 */
		public static function PointDir($point, $dir) : Projectile
		{
			return new self($point->x, $point->y, $point->z, $dir);
		}
	}
