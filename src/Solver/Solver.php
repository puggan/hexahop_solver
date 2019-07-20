<?php

	namespace Puggan\Solver;

	use Puggan\Solver\HexaHop\HexaHopMap;

	class Solver
	{
		/** @var MapState $startState */
		protected $startState;

		/** @var HashStorage $solved */
		private $solved;

		/* * @var AliasStorage $alias */
		//private $alias;

		/** @var HashStorage $hashes */
		private $hashes;

		/** @var TodoStorage $todos */
		private $todos;

		/**
		 * Solver constructor.
		 *
		 * @param MapState $startState
		 * @param HashStorage $solved
		 * @-param AliasStorage $alias
		 * @param HashStorage $hashes
		 * @param TodoStorage $todos
		 */
		public function __construct($startState, $solved, /*$alias,*/ $hashes, $todos)
		{
			$this->startState = $startState;
			$this->solved = $solved;
			//$this->alias = $alias;
			$this->hashes = $hashes;
			$this->todos = $todos;
		}

		/**
		 * @param int[] $path
		 *
		 * @return MapState
		 */
		public function loadState($path) : MapState
		{
			return $this->startState->path($path);
		}

		/**
		 * @param int $pid
		 *
		 * @return bool
		 */
		public function step($pid) : bool
		{
			$path = $this->todos->reserve($pid);
			if($path === FALSE)
			{
				return FALSE;
			}
			$todo_state = $this->loadState($path);
			if($todo_state->lost())
			{
				throw new \RuntimeException('Invalid path in TODO, already lost: ' . $todo_state->print_path($path) . ' (' . implode(', ', $path) . ')');
			}
			if($todo_state->won())
			{
				throw new \RuntimeException('Invalid path in TODO, already won: ' . $todo_state->print_path($path));
			}
			foreach($todo_state->move_all() as $direction => $state)
			{
				if($state->lost())
				{
					continue;
				}

				$dir_path = $path;
				$dir_path[] = $direction;

				$hash = $state->hash();
				$duplicate_path = $this->hashes->get($hash);
				if($duplicate_path !== FALSE)
				{
					$duplicate_state = $this->loadState($duplicate_path);
					if(!$state->better($duplicate_state))
					{
						//$this->alias->add($dir_path, $duplicate_path);
						continue;
					}
					//$this->alias->add($duplicate_path, $dir_path);

					$this->todos->remove_all($duplicate_path);
				}
				$this->hashes->save($hash, $dir_path);

				if($state->won())
				{
					$this->solved->save($hash, $dir_path);

					// TODO: move to trigger
					if($state instanceof HexaHopMap && $this->startState instanceof HexaHopMap)
					{
						/** @var HexaHopMap $startState */
						$startState = $this->startState;
						if($state->points() < $startState->par())
						{
							$startState->overridePar($state->points());
						}
					}
				}
				else if(!$state->impossible())
				{
					$this->todos->add($dir_path);
				}
			}
			$this->todos->remove($path);
			return TRUE;
		}
	}
