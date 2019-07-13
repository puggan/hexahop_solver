<?php

	namespace Puggan\Solver;

	abstract class MapState
	{
		abstract public function __construct($data, $path = NULL);

		/**
		 * Player have won
		 * @return bool
		 */
		abstract public function won() : bool;

		/**
		 * Player have lost
		 * @return bool
		 */
		abstract public function lost() : bool;

		/**
		 * The game allows thus moves to be executed
		 * May include moves that make the player lose
		 * @return int[]
		 */
		abstract public function possible_moves() : array;

		/**
		 * Clones the state, make a move, and return that new state
		 *
		 * @param int $move move/direction to travel
		 *
		 * @return MapState Pure function, no side-effect allowed, so not $this
		 */
		public function move($move) : self
		{
			$state = clone $this;
			$state->_move($move);
			return $state;
		}

		/**
		 * Make a move in the current state
		 *
		 * @param int $move move/direction to travel
		 */
		abstract protected function _move($move) : void;

		/**
		 * Execute all posible moves, and return a list
		 * @return MapState[]
		 */
		public function move_all() : array
		{
			/** @var MapState[] $states */
			$states = [];
			foreach($this->possible_moves() as $move)
			{
				$states[$move] = $this->move($move);
			}
			return $states;
		}

		/**
		 * @return string uniq state hash, used to detect duplicates
		 */
		abstract public function hash() : string;

		/**
		 * @param int[] $path
		 *
		 * @return MapState
		 */
		public function path($path) : self
		{
			$state = clone $this;
			if(is_array($path))
			{
				foreach($path as $move)
				{
					$state->_move($move);
				}
			}
			return $state;
		}

		/**
		 * is the current state better that this other state?
		 *
		 * @param MapState $other
		 *
		 * @return bool
		 */
		abstract public function better($other) : bool;
	}
