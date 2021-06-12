#!/usr/bin/env php
<?php

use Puggan\Solver\HexaHop\HexaHopMap;

/** @noinspection PhpIncludeInspection parameter levels seams to be ignored https://youtrack.jetbrains.com/issue/WI-35143 */
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

(static function (?string $id, ?string $path_str) {
    if (!$id) {
        echo 'No ID', PHP_EOL;
        die(1);
    }

    $maps = HexaHopMap::list_maps();

    if (empty($maps[$id]->title)) {
        echo 'Bad map, no title', PHP_EOL;
        die(1);
    }

    /** @noinspection NonSecureHtmlentitiesUsageInspection */
    $map_info = (object)array_map('htmlentities', (array)$maps[$id]);
    $map = new HexaHopMap($id);

    if (!$map) {
        echo 'Bad map', PHP_EOL;
        die(1);
    }

    $alive = true;
    if ($map->impossible()) {
        echo 'Impossible (at start)', PHP_EOL;
        $alive = false;
    }

    $path = [];
    if ($path_str) {
        $path = array_map('intval', explode(',', $path_str));
    }

    if ($alive && $path) {
        $directions = ['North', 'North-East', 'South-East', 'South', ' South-West', 'North-West', 'Jump'];
        foreach (array_values($path) as $index => $dir) {
            if (empty($directions[$dir])) {
                break;
            }

            /** @var HexaHopMap $map */
            $map = $map->move($dir);

            if ($map->won()) {
                echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
                echo 'Won', PHP_EOL;
                $alive = true;
                break;
            }
            if ($map->lost()) {
                echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
                echo 'Lost', PHP_EOL;
                $alive = false;
                break;
            }
            if ($map->impossible()) {
                echo 'Step: ', ($index + 1), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
                echo 'Impossible', PHP_EOL;
                $alive = false;
                break;
            }
        }
    }

    if ($alive) {
        if (!$map->won()) {
            echo 'Step: ', count($path), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
            echo 'Still alive', PHP_EOL;
            die(253);
        }

        if ($map->points() < $map->par()) {
            echo 'Beat Par', PHP_EOL;
            die(1);
        }
    } else {
        die(254);
    }
})(
    $argv[1] ?? null,
    $argv[2] ?? null
);
