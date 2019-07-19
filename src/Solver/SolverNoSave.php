<?php

	namespace Puggan\Solver;

	use Puggan\Solver\HexaHop\HexaHopMap;

	class SolverNoSave
	{
		/** @var HashStorage $solved */
		protected $solved;

		/** @var int $deepth */
		protected $deepth = 0;

		/** @var int[] $path */
		protected $path;

		/** @var int[][] $path_todos */
		protected $path_todos = [];

		/** @var MapState[] $states */
		protected $states;

		/** @var string[] $path_hashes */
		protected $path_hashes;

		/**
		 * SolverNoSave constructor.
		 *
		 * @param MapState $startState
		 * @param HashStorage $solved
		 */
		public function __construct($startState, $solved)
		{
			$this->solved = $solved;
			$this->states[0] = $startState;
			$this->path_hashes[0] = $startState->hash();
			$this->path_todos[0] = $startState->possible_moves();
			/** @noinspection NonSecureShuffleUsageInspection */
			shuffle($this->path_todos[0]);
		}

		public function step()
		{
			while($this->deepth > 0 && empty($this->path_todos[$this->deepth]))
			{
				$this->deepth--;
			}

			if($this->deepth < 0 || empty($this->path_todos[$this->deepth]))
			{
				return FALSE;
			}

			$dir = array_pop($this->path_todos[$this->deepth]);
			$current = $this->states[$this->deepth];
			$this->deepth++;
			$this->path[$this->deepth] = $dir;
			$new = $current->move($dir);
			$this->states[$this->deepth] = $new;

			// If dead, Undo
			if($new->lost())
			{
				$this->deepth--;
				return TRUE;
			}

			$hash = $new->hash();
			$this->path_hashes[$this->deepth] = $hash;

			// Walking in circles?
			if(array_search($hash, $this->path_hashes, TRUE) < $this->deepth)
			{
				$this->deepth--;
				return TRUE;
			}

			// Won? Save and undo
			if($new->won())
			{
				$this->solved->save($hash, array_slice($this->path, 0, $this->deepth));
				$this->deepth--;

				// TODO: move to trigger
				if($new instanceof HexaHopMap)
				{
					$points = $new->points();
					if($points < $new->par())
					{
						foreach($this->states as $s)
						{
							if($s instanceof HexaHopMap && $points < $s->par())
							{
								$s->overridePar($points);
							}
						}
					}
				}

				return TRUE;
			}

			// If imposible state, Undo
			if($new->imposible())
			{
				$this->deepth--;
				return TRUE;
			}

			//
			$this->path_todos[$this->deepth] = $new->possible_moves();

			// Random what order to try the paths
			/** @noinspection NonSecureShuffleUsageInspection */
			shuffle($this->path_todos[$this->deepth]);
			return TRUE;
		}

		public function debug()
		{
			// $d = [];
			// if($this->deepth)
			// {
			// 	foreach(range(0, $this->deepth - 1) as $i)
			// 	{
			// 		$d[] = ['dir' => $this->path[$i + 1], 'todo' => $this->path_todos[$i]];
			// 	}
			// }
			// $d[] = ['next' => $this->path_todos[$this->deepth]];
			// return $d;
			/** @var \Puggan\Solver\HexaHop\HexaHopMap $map_state */
			$map_state = $this->states[$this->deepth];
			return implode(',', array_slice($this->path, 0, $this->deepth + 1)) . ' @ ' . $this->deepth . ' (' . $map_state->points() . ')';
		}
	}
