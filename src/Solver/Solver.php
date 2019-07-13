<?php

	namespace Puggan\Solver;

	class Solver
	{
		/** @var MapState $startState */
		private $startState;

		/** @var HashStorage $solved */
		private $solved;

		/** @var AliasStorage $alias */
		private $alias;

		/** @var HashStorage $hashes */
		private $hashes;

		/** @var TodoStorage $todos */
		private $todos;

		/**
		 * Solver constructor.
		 *
		 * @param MapState $startState
		 * @param HashStorage $solved
		 * @param AliasStorage $alias
		 * @param HashStorage $hashes
		 * @param TodoStorage $todos
		 */
		public function __construct($startState, $solved, $alias, $hashes, $todos)
		{
			$this->startState = $startState;
			$this->solved = $solved;
			$this->alias = $alias;
			$this->hashes = $hashes;
			$this->todos = $todos;
		}

		/**
		 * @param int[] $path
		 *
		 * @return MapState
		 */
		public function loadState($path)
		{
			return $this->startState->path($path);
		}

		/**
		 * @param int $pid
		 *
		 * @return bool
		 */
		public function step($pid)
		{
			$path = $this->todos->reserve($pid);
			if($path === FALSE)
			{
				return FALSE;
			}
			foreach($this->loadState($path)->move_all() as $direction => $state)
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
						$this->alias->add($dir_path, $duplicate_path);
						continue;
					}
					$this->alias->add($duplicate_path, $dir_path);

					$this->todos->remove_all($duplicate_path);
				}

				if($state->won())
				{
					$this->solved->save($hash, $dir_path);
				}
				else
				{
					$this->todos->add($dir_path);
				}
			}
			$this->todos->remove($path);
			return TRUE;
		}
	}
