<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\AliasHashStorage;
	use Puggan\Solver\IniHashStorage;
	use Puggan\Solver\Solver;
	use Puggan\Solver\TodoFileStorage;

	class HexaHopSolver extends Solver
	{
		public function __construct($map_id)
		{
			$new_map = false;
			//<editor-fold desc="mkdir $this->path">
			$path = dirname(__DIR__, 3) . '/data/' . $map_id . '/';
			if(!is_dir($path))
			{
				if(!mkdir($path) && !is_dir($path))
				{
					throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
				}
				$new_map = true;
			}
			//</editor-fold>
			$startState = new HexaHopMap($map_id);
			$solved = new IniHashStorage($path . 'solved.ini');
			$alias = new AliasHashStorage(new IniHashStorage($path . 'alias.ini'));
			$hashes = new IniHashStorage($path . 'hashes.ini');
			$todos = new TodoFileStorage($path . 'todo.ini');
			if($new_map)
			{
				$todos->add([]);
			}
			parent::__construct($startState, $solved, $alias, $hashes, $todos);
		}
	}
