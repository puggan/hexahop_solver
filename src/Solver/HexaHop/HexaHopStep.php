<?php

	namespace Puggan\Solver\HexaHop;

	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$pid = getmypid();

	$solver = new HexaHopSolver(1);
	while($solver->step($pid))
	{
		echo '.';
		usleep(1e3);
	}
	echo PHP_EOL, 'Done!', PHP_EOL;
	echo file_get_contents(dirname(__DIR__, 3) . '/data/1/solved.ini'), PHP_EOL;
