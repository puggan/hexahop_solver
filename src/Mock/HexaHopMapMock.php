<?php

	namespace Puggan\Mock;

	use PHPDoc\Player;
	use Puggan\Solver\HexaHop\HexaHopMap;
	use Puggan\Solver\MapState;

	class HexaHopMapMock extends HexaHopMap
	{
		/** @noinspection MagicMethodsValidityInspection */
		/** @noinspection PhpMissingParentConstructorInspection */
		public function __construct($tiles, $x, $y, $par = 9999, $path = NULL)
		{
			$this->tiles = $tiles;
			$y_list = array_keys($tiles);
			$y_min = $y_list[0];
			$y_max = $y_list[count($y_list) - 1];
			$x_list = array_keys($tiles[$y_min]);
			//$x_min = $x_list[0];
			$x_max = $x_list[count($x_list) - 1];

			$this->map_info = (object) [
				'file' => 'mock.lev',
				'title' => 'Mock Level',
				'level_number' => 999,
				'width' => $x_max,
				'height' => $y_max,
				'par' => $par,
				'start_x' => $x,
				'start_y' => $y,
			];

			$this->points = 0;
			$this->par = $this->map_info->par;
			$this->items[self::ITEM_ANTI_ICE] = 0;
			$this->items[self::ITEM_JUMP] = 0;
			/** @var Player $player */
			$player = (object) [
				'alive' => TRUE,
				'x' => $this->map_info->start_x,
				'y' => $this->map_info->start_y,
				'z' => 0,
			];
			$this->player = $player;

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

		/**
		 * @param int $move
		 *
		 * @return HexaHopMapMock
		 */
		public function move($move) : MapState
		{
			return parent::move($move);
		}
	}
