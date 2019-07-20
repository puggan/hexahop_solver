<?php

	namespace Puggan\Solver\Entities;

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
		public $x;
		/** @var int */
		public $y;
		/** @var int */
		public $z;

		/**
		 * Point constructor.
		 *
		 * @param int $x
		 * @param int $y
		 * @param int $z
		 */
		public function __construct($x, $y, $z)
		{
			$this->x = $x;
			$this->y = $y;
			$this->z = $z;
		}
	}
