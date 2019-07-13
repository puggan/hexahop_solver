<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\MapState;

	class HexaHopMap extends MapState
	{

		public function __construct($data, $path = NULL)
		{
		}

		/**
		 * Player have won
		 * @return bool
		 */
		public function won() : bool
		{
			// TODO: Implement won() method.
			return false;
		}

		/**
		 * Player have lost
		 * @return bool
		 */
		public function lost() : bool
		{
			// TODO: Implement lost() method.
			return false;
		}

		/**
		 * The game allows thus moves to be executed
		 * May include moves that make the player lose
		 * @return int[]
		 */
		public function possible_moves() : array
		{
			// TODO: Implement possible_moves() method.
			return [];
		}

		/**
		 * Make a move in the current state
		 *
		 * @param int $move move/direction to travel
		 */
		protected function _move($move) : void
		{
			// TODO: Implement _move() method.
		}

		/**
		 * @return string uniq state hash, used to detect duplicates
		 */
		public function hash() : string
		{
			// TODO: Implement hash() method.
			return 'N/A';
		}

		/**
		 * @param MapState $a
		 * @param MapState $b
		 *
		 * @return int -1, 0, 1
		 */
		public static function cmp($a, $b) : int
		{
			// TODO: Implement cmp() method.
			return 0;
		}
	}
