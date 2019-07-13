<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\MapState;

	class HexaHopMap extends MapState
	{
		const DIR_N = 0;
		const DIR_NE = 1;
		const DIR_SE = 2;
		const DIR_S = 3;
		const DIR_SW = 4;
		const DIR_NW = 5;
		const DIR_J = 6;

		const TILE_WATER = 0;
		const TILE_LOW_LAND = 1;
		const TILE_LOW_GREEN = 2;
		const TILE_HIGH_GREEN = 3;
		const TILE_TRAMPOLINE = 4;
		const TILE_ROTATOR = 5;
		const TILE_HIGH_LAND = 6;
		const TILE_LOW_BLUE = 7;
		const TILE_HIGH_BLUE = 8;
		const TILE_LASER = 9;
		const TILE_ICE = 10;
		const TILE_ANTI_ICE = 11;
		const TILE_BUILD = 12;
		const TILE_UNKNOWN_13 = 13;
		const TILE_BOAT = 14;
		const TILE_LOW_ELEVATOR = 15;
		const TILE_HIGH_ELEVATOR = 16;

		const ITEM_ANIT_ICE = 1;
		const ITEM_JUMP = 2;

		const MASK_TILE_TYPE = 0x1F;
		const SHIFT_TILE_ITEM = 5;

		/** @var \PHPDoc\MapInfo $mapinfo */
		private $mapinfo;

		/** @var int x_min */
		private $x_min;
		/** @var int x_max */
		private $x_max;
		/** @var int y_min */
		private $y_min;
		/** @var int y_max */
		private $y_max;

		/** @var int[][] */
		private $tiles = [];

		/** @var int[] */
		private $items = [];

		/** @var \PHPDoc\Player player */
		private $player;

		/** @var int points */
		private $points;

		public function __construct($level_number, $path = NULL)
		{
			$this->points = 0;
			$this->items[self::ITEM_ANIT_ICE] = 0;
			$this->items[self::ITEM_JUMP] = 0;
			$this->mapinfo = self::mapinfo($level_number);
			$this->player = (object) [
				'alive' => TRUE,
				'x' => $this->mapinfo->start_x,
				'y' => $this->mapinfo->start_y,
				'z' => 0,
			];

			$this->parse_map(new MapStream(self::getResourePath('levels/' . $this->mapinfo->file)));

			if($path)
			{
				foreach($path as $move)
				{
					$this->_move($move);
				}
			}
		}

		//<editor-fold desc="Implement MapState">

		/**
		 * Player have won
		 * @return bool
		 */
		public function won() : bool
		{
			if($this->lost())
			{
				return FALSE;
			}

			foreach($this->tiles as $row)
			{
				foreach($row as $tile)
				{
					if(($tile & 0x1e) === self::TILE_LOW_GREEN)
					{
						return FALSE;
					}
				}
			}
			return TRUE;
		}

		/**
		 * Player have lost
		 * @return bool
		 */
		public function lost() : bool
		{
			return !$this->player->alive || $this->points > $this->mapinfo->par;
		}

		/**
		 * The game allows thus moves to be executed
		 * May include moves that make the player lose
		 * @return int[]
		 */
		public function possible_moves() : array
		{
			static $moves;
			if(!$moves)
			{
				$moves = range(0, 6);
			}
			return $moves;
		}

		/**
		 * Make a move in the current state
		 *
		 * @param int $move move/direction to travel
		 */
		protected function _move($move) : void
		{
			$this->player->alive = FALSE;
			// TODO: Implement _move() method.
		}

		/**
		 * @return string uniq state hash, used to detect duplicates
		 */
		public function hash() : string
		{
			return md5(json_encode([$this->player, $this->items, $this->tiles]));
		}

		/**
		 * @param HexaHopMap $a
		 * @param HexaHopMap $b
		 *
		 * @return int -1, 0, 1
		 */
		public static function cmp($a, $b) : int
		{
			return $a->points - $b->points;
		}
		//</editor-fold>

		/**
		 * @param string $filename
		 *
		 * @return string
		 */
		private static function getResoure($filename)
		{
			return file_get_contents(self::getResourePath($filename));
		}

		/**
		 * @param string $filename
		 *
		 * @return string
		 */
		private static function getResourePath($filename)
		{
			return dirname(__DIR__, 3) . '/resources/' . $filename;
		}

		/**
		 * @param $level_number
		 *
		 * @return \PHPDoc\MapInfo
		 */
		private static function mapinfo($level_number)
		{
			static $json;
			if(!$json)
			{
				$json = json_decode(self::getResoure('hexahopmaps.json'), FALSE);
			}
			return $json[$level_number];
		}

		/**
		 * @param MapStream $map_stream
		 */
		private function parse_map($map_stream)
		{
			// Version(1), newline(1), par(4), diff(4)
			$map_stream->goto(10);

			$this->x_min = $map_stream->uint8();
			$this->x_max = $map_stream->uint8();
			$this->y_min = $map_stream->uint8();
			$this->y_max = $map_stream->uint8();

			// Player position: x(4), y(4)
			$map_stream->skip(8);

			foreach(range($this->x_min, $this->x_max) as $x)
			{
				foreach(range($this->y_min, $this->y_max) as $y)
				{
					/* 4 bit item, 4 bit map */
					$this->tiles[$y][$x] = $map_stream->uint8();
				}
			}
		}
	}
