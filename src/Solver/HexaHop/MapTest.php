#!/usr/bin/env php
<?php

use Puggan\Solver\HexaHop\HexaHopMap;
use Puggan\Views\Bash;

/** @noinspection PhpIncludeInspection parameter levels seams to be ignored https://youtrack.jetbrains.com/issue/WI-35143 */
require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

(static function (?string $id, ?string $path_str, ?string $printEveryStep) {
    if (!$id) {
        echo 'No ID', PHP_EOL;
        die(1);
    }

    $view = new Bash();

    $maps = HexaHopMap::list_maps();

    if (empty($maps[$id]->title)) {
        echo 'Bad map, no title', PHP_EOL;
        die(1);
    }

    /** @noinspection NonSecureHtmlentitiesUsageInspection */
    $map_info = (object)array_map('htmlentities', (array)$maps[$id]);
    $map = new HexaHopMap((int) $id);

    $alive = true;
    if ($map->impossible()) {
        echo 'Impossible (at start)', PHP_EOL;
        //$alive = false;
    }

    $path = [];
    if ($path_str) {
        $path = array_map('intval', explode(',', $path_str));
    }

    if ($path) {
        $directions = ['North', 'North-East', 'South-East', 'South', ' South-West', 'North-West', 'Jump'];
        foreach (array_values($path) as $index => $dir) {
            if (empty($directions[$dir])) {
                break;
            }

            if ($printEveryStep) {
                echo $view->header($map), 'Moving ', $directions[$dir], PHP_EOL, $view->map($map), PHP_EOL;
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

    echo $view->header($map);
    $viewMap = $view->map($map);

    if (!$alive) {
        echo $viewMap, PHP_EOL;
        die(254);
    }

    if (!$map->won()) {
        echo 'Step: ', count($path), ', Points: ', $map->points(), ' / ', $map->par(), PHP_EOL;
        echo 'Still alive', PHP_EOL, $viewMap, PHP_EOL;
        die(253);
    }

    if ($map->points() < $map->par()) {
        echo 'Beat Par', PHP_EOL, $viewMap, PHP_EOL;
        die(1);
    }

    echo $viewMap, PHP_EOL;
    die(0);
})(
    $argv[1] ?? null,
    $argv[2] ?? null,
    $argv[3] ?? null
);
