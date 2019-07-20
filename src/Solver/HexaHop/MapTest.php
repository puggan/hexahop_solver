#!/usr/bin/env php
<?php

	use PHPDoc\MapInfo;
	use Puggan\Solver\HexaHop\HexaHopMap;

	require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

	$id = $argv[1] ?? NULL;
	$path_str = $argv[2] ?? NULL;

	if(!$id)
	{
		echo 'No ID';
		die(1);
	}

	/** @var MapInfo[] $maps */
	$maps = HexaHopMap::list_maps();

	if(empty($maps[$id]->title))
	{
		echo 'Bad map, no title';
		die(1);
	}

	/** @noinspection NonSecureHtmlentitiesUsageInspection */
	$map_info = (object) array_map('htmlentities', (array) $maps[$id]);
	$map = new HexaHopMap($id);

	if(!$map)
	{
		echo 'Bad map';
		die(1);
	}

	$path = [];
	if($path_str || $path_str === '0')
	{
		if(is_array($path_str))
		{
			$path = array_map('intval', $path_str);
		}
		else if(is_string($path_str))
		{
			$path = array_map('intval', explode(',', $path_str));
		}
	}

	$alive = true;

	if($path)
	{
		$directions = ['North', 'North-East', 'South-East', 'South', ' South-West', 'North-West', 'Jump'];
		foreach(array_values($path) as $index => $dir)
		{
			if(empty($directions[$dir]))
			{
				break;
			}

			/** @var HexaHopMap $map */
			$map = $map->move($dir);

			if($map->won())
			{
				echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
				echo 'Won', PHP_EOL;
				$alive = true;
				break;
			}
			if($map->lost())
			{
				echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
				echo 'Lost', PHP_EOL;
				$alive = false;
				break;
			}
			if($map->impossible())
			{
				echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
				echo 'Impossible', PHP_EOL;
				$alive = false;
				break;
			}
		}
	}

	if($alive)
	{
		if(!$map->won())
		{
			echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
			echo 'Still alive', PHP_EOL;
			die(-2);
		}

		if($map->points() < $map->par())
		{
			echo 'Beat Par', PHP_EOL;
			die(1);
		}
	}
	else
	{
		die(-1);
	}
