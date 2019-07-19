#!/usr/bin/env php
<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\IniHashStorage;
	use Puggan\Solver\SolverNoSave;

	define('DOT_STEPS', 1000);
	define('DISPLAY_STEPS', 100000);
	define('SLEEP_TIME', 100);

	if($argc < 2)
	{
		die('$level_number missing');
	}

	$level_number = +$argv[1];

	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$start_state = new HexaHopMap($level_number);
	$solver = new SolverNoSave($start_state, new IniHashStorage(HexaHopSolver::data_dir($level_number) . 'solved.ini'));
	echo $start_state->map_info(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
	$steps = 0;
	$timestamp = hrtime(TRUE);
	while($solver->step())
	{
		++$steps;
		if($steps % DOT_STEPS === 0)
		{
			echo '.';
		}
		if($steps % DISPLAY_STEPS === 0)
		{
			$old = $timestamp;
			$timestamp = hrtime(TRUE);
			echo $steps, ' @ ', (DISPLAY_STEPS * 1e9 / ($timestamp - $old)), PHP_EOL;
			echo json_encode($solver->debug()), PHP_EOL;
		}
		//usleep(SLEEP_TIME);
		//sleep(1);
		//echo json_encode($solver->debug()), PHP_EOL;
	}
	echo PHP_EOL, 'Done!', PHP_EOL;
	echo file_get_contents(dirname(__DIR__, 3) . '/data/' . $level_number . '/solved.ini'), PHP_EOL;
