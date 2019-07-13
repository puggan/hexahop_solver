<?php

	namespace Puggan\Solver\HexaHop;

	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$pid = getmypid();

	$solver = new HexaHopSolver(1);
	echo $solver->step($pid) ? 'ok' : 'fail', PHP_EOL;
