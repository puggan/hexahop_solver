#!/usr/bin/env php
<?php

	namespace Puggan\Solver\HexaHop;

	use Puggan\Solver\IniHashStorage;

	define('DOT_STEPS', 1000);
	define('DISPLAY_STEPS', 100000);
	define('SLEEP_TIME', 0);

	if($argc < 2)
	{
		die('$level_number missing');
	}

	$level_number = +$argv[1];

	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$start_state = new HexaHopMap($level_number);
	$solved_filename = HexaHopSolver::data_dir($level_number) . 'solved.ini';
	if(is_file($solved_filename))
	{
		unlink($solved_filename);
	}
	$solver = new HexaHopSolverMemSave($start_state, new IniHashStorage($solved_filename));
	echo $start_state->map_info(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
	$steps = 0;
	$timestamp = hrtime(TRUE);
	$start_time = $timestamp;
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
		if(SLEEP_TIME > 0)
		{
			usleep(SLEEP_TIME);
			//sleep(1);
			//echo json_encode($solver->debug()), PHP_EOL;
		}
	}
	$endtime = hrtime(TRUE);
	echo PHP_EOL, 'Done!', PHP_EOL;
	if(is_file($solved_filename))
	{
		echo file_get_contents($solved_filename), PHP_EOL;
		echo 'Solved in ', number_format($steps, 0, '.', ' '), ' steps', PHP_EOL;
		$full_time = ($endtime - $start_time) / 1000000000;
		echo 'Solved in ', $full_time, ' secounds', PHP_EOL;
		if(SLEEP_TIME)
		{
			$sleep_time = SLEEP_TIME * $steps;
			echo ' - Sleeping ', $sleep_time, ' secounds, (', 100 * $sleep_time / $full_time, '%)', PHP_EOL;
			echo ' + Working ', ($full_time - $sleep_time), ' secounds, (', 100 - 100 * $sleep_time / $full_time, '%)', PHP_EOL;
		}
		if($full_time > 3600 * 24)
		{
			echo ' = ', $full_time / 3600 / 24, ' days', PHP_EOL;
		}
		else if($full_time > 3600)
		{
			echo ' = ', $full_time / 3600, ' hours', PHP_EOL;
		}
		else if($full_time > 60)
		{
			echo ' = ', $full_time / 60, ' minutes', PHP_EOL;
		}
		echo ' => ', $steps / $full_time, ' steps per secound', PHP_EOL;

		$stat_filename = dirname(__DIR__, 3) . '/data/stats.json';
		if(is_file($stat_filename))
		{
			$stats = (array) json_decode(file_get_contents($stat_filename), FALSE);
			if($stats[$level_number]->steps > 0)
			{
				if($stats[$level_number]->steps < $steps)
				{
					echo 'Step count worse than last time, then: ', $stats[$level_number]->steps, ', now: ', $steps, PHP_EOL;

				}
				else if($stats[$level_number]->steps > $steps)
				{
					echo 'Step count better than last time, then: ', $stats[$level_number]->steps, ', now: ', $steps, PHP_EOL;
				}
				else
				{
					echo 'Same step-count as last time', PHP_EOL;
				}
			}
			if(!$stats[$level_number]->steps || $stats[$level_number]->steps > $steps)
			{
				$stats[$level_number]->steps = $steps;
				$stats[$level_number]->time = $full_time;
				file_put_contents($stat_filename, json_encode($stats, JSON_PRETTY_PRINT));
			}
		}

		$full_solved_filename = dirname(__DIR__, 3) . '/data/solved/' . $level_number . '.ini';
		rename($solved_filename, $full_solved_filename);
		rmdir(dirname(__DIR__, 3) . '/data/' . $level_number);
	}
	else
	{
		echo 'Not solved :-(', PHP_EOL;
		die(1);
	}
