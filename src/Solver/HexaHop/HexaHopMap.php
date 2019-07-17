<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\MapState;

	class HexaHopMap extends MapState implements \JsonSerializable
	{

		private const DIR_N = 0;
		private const DIR_NE = 1;
		private const DIR_SE = 2;
		private const DIR_S = 3;
		private const DIR_SW = 4;
		private const DIR_NW = 5;
		private const DIR_J = 6;

		private const TILE_WATER = 0;
		private const TILE_LOW_LAND = 1;
		private const TILE_LOW_GREEN = 2;
		private const TILE_HIGH_GREEN = 3;
		private const TILE_TRAMPOLINE = 4;
		private const TILE_ROTATOR = 5;
		private const TILE_HIGH_LAND = 6;
		private const TILE_LOW_BLUE = 7;
		private const TILE_HIGH_BLUE = 8;
		private const TILE_LASER = 9;
		private const TILE_ICE = 10;
		private const TILE_ANTI_ICE = 11;
		private const TILE_BUILD = 12;
		//private const TILE_UNKNOWN_13 = 13;
		private const TILE_BOAT = 14;
		private const TILE_LOW_ELEVATOR = 15;
		private const TILE_HIGH_ELEVATOR = 16;

		private const ITEM_ANIT_ICE = 1;
		private const ITEM_JUMP = 2;

		private const MASK_TILE_TYPE = 0x1F;
		private const MASK_ITEM_TYPE = 0xE0;
		private const SHIFT_TILE_ITEM = 5;

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
			$this->mapinfo = self::mapinfo($level_number);
			if(!$this->mapinfo)
			{
				throw new \RuntimeException('invalid level_number. ' . $level_number);
			}

			$this->points = 0;
			$this->items[self::ITEM_ANIT_ICE] = 0;
			$this->items[self::ITEM_JUMP] = 0;
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
		 * @param int $direction move/direction to travel
		 */
		protected function _move($direction) : void
		{
			if($direction === self::DIR_J)
			{
				if($this->items[self::ITEM_JUMP] < 1)
				{
					$this->player->alive = FALSE;

					return;
				}
				$this->items[self::ITEM_JUMP]--;
			}
			$next_point = $this->next_point($this->player, $direction);
			$old_tile = $this->move_outof($this->player);
			$this->points++;
			$this->move_into($next_point, $direction, $old_tile);
		}

		/**
		 * @return string uniq state hash, used to detect duplicates
		 */
		public function hash() : string
		{
			return md5(json_encode([$this->player, $this->items, $this->tiles]));
		}

		/**
		 * is the current state better that this other state?
		 *
		 * @param HexaHopMap $other
		 *
		 * @return bool
		 */
		public function better($other) : bool
		{
			return $this->points < $other->points;
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

		/**
		 * @param \PhpDoc\Point $point
		 * @param int $direction
		 */
		private function move_into($point, $direction, $old_tile)
		{
			$this->player->x = $point->x;
			$this->player->y = $point->y;

			//<editor-fold desc="Out of bounds">
			if(empty($this->tiles[$point->y][$point->x]))
			{
				$this->player->alive = FALSE;
				$this->player->z = 0;

				return;
			}
			//</editor-fold>

			$tile_and_item = $this->tiles[$point->y][$point->x];
			$tile = $tile_and_item & self::MASK_TILE_TYPE;

			//<editor-fold desc="Item">
			$item = $tile_and_item >> self::SHIFT_TILE_ITEM;
			if($item)
			{
				$this->items[$item]++;
				$this->tiles[$point->y][$point->x] = $tile;
			}
			//</editor-fold>

			if($point->z < 1)
			{
				switch($tile)
				{
					case self::TILE_HIGH_GREEN:
					case self::TILE_HIGH_LAND:
					case self::TILE_HIGH_BLUE:
					case self::TILE_HIGH_ELEVATOR:
						$this->player->alive = FALSE;

						return;
				}
			}

			switch($tile)
			{
				case self::TILE_WATER:
					$this->player->alive = FALSE;
					$this->player->z = 0;

					return;

				case self::TILE_LOW_ELEVATOR:
					$this->tiles[$point->y][$point->x] = self::TILE_HIGH_ELEVATOR;
					break;

				case self::TILE_HIGH_ELEVATOR:
					$this->tiles[$point->y][$point->x] = self::TILE_LOW_ELEVATOR;
					break;

				case self::TILE_TRAMPOLINE:
					$this->wall_test($old_tile);
					$goal_point = $this->next_point($point, $direction, 2);
					// if jumping from a high place, skip hight tests
					if($this->player->z <= 0)
					{
						$mid_point = $this->next_point($point, $direction);
						if($this->high_tile($mid_point))
						{
							break;
						}
						if($this->high_tile($goal_point))
						{
							return $this->move_into($mid_point, $direction, $old_tile);
						}
					}

					return $this->move_into($goal_point, $direction, $tile);

				case self::TILE_ROTATOR:
					throw new \RuntimeException('Tile ROTATOR not implemented');
					break;

				case self::TILE_LASER:
					throw new \RuntimeException('Tile LASER not implemented');
					break;

				case self::TILE_ICE:
					throw new \RuntimeException('Tile ICE not implemented');
					break;

				case self::TILE_BUILD:
					throw new \RuntimeException('Tile BUILD not implemented');
					break;

				case self::TILE_BOAT:
					$end_point = $point;
					foreach(range(1, 20) as $steps)
					{
						$test_point = $this->next_point($point, $direction, $steps);
						$test_tile = $this->tiles[$test_point->y][$test_point->x] ?? -1;
						if($test_tile > 0)
						{
							$test_tile &= self::MASK_TILE_TYPE;
						}
						if($test_tile > 0)
						{
							break;
						}
						$end_point = $test_point;
						if($test_tile < 0)
						{
							$this->player->alive = FALSE;
							break;
						}
					}
					if($end_point !== $point)
					{
						$this->tiles[$point->y][$point->x] -= self::TILE_BOAT;
						if(isset($this->tiles[$end_point->y][$end_point->x]))
						{
							$this->tiles[$end_point->y][$end_point->x] += self::TILE_BOAT;
						}
						$this->player->x = $end_point->x;
						$this->player->y = $end_point->y;
					}

					break;

				/*
				case self::TILE_ANTI_ICE:
				case self::TILE_LOW_LAND:
				case self::TILE_LOW_GREEN:
				case self::TILE_LOW_BLUE:
				case self::TILE_HIGH_LAND:
				case self::TILE_HIGH_GREEN:
				case self::TILE_HIGH_BLUE:
					 // no effect on enter
					 break;
				*/
			}

			//<editor-fold desc="set height (z)">
			switch($tile)
			{
				case self::TILE_WATER:
				case self::TILE_LOW_LAND:
				case self::TILE_LOW_GREEN:
				case self::TILE_TRAMPOLINE:
				case self::TILE_ROTATOR:
				case self::TILE_LOW_BLUE:
				case self::TILE_LASER:
				case self::TILE_ICE:
				case self::TILE_ANTI_ICE:
				case self::TILE_BUILD:
				case self::TILE_LOW_ELEVATOR:
				case self::TILE_BOAT:
					$this->player->z = 0;
					break;

				case self::TILE_HIGH_GREEN:
				case self::TILE_HIGH_LAND:
				case self::TILE_HIGH_BLUE:
				case self::TILE_HIGH_ELEVATOR:
					$this->player->z = 1;
					break;
			}
			//</editor-fold>

			$this->wall_test($old_tile);
		}

		/**
		 * @param \PhpDoc\Point $point
		 */
		private function move_outof($point)
		{
			$tile = $this->tiles[$point->y][$point->x];
			switch($tile & self::MASK_TILE_TYPE)
			{
				case self::TILE_LOW_GREEN:
				case self::TILE_HIGH_GREEN:
					$this->tiles[$point->y][$point->x] = self::TILE_WATER;
					break;

				case self::TILE_LOW_BLUE:
					$this->tiles[$point->y][$point->x] = self::TILE_LOW_GREEN;
					$this->points += 10;
					break;

				case self::TILE_HIGH_BLUE:
					$this->tiles[$point->y][$point->x] = self::TILE_HIGH_GREEN;
					$this->points += 10;
					break;

				case self::TILE_ANTI_ICE:
					$this->tiles[$point->y][$point->x] = self::TILE_LOW_BLUE;
					break;
			}

			return $tile;
		}

		/**
		 * @param \PhpDoc\Point $current
		 * @param int $direction
		 * @param int $steps
		 *
		 * @return \PhpDoc\Point
		 */
		private function next_point($current, $direction, $steps = 1)
		{
			/** @var \PhpDoc\Point $new_point */
			$new_point = clone $current;
			switch($direction)
			{
				case self::DIR_N:
					$new_point->y -= $steps;

					return $new_point;

				case self::DIR_NE:
					$new_point->x += $steps;
					$new_point->y -= $steps;

					return $new_point;

				case self::DIR_SE:
					$new_point->x += $steps;

					return $new_point;

				case self::DIR_S:
					$new_point->y += $steps;

					return $new_point;

				case self::DIR_SW:
					$new_point->x -= $steps;
					$new_point->y += $steps;

					return $new_point;

				case self::DIR_NW:
					$new_point->x -= $steps;

					return $new_point;

				case self::DIR_J:
					return $new_point;
			}
			throw new \RuntimeException('Bad direction: ' . $direction);
		}

		/**
		 * @param $current
		 * @param int $steps
		 *
		 * @return \PhpDoc\Point[]
		 */
		private function next_points($current, $steps = 1)
		{
			$points = [];
			foreach(range(0,5) as $direction)
			{

				/** @var \PhpDoc\Point $new_point */
				$new_point = clone $current;
				switch($direction)
				{
					case self::DIR_N:
						$new_point->y -= $steps;
						break;

					case self::DIR_NE:
						$new_point->x += $steps;
						$new_point->y -= $steps;
						break;

					case self::DIR_SE:
						$new_point->x += $steps;
						break;

					case self::DIR_S:
						$new_point->y += $steps;
						break;

					case self::DIR_SW:
						$new_point->x -= $steps;
						$new_point->y += $steps;
						break;

					case self::DIR_NW:
						$new_point->x -= $steps;
						break;
				}
				$points[] = $new_point;
			}
			return $points;
		}

		public function jsonSerialize()
		{
			return [
				'mapinfo' => $this->mapinfo,
				'x_min' => $this->x_min,
				'x_max' => $this->x_max,
				'y_min' => $this->y_min,
				'y_max' => $this->y_max,
				'tiles' => $this->tiles,
				'items' => $this->items,
				'player' => $this->player,
				'points' => $this->points,
			];
		}

		public function __clone()
		{
			$this->player = clone $this->player;
		}

		public function map_info($json_option)
		{
			return json_encode($this->mapinfo, $json_option);
		}

		public function print_path($path) : string
		{
			$dir = [];
			foreach($path as $move)
			{
				switch($move)
				{
					case self::DIR_N:
						$dir[] = 'N';
						break;
					case self::DIR_NE:
						$dir[] = 'NE';
						break;
					case self::DIR_SE:
						$dir[] = 'SE';
						break;
					case self::DIR_S:
						$dir[] = 'S';
						break;
					case self::DIR_SW:
						$dir[] = 'SW';
						break;
					case self::DIR_NW:
						$dir[] = 'NW';
						break;
					case self::DIR_J:
						$dir[] = 'Jump';
						break;
					default:
						$dir[] = '?' . $move;
						break;
				}
			}

			return implode(', ', $dir);
		}

		/**
		 * @param \PhpDoc\Point $point
		 *
		 * @return boolean
		 */
		public function high_tile($point) : bool
		{
			// out of bounds or water
			if(empty($this->tiles[$point->y][$point->x]))
			{
				return FALSE;
			}

			switch($this->tiles[$point->y][$point->x] & self::MASK_TILE_TYPE)
			{
				case self::TILE_WATER:
				case self::TILE_LOW_ELEVATOR:
				case self::TILE_TRAMPOLINE:
				case self::TILE_ROTATOR:
				case self::TILE_LASER:
				case self::TILE_ICE:
				case self::TILE_BUILD:
				case self::TILE_BOAT:
				case self::TILE_ANTI_ICE:
				case self::TILE_LOW_LAND:
				case self::TILE_LOW_GREEN:
				case self::TILE_LOW_BLUE:
					return FALSE;

				case self::TILE_HIGH_ELEVATOR:
				case self::TILE_HIGH_LAND:
				case self::TILE_HIGH_GREEN:
				case self::TILE_HIGH_BLUE:
					return TRUE;

				default:
					throw new \RuntimeException('Unknown title: ' . $this->tiles[$point->y][$point->x]);
			}
		}

		public function green_wall_test()
		{
			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) == self::TILE_LOW_GREEN)
					{
						return;
					}
				}
			}

			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) == self::TILE_HIGH_GREEN)
					{
						$this->tiles[$y][$x] += self::TILE_LOW_GREEN - self::TILE_HIGH_GREEN;
					}
				}
			}
		}

		public function blue_wall_test()
		{
			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) == self::TILE_LOW_BLUE)
					{
						return;
					}
				}
			}
			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) == self::TILE_HIGH_BLUE)
					{
						$this->tiles[$y][$x] += self::TILE_LOW_BLUE - self::TILE_HIGH_BLUE;
					}
				}
			}
		}

		/**
		 * @return int
		 */
		public function points()
		{
			return $this->points;
		}

		/**
		 * @return int[]
		 */
		public function tile_type_count()
		{
			$c = array_fill_keys(range(0, 16), 0);
			foreach($this->tiles as $row)
			{
				foreach($row as $tile)
				{
					$c[$tile & self::MASK_TILE_TYPE]++;
				}
			}
			return $c;
		}

		public function imposible()
		{
			$tile_types = $this->tile_type_count();

			// avoid giving bad answers on non-implemented tiles
			if($tile_types[self::TILE_ROTATOR]) return false;
			if($tile_types[self::TILE_LASER]) return false;
			if($tile_types[self::TILE_BUILD]) return false;
			if($tile_types[self::TILE_BOAT]) return false;

			$reachable = array_fill_keys(array_keys($this->tiles), []);

			foreach($this->tiles as $y => $row)
			{
				foreach(array_keys($row) as $x)
				{
					$reachable[$y][$x] = 0;
				}
			}

			$reachable[$this->player->y][$this->player->x] = 1;
			/** @var \PhpDoc\Point[] $todo */
			$todo = [$this->player];

			while($todo)
			{
				$start_point = array_pop($todo);
				$neighbors = $this->next_points($start_point);
				$start_tile = ($this->tiles[$start_point->y][$start_point->x] ?? 0) & self::MASK_TILE_TYPE;
				if($start_tile)
				{
					switch($start_tile)
					{
						case self::TILE_TRAMPOLINE:
							$neighbors = array_merge($neighbors, $this->next_points($start_point, 2));
							break;
					}
					foreach($neighbors as $point)
					{
						if(!empty($reachable[$point->y][$point->x]))
						{
							continue;
						}

						$tile = ($this->tiles[$point->y][$point->x] ?? 0) & self::MASK_TILE_TYPE;
						if(!$tile)
						{
							continue;
						}
						$reachable[$point->y][$point->x] = 1;
						$todo[] = $point;
					}
				}
			}

			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					$tile = $tile_with_item & self::MASK_TILE_TYPE;
					if($tile === self::TILE_LOW_GREEN || $tile === self::TILE_HIGH_GREEN)
					{
						if(empty($reachable[$y][$x]))
						{
							return TRUE;
						}
					}
				}
			}
			return FALSE;
		}

		/**
		 * @param $old_tile
		 */
		private function wall_test($old_tile) : void
		{
			switch($old_tile & self::MASK_TILE_TYPE)
			{
				case self::TILE_LOW_BLUE:
					$this->blue_wall_test();
					return;

				case self::TILE_LOW_GREEN:
					$this->green_wall_test();
					return;
			}
		}
	}
