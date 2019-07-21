<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\Entities\JSON\MapInfo;
	use Puggan\Solver\Entities\Player;
	use Puggan\Solver\Entities\Point;
	use Puggan\Solver\Entities\Projectile;
	use Puggan\Solver\MapState;

	class HexaHopMap extends MapState implements \JsonSerializable
	{
		public const DIR_N = 0;
		public const DIR_NE = 1;
		public const DIR_SE = 2;
		public const DIR_S = 3;
		public const DIR_SW = 4;
		public const DIR_NW = 5;
		public const DIR_J = 6;

		public const TILE_WATER = 0;
		public const TILE_LOW_LAND = 1;
		public const TILE_LOW_GREEN = 2;
		public const TILE_HIGH_GREEN = 3;
		public const TILE_TRAMPOLINE = 4;
		public const TILE_ROTATOR = 5;
		public const TILE_HIGH_LAND = 6;
		public const TILE_LOW_BLUE = 7;
		public const TILE_HIGH_BLUE = 8;
		public const TILE_LASER = 9;
		public const TILE_ICE = 10;
		public const TILE_ANTI_ICE = 11;
		public const TILE_BUILD = 12;
		//private const TILE_UNKNOWN_13 = 13;
		public const TILE_BOAT = 14;
		public const TILE_LOW_ELEVATOR = 15;
		public const TILE_HIGH_ELEVATOR = 16;

		public const ITEM_ANTI_ICE = 1;
		public const ITEM_JUMP = 2;

		public const MASK_TILE_TYPE = 0x1F;
		public const MASK_ITEM_TYPE = 0xE0;
		public const SHIFT_TILE_ITEM = 5;

		/** @var MapInfo $map_info */
		protected $map_info;

		/** @var int x_min */
		protected $x_min;

		/** @var int x_max */
		protected $x_max;

		/** @var int y_min */
		protected $y_min;

		/** @var int y_max */
		protected $y_max;

		/** @var int[][] */
		protected $tiles = [];

		/** @var int[] */
		protected $items = [];

		/** @var Player player */
		protected $player;

		/** @var int points */
		protected $points;

		/** @var int */
		protected $par;

		public function __construct($level_number, $path = NULL)
		{
			$this->map_info = self::read_map_info($level_number);
			if(!$this->map_info)
			{
				throw new \RuntimeException('invalid level_number. ' . $level_number);
			}

			$this->points = 0;
			$this->par = $this->map_info->par;
			$this->items[self::ITEM_ANTI_ICE] = 0;
			$this->items[self::ITEM_JUMP] = 0;
			$this->player = new Player($this->map_info->start_x, $this->map_info->start_y, 0);

			$this->parse_map(new MapStream(self::getResourcePath('levels/' . $this->map_info->file)));

			if($this->high_tile($this->player))
			{
				$this->player->z = 1;
			}

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
			return !$this->player->alive || $this->points > $this->par;
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
			$old_tile = $this->move_out_of($this->player);
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
		private static function getResource($filename) : string
		{
			return file_get_contents(self::getResourcePath($filename));
		}

		/**
		 * @param string $filename
		 *
		 * @return string
		 */
		private static function getResourcePath($filename) : string
		{
			return dirname(__DIR__, 3) . '/resources/' . $filename;
		}

		/**
		 * @param $level_number
		 *
		 * @return MapInfo
		 */
		private static function read_map_info($level_number) : MapInfo
		{
			static $json;
			if(!$json)
			{
				$json = self::list_maps();
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

			$this->tiles = array_fill(
				$this->y_min - 1,
				$this->y_max - $this->y_min + 3,
				array_fill(
					$this->x_min - 1,
					$this->x_max - $this->x_min + 3,
					self::TILE_WATER
				)
			);
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
		 * @param Point $point
		 * @param int $direction
		 * @param int $old_tile
		 */
		private function move_into($point, $direction, $old_tile) : void
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
					if($direction === self::DIR_J)
					{
						break;
					}
					$goal_point = $this->next_point($point, $direction, 2);
					// if jumping from a high place, skip height tests
					if($this->player->z <= 0)
					{
						$mid_point = $this->next_point($point, $direction);
						if($this->high_tile($mid_point))
						{
							break;
						}
						if($this->high_tile($goal_point))
						{
							$this->move_into($mid_point, $direction, $old_tile);
							return;
						}
					}

					$this->move_into($goal_point, $direction, $tile);
					return;

				case self::TILE_ROTATOR:
					$swap_points = $this->next_points($point);
					$swap_in_tile = ($this->tiles[$swap_points[5]->y][$swap_points[5]->x] ?? 0) & self::MASK_TILE_TYPE;
					foreach($swap_points as $swap_point)
					{
						$swap_out_tile = ($this->tiles[$swap_point->y][$swap_point->x] ?? 0);
						$item = $swap_out_tile & self::MASK_ITEM_TYPE;
						$swap_out_tile -= $item;
						$this->tiles[$swap_point->y][$swap_point->x] = $swap_in_tile + $item;
						$swap_in_tile = $swap_out_tile;
					}
					break;

				case self::TILE_LASER:
					;
					/** @var Projectile[] $projectiles */
					$projectiles = [];
					/** @var Projectile[] $todos */
					$todos = [];
					/** @var Point $damage */
					$damage = [];
					if($direction === self::DIR_J)
					{
						$todos = [
							Projectile::PointDir($point, 0),
							Projectile::PointDir($point, 1),
							Projectile::PointDir($point, 2),
							Projectile::PointDir($point, 3),
							Projectile::PointDir($point, 4),
							Projectile::PointDir($point, 5),
						];
					}
					else
					{
						$todos[] = Projectile::PointDir($point, $direction);
					}

					while($todos)
					{
						$todo_projectile = array_pop($todos);
						$todo_key = "{$todo_projectile->x}:{$todo_projectile->y}:{$todo_projectile->dir}";
						if(isset($projectiles[$todo_key]))
						{
							continue;
						}
						$projectiles[$todo_key] = $todo_projectile;
						$hit_point = $this->next_point($todo_projectile, $todo_projectile->dir);
						$hit_key = "{$hit_point->x}:{$hit_point->y}";
						$hit_tile = ($this->tiles[$hit_point->y][$hit_point->x] ?? -1);
						switch($hit_tile)
						{
							case -1:
								break;

							case self::TILE_ICE:
								$todos[] = Projectile::PointDir($hit_point, ($todo_projectile->dir + 5) % 6);
								$todos[] = Projectile::PointDir($hit_point, ($todo_projectile->dir + 1) % 6);
								break;

							case self::TILE_WATER:
								$todos[] = Projectile::PointDir($hit_point, $todo_projectile->dir);
								break;

							default:
								$damage[$hit_key] = $hit_point;
								break;
						}
					}

					$damage_by_tile_type = array_fill(0, 17, 0);
					foreach($damage as $hit_point)
					{
						$hit_tile = ($this->tiles[$hit_point->y][$hit_point->x] ?? -1);
						$damage_by_tile_type[$hit_tile]++;
						switch($hit_tile)
						{
							case self::TILE_WATER:
								break;

							case self::TILE_LASER:
								$this->tiles[$hit_point->y][$hit_point->x] = self::TILE_WATER;
								foreach($this->next_points($hit_point) as $extra_hit_point)
								{
									$damage_by_tile_type[$this->tiles[$extra_hit_point->y][$extra_hit_point->x] ?? 0]++;
									$this->tiles[$extra_hit_point->y][$extra_hit_point->x] = self::TILE_WATER;
								}
								break;

							default:
								$this->tiles[$hit_point->y][$hit_point->x] = self::TILE_WATER;
								break;
						}
					}

					if(!$this->tiles[$point->y][$point->x])
					{
						$this->player->alive = FALSE;
					}

					if($damage_by_tile_type[self::TILE_LOW_GREEN])
					{
						$this->wall_test(self::TILE_LOW_GREEN);
					}
					if($damage_by_tile_type[self::TILE_LOW_BLUE])
					{
						$this->wall_test(self::TILE_LOW_BLUE);
					}

					// Water and green tiles give 0 point, all other give 10 points
					unset($damage_by_tile_type[self::TILE_WATER], $damage_by_tile_type[self::TILE_LOW_GREEN], $damage_by_tile_type[self::TILE_HIGH_GREEN]);
					$this->points += 10 * array_sum($damage_by_tile_type);
					break;

				case self::TILE_ICE:
					// Anti Ice?
					if($this->items[self::ITEM_ANTI_ICE] > 0)
					{
						$this->items[self::ITEM_ANTI_ICE]--;
						$this->tiles[$point->y][$point->x] = self::TILE_ANTI_ICE;
						break;
					}

					// TODO wall-test before or after?

					// Normal Ice
					foreach(range(1, 100) as $distance)
					{
						$goal_point = $this->next_point($point, $direction, $distance);
						if(($this->tiles[$goal_point->y][$goal_point->x] ?? 0) !== self::TILE_ICE)
						{
							$this->move_into($goal_point, $direction, $old_tile);
							return;
						}
					}
					break;

				case self::TILE_BUILD:
					$high_built = 0;
					$low_built = 0;
					foreach($this->next_points($point) as $build_point)
					{
						$build_tile = ($this->tiles[$build_point->y][$build_point->x] ?? -1) & self::MASK_TILE_TYPE;
						if($build_tile === self::TILE_LOW_GREEN)
						{
							$this->tiles[$build_point->y][$build_point->x] += self::TILE_HIGH_GREEN - self::TILE_LOW_GREEN;
							$high_built++;
						}
						else if($build_tile === self::TILE_WATER)
						{
							$this->tiles[$build_point->y][$build_point->x] += self::TILE_LOW_GREEN - self::TILE_WATER;
							$low_built++;
						}
					}
					if($high_built && !$low_built)
					{
						$this->wall_test(self::TILE_LOW_GREEN);
					}
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
				case self::TILE_ANTI_ICE:
				case self::TILE_BOAT:
				case self::TILE_BUILD:
				case self::TILE_HIGH_ELEVATOR:
				case self::TILE_ICE:
				case self::TILE_LASER:
				case self::TILE_LOW_BLUE:
				case self::TILE_LOW_GREEN:
				case self::TILE_LOW_LAND:
				case self::TILE_ROTATOR:
				case self::TILE_TRAMPOLINE:
				case self::TILE_WATER:
					$this->player->z = 0;
					break;

				case self::TILE_HIGH_BLUE:
				case self::TILE_HIGH_GREEN:
				case self::TILE_HIGH_LAND:
				case self::TILE_LOW_ELEVATOR:
					$this->player->z = 1;
					break;
			}
			//</editor-fold>

			$this->wall_test($old_tile);
		}

		/**
		 * @param Point $point
		 *
		 * @return int tile
		 */
		private function move_out_of($point) : int
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
		 * @param Point $current
		 * @param int $direction
		 * @param int $steps
		 *
		 * @return Point
		 */
		private function next_point($current, $direction, $steps = 1) : Point
		{
			/** @var Point $new_point */
			$new_point = Point::copy($current);
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
		 * @return Point[]
		 */
		private function next_points($current, $steps = 1) : array
		{
			$points = [];
			foreach(range(0, 5) as $direction)
			{

				/** @var Point $new_point */
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
				'map_info' => $this->map_info,
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
			return json_encode($this->map_info, $json_option);
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
		 * @param Point $point
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
					if(($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_LOW_GREEN)
					{
						return;
					}
				}
			}

			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_HIGH_GREEN)
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
					if(($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_LOW_BLUE)
					{
						return;
					}
				}
			}
			foreach($this->tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					if(($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_HIGH_BLUE)
					{
						$this->tiles[$y][$x] += self::TILE_LOW_BLUE - self::TILE_HIGH_BLUE;
					}
				}
			}
		}

		/**
		 * @return int
		 */
		public function points() : int
		{
			return $this->points;
		}

		/**
		 * @return int
		 */
		public function par() : int
		{
			return $this->par;
		}

		/**
		 * @param int $new_par
		 */
		public function overridePar($new_par)
		{
			$this->par = $new_par;
		}

		/**
		 * @return int[]
		 */
		public function tile_type_count() : array
		{
			$c = array_fill(0, 17, 0);
			foreach($this->tiles as $row)
			{
				foreach($row as $tile)
				{
					$c[$tile & self::MASK_TILE_TYPE]++;
				}
			}
			return $c;
		}

		/**
		 * @return int[]
		 */
		public function item_count() : array
		{
			$c = $this->items;
			foreach($this->tiles as $row)
			{
				foreach($row as $tile)
				{
					$item_shifted = $tile & self::MASK_ITEM_TYPE;
					if($item_shifted)
					{
						$c[$item_shifted >> self::SHIFT_TILE_ITEM]++;
					}
				}
			}
			return $c;
		}

		public function impossible() : bool
		{
			//<editor-fold desc="Init vars, counting stuff">
			$tile_types = $this->tile_type_count();
			$total_items = $this->item_count();

			$my_tiles = $this->tiles;
			$player_tile = $this->tiles[$this->player->y][$this->player->x] & self::MASK_TILE_TYPE;
			$player_on_green = $player_tile === self::TILE_LOW_GREEN || $player_tile === self::TILE_HIGH_GREEN;
			$missing_green = $tile_types[self::TILE_LOW_GREEN] + $tile_types[self::TILE_HIGH_GREEN];

			// Already won
			if(!$missing_green)
			{
				return FALSE;
			}
			//</editor-fold>

			//<editor-fold desc="Par vs Steps + Greens">
			$minimum_cost = $missing_green + ($player_on_green ? 0 : 1);
			if($tile_types[self::TILE_LASER])
			{
				$minimum_cost--;
				if($total_items[self::ITEM_JUMP]) {
					$minimum_cost -= 5*5 * $total_items[self::ITEM_JUMP];
				}
				if($tile_types[self::TILE_ICE])
				{
					$minimum_cost = 1;
				}
			}
			// Enough steps to step on all greens?
			if($this->points + $minimum_cost > $this->par)
			{
				return TRUE;
			}
			//</editor-fold>

			//<editor-fold desc="Init reachable">
			/** @var boolean[][][] $reachable keys: z, y, x */
			$reachable = [];

			foreach($my_tiles as $y => $row)
			{
				foreach(array_keys($row) as $x)
				{
					$reachable[0][$y][$x] = FALSE;
					$reachable[1][$y][$x] = FALSE;
				}
			}
			$reachable[$this->player->z][$this->player->y][$this->player->x] = true;
			//</editor-fold>

			/**
			 * @param bool $green_wall_lowerable
			 * @param bool $blue_wall_lowerable
			 *
			 * @return bool
			 */
			$expand_reachable = function ($green_wall_lowerable, $blue_wall_lowerable) use (&$my_tiles, &$reachable, &$tile_types) {
				/** @var Point[] $todo */
				$todo = [];
				foreach($reachable as $z => $plane)
				{
					foreach($plane as $y => $row)
					{
						foreach($row as $x => $reached)
						{
							if($reached)
							{
								$todo[] = new Point($x, $y, $z);
							}
						}
					}
				}

				while($todo)
				{
					$start_point = array_pop($todo);
					$neighbors = $this->next_points($start_point);
					$start_tile = ($my_tiles[$start_point->y][$start_point->x] ?? self::TILE_WATER) & self::MASK_TILE_TYPE;
					if($start_tile !== self::TILE_WATER)
					{
						switch($start_tile)
						{
							case self::TILE_TRAMPOLINE:
								$neighbors = array_merge($neighbors, $this->next_points($start_point, 2));
								break;

							case self::TILE_ROTATOR:
							case self::TILE_BUILD:
								$neighbor_count = 0;
								foreach($neighbors as $neighbor)
								{
									$neighbor_tile = ($my_tiles[$neighbor->y][$neighbor->x] ?? 0) & self::MASK_TILE_TYPE;
									// Double rotator can move about everywhere, rotated builder is a mess too
									if($neighbor_tile === self::TILE_ROTATOR)
									{
										return FALSE;
									}
									if($neighbor_tile !== self::TILE_WATER)
									{
										$neighbor_count++;
									}
								}
								if($neighbor_count)
								{
									foreach($neighbors as $neighbor)
									{
										$neighbor_tile = ($my_tiles[$neighbor->y][$neighbor->x] ?? -1) & self::MASK_TILE_TYPE;
										if($neighbor_tile === self::TILE_WATER)
										{
											// As it can be either low or high, treat it as an elevator
											$my_tiles[$neighbor->y][$neighbor->x] = self::TILE_LOW_ELEVATOR;
											$tile_types[self::TILE_WATER]--;
											$tile_types[self::TILE_LOW_ELEVATOR]++;
										}
									}
								}
								break;
						}
						foreach($neighbors as $point)
						{
							if(!empty($reachable[$point->z][$point->y][$point->x]))
							{
								continue;
							}

							$tile = ($my_tiles[$point->y][$point->x] ?? 0) & self::MASK_TILE_TYPE;
							switch($tile)
							{
								case self::TILE_HIGH_GREEN:
									if($point->z > 0)
									{
										$reachable[1][$point->y][$point->x] = TRUE;
										$todo[] = $point;
									}
									if($green_wall_lowerable)
									{
										$reachable[0][$point->y][$point->x] = TRUE;
										$new_point = Point::copy($point);
										$new_point->z = 0;
										$todo[] = $new_point;
									}
									break;

								case self::TILE_HIGH_BLUE:
									if($point->z > 0)
									{
										$reachable[1][$point->y][$point->x] = TRUE;
										$todo[] = $point;
									}
									if($blue_wall_lowerable)
									{
										$reachable[0][$point->y][$point->x] = TRUE;
										$new_point = Point::copy($point);
										$new_point->z = 0;
										$todo[] = $new_point;
									}
									break;

								// Always reach z 0 and z 1
								case self::TILE_LOW_ELEVATOR:
									$reachable[0][$point->y][$point->x] = TRUE;
									$reachable[1][$point->y][$point->x] = TRUE;
									$new_point = Point::copy($point);
									$new_point->z = 1;
									$todo[] = $new_point;
									break;

								// May reach z1 if currently on z1
								case self::TILE_TRAMPOLINE:
									$reachable[0][$point->y][$point->x] = TRUE;
									$reachable[$point->z][$point->y][$point->x] = TRUE;
									$todo[] = $point;
									break;

								case self::TILE_ROTATOR:
								case self::TILE_LASER:
								case self::TILE_ICE:
								case self::TILE_BUILD:
								case self::TILE_BOAT:
								case self::TILE_ANTI_ICE:
								case self::TILE_LOW_LAND:
								case self::TILE_LOW_GREEN:
								case self::TILE_LOW_BLUE:
									$reachable[0][$point->y][$point->x] = TRUE;
									$new_point = Point::copy($point);
									$new_point->z = 0;
									$todo[] = $new_point;
									break;

								case self::TILE_HIGH_ELEVATOR:
								case self::TILE_HIGH_LAND:
									if($point->z > 0)
									{
										$reachable[1][$point->y][$point->x] = TRUE;
										$todo[] = $point;
									}
									break;

								case self::TILE_WATER:
								default:
									break;
							}
						}
					}
				}
				return TRUE;
			};
			/**
			 * @return bool[]
			 */
			$wall_test = static function () use (&$my_tiles, &$reachable, &$tile_types) {
				if($tile_types[self::TILE_HIGH_GREEN] === 0 && $tile_types[self::TILE_HIGH_BLUE] === 0)
				{
					return [FALSE, FALSE];
				}
				$unreached_high_green = 0;
				$unreached_low_green = 0;
				$unreached_low_blue = 0;
				foreach($my_tiles as $y => $row)
				{
					foreach($row as $x => $tile_wi)
					{
						switch($tile_wi & self::MASK_TILE_TYPE)
						{
							case self::TILE_HIGH_GREEN:
								if(!$reachable[1][$y][$x] && !$reachable[0][$y][$x])
								{
									$unreached_high_green++;
								}
								break;

							case self::TILE_LOW_GREEN:
								if(!$reachable[0][$y][$x])
								{
									$unreached_low_green++;
								}
								break;

							case self::TILE_LOW_BLUE:
								if(!$reachable[0][$y][$x])
								{
									$unreached_low_blue++;
								}
								break;
						}
						if($unreached_low_blue > 0 || $tile_types[self::TILE_HIGH_BLUE] === 0)
						{
							if($unreached_low_green > 0 || $tile_types[self::TILE_HIGH_GREEN] === 0)
							{
								return [FALSE, FALSE];
							}
						}
					}
				}
				return [
					$tile_types[self::TILE_HIGH_GREEN] > 0 && $unreached_low_green === 0,
					$tile_types[self::TILE_HIGH_BLUE] > 0 && $unreached_low_blue === 0 && $unreached_high_green + $unreached_low_green > 0,
				];
			};
			if($expand_reachable(FALSE, FALSE) === FALSE)
			{
				return FALSE;
			}
			[$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();

			if($green_wall_lowerable)
			{
				if($expand_reachable(true, false) === FALSE)
				{
					return FALSE;
				}
				[$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();
			}
			if($blue_wall_lowerable)
			{
				if($expand_reachable($green_wall_lowerable, true) === FALSE)
				{
					return FALSE;
				}
				if(!$green_wall_lowerable)
				{
					[$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();
					if($green_wall_lowerable)
					{
						if($expand_reachable(TRUE, TRUE) === FALSE)
						{
							return FALSE;
						}
					}
				}
			}

			if($blue_wall_lowerable) {
				$minimum_cost += 10 * $tile_types[self::TILE_LOW_BLUE];
				if($this->points + $minimum_cost > $this->par)
				{
					return TRUE;
				}
			}

			//<editor-fold desc="Boats - Abort, no calculation implemented">
			// If any boat is reachable, skip the rest of the calculations
			if($tile_types[self::TILE_BOAT])
			{
				foreach($my_tiles as $y => $row)
				{
					foreach($row as $x => $tile_wi)
					{
						$tile = $tile_wi & self::MASK_TILE_TYPE;
						if($tile === self::TILE_BOAT && !empty($reachable[0][$y][$x]))
						{
							return FALSE;
						}
					}
				}
			}
			//</editor-fold>

			//<editor-fold desc="Lasers - Reach or Destroy">
			if($tile_types[self::TILE_LASER])
			{
				/** @var Point[] $missing_greens */
				$missing_greens = [];
				/** @var Point[] $reached_lasers */
				$reached_lasers = [];
				/** @var Point[] $other_lasers */
				$other_lasers = [];
				/** @var Point[] $other_lasers */
				$explodeable_lasers = [];

				foreach($my_tiles as $y => $row)
				{
					foreach($row as $x => $tile_wi)
					{
						$tile = $tile_wi & self::MASK_TILE_TYPE;
						if(empty($reachable[0][$y][$x]))
						{
							if($tile === self::TILE_LASER)
							{
								$other_lasers[] = new Point($x, $y, 0);
							}
							else if($tile === self::TILE_LOW_GREEN)
							{
								$missing_greens[] = new Point($x, $y, 0);
							}
							else if($tile === self::TILE_HIGH_GREEN)
							{
								$missing_greens[] = new Point($x, $y, 0);
							}
						}
						else if($tile === self::TILE_LASER)
						{
							$reached_lasers[] = new Point($x, $y, 0);
						}
					}
				}

				// if all green are reachable, but nu lasers are reachable, use the cost-calculation
				if(!$reached_lasers && !$missing_greens)
				{
					return ($this->points + $missing_green + ($player_on_green ? 0 : 1) > $this->par);
				}

				if(!$missing_greens)
				{
					return FALSE;
				}
				if(!$reached_lasers)
				{
					return TRUE;
				}

				// The destruction of a reachable laser + ice is BIG
				if($tile_types[self::TILE_ICE])
				{
					return FALSE;
				}

				foreach($reached_lasers as $laser_point)
				{
					foreach($missing_greens as $green_point_index => $green_point)
					{
						$delta_x = $laser_point->x - $green_point->x;
						$delta_y = $laser_point->y - $green_point->y;
						if(!$delta_x || !$delta_y || $delta_x === -$delta_y)
						{
							unset($missing_greens[$green_point_index]);
							if(!$missing_greens)
							{
								return FALSE;
							}
						}
					}
				}
				if(!$other_lasers)
				{
					return TRUE;
				}
				foreach($reached_lasers as $laser_point)
				{
					foreach($other_lasers as $other_point_index => $other_point)
					{
						$delta_x = $laser_point->x - $other_point->x;
						$delta_y = $laser_point->y - $other_point->y;
						if(!$delta_x || !$delta_y || $delta_x === -$delta_y)
						{
							$explodeable_lasers[] = $other_point;
							unset($other_lasers[$other_point_index]);
						}
					}
				}
				if(!$explodeable_lasers)
				{
					return TRUE;
				}

				foreach($explodeable_lasers as $laser_point)
				{
					foreach($missing_greens as $green_point_index => $green_point)
					{
						$delta_x = $laser_point->x - $green_point->x;
						$delta_y = $laser_point->y - $green_point->y;
						if($delta_x > 1 || $delta_x < -1 || $delta_y > 1 || $delta_y < -1)
						{
							continue;
						}
						if(!$delta_x || !$delta_y || $delta_x === -$delta_y)
						{
							unset($missing_greens[$green_point_index]);
							if(!$missing_greens)
							{
								return FALSE;
							}
						}
					}
				}

				return TRUE;
			}
			//</editor-fold>

			//<editor-fold desc="Was all green Reachable">
			foreach($my_tiles as $y => $row)
			{
				foreach($row as $x => $tile_with_item)
				{
					$tile = $tile_with_item & self::MASK_TILE_TYPE;
					if($tile === self::TILE_LOW_GREEN || $tile === self::TILE_HIGH_GREEN)
					{
						if(empty($reachable[0][$y][$x]) && empty($reachable[1][$y][$x]))
						{
							return TRUE;
						}
					}
				}
			}
			//</editor-fold>

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

		/**
		 * @return MapInfo[]
		 */
		public static function list_maps() : array
		{
			$extra_index = 101;
			$maps = [];
			/** @var MapInfo $map_info */
			foreach(json_decode(self::getResource('hexahopmaps.json'), FALSE) as $map_info)
			{
				if($map_info->level_number < 0)
				{
					$maps[$extra_index++] = $map_info;
				}
				else
				{
					if(isset($maps[$map_info->level_number]))
					{
						throw new \RuntimeException('Duplicate map at ' . $map_info->level_number);
					}
					$maps[$map_info->level_number] = new MapInfo($map_info);
				}
			}
			ksort($maps);
			return $maps;
		}
	}
