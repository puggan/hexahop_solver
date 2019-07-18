<?php

	namespace Puggan\Solver\HexaHop;

	//use Puggan\Solver\AliasHashStorage;
	use Puggan\Solver\IniFolderHashStorage;
	use Puggan\Solver\IniHashStorage;
	use Puggan\Solver\Solver;
	use Puggan\Solver\TodoFolderStorage;

	/**
	 * Class HexaHopSolver
	 * @package Puggan\Solver\HexaHop
	 * @property-read HexaHopMap startState
	 */
	class HexaHopSolver extends Solver
	{
		/**
		 * HexaHopSolver constructor.
		 *
		 * @param int $level_number
		 *
		 * @throws \Exception
		 */
		public function __construct($level_number)
		{
			$path = self::data_dir($level_number);
			$new_map = is_dir($path . '/todo');
			$startState = new HexaHopMap($level_number);
			$solved = new IniHashStorage($path . 'solved.ini');
			//$alias = new AliasHashStorage(new IniHashStorage($path . 'alias.ini'));
			//$hashes = new IniHashStorage($path . 'hashes.ini');
			$hashes = new IniFolderHashStorage($path . 'hashes/');
			$todos = new TodoFolderStorage($path . 'todo/');
			if($new_map)
			{
				$todos->add([]);
			}
			parent::__construct($startState, $solved, /*$alias,*/ $hashes, $todos);
		}

		/**
		 * @param $level_number
		 *
		 * @return string
		 */
		public static function data_dir($level_number) : string
		{
			$path = dirname(__DIR__, 3) . '/data/' . $level_number . '/';
			if(!is_dir($path))
			{
				if(!mkdir($path) && !is_dir($path))
				{
					throw new \RuntimeException(sprintf('Directory "%s" was not created', $path));
				}
			}
			return $path;
		}

		public function map_info($json_option)
		{
			return $this->startState->map_info($json_option);
		}
	}
