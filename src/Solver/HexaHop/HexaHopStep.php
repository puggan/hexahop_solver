<?php

	namespace Puggan\Solver\HexaHop;

	define('DISPLAY_STEPS', 1000);

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
	$timestamp = hrtime(true);
	while($solver->step($pid))
	{
		echo '.';
		if(++$steps % DISPLAY_STEPS === 0) {
			$old = $timestamp;
			$timestamp = hrtime(true);
			echo $steps, ' @ ', (DISPLAY_STEPS * 1e9 / ($timestamp - $old)), PHP_EOL;
		}
		usleep(1e3);
	}
	echo PHP_EOL, 'Done!', PHP_EOL;
	echo file_get_contents(dirname(__DIR__, 3) . '/data/' . $level_number . '/solved.ini'), PHP_EOL;
