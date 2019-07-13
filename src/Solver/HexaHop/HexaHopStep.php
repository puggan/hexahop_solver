<?php

	namespace Puggan\Solver\HexaHop;

	if($argc < 2)
	{
		die('$level_number missing');
	}

	$level_number = +$argv[1];


	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$pid = getmypid();

	$solver = new HexaHopSolver($level_number);
	echo $solver->map_info(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
	$steps = 0;
	while($solver->step($pid))
	{
		echo '.';
		if(++$steps % 250 === 0) {
			echo $steps, PHP_EOL;
		}
		usleep(1e3);
	}
	echo PHP_EOL, 'Done!', PHP_EOL;
	echo file_get_contents(dirname(__DIR__, 3) . '/data/' . $level_number . '/solved.ini'), PHP_EOL;
