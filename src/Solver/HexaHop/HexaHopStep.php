<?php

	namespace Puggan\Solver\HexaHop;

	$pid = getmypid();

	$solver = new HexaHopSolver(1);
	echo $solver->step($pid) ? 'ok' : 'fail', PHP_EOL;
