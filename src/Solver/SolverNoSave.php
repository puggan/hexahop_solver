<?php

	namespace Puggan\Solver;

	use Puggan\Solver\HexaHop\HexaHopMap;

	class SolverNoSave
	{
		/** @var HashStorage $solved */
		protected $solved;

		/** @var int $depth */
		protected $depth = 0;

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
			while($this->depth > 0 && empty($this->path_todos[$this->depth]))
			{
				$this->depth--;
			}

			if($this->depth < 0 || empty($this->path_todos[$this->depth]))
			{
				return FALSE;
			}

			$dir = array_pop($this->path_todos[$this->depth]);
			$current = $this->states[$this->depth];
			$this->depth++;
			$this->path[$this->depth] = $dir;
			$new = $current->move($dir);
			$this->states[$this->depth] = $new;

			// If dead, Undo
			if($new->lost())
			{
				$this->depth--;
				return TRUE;
			}

			$hash = $new->hash();
			$this->path_hashes[$this->depth] = $hash;

			// Walking in circles?
			if(array_search($hash, $this->path_hashes, TRUE) < $this->depth)
			{
				$this->depth--;
				return TRUE;
			}

			// Won? Save and undo
			if($new->won())
			{
				$this->solved->save($hash, array_slice($this->path, 0, $this->depth));
				$this->depth--;

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

			// If impossible state, Undo
			if($new->impossible())
			{
				$this->depth--;
				return TRUE;
			}

			//
			$this->path_todos[$this->depth] = $new->possible_moves();

			// Random what order to try the paths
			/** @noinspection NonSecureShuffleUsageInspection */
			shuffle($this->path_todos[$this->depth]);
			return TRUE;
		}

		public function debug()
		{
			// $d = [];
			// if($this->depth)
			// {
			// 	foreach(range(0, $this->depth - 1) as $i)
			// 	{
			// 		$d[] = ['dir' => $this->path[$i + 1], 'todo' => $this->path_todos[$i]];
			// 	}
			// }
			// $d[] = ['next' => $this->path_todos[$this->depth]];
			// return $d;
			/** @var \Puggan\Solver\HexaHop\HexaHopMap $map_state */
			$map_state = $this->states[$this->depth];
			return implode(',', array_slice($this->path, 0, $this->depth + 1)) . ' @ ' . $this->depth . ' (' . $map_state->points() . ')';
		}
	}
