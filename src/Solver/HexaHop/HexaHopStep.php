#!/usr/bin/env php
<?php

namespace Puggan\Solver\HexaHop;

define('DISPLAY_STEPS', 1000);
define('SLEEP_TIME', 1000);

if ($argc < 2) {
    die('$level_number missing');
}

(static function (int $level_number) {
    /** @noinspection PhpIncludeInspection parameter levels seams to be ignored https://youtrack.jetbrains.com/issue/WI-35143 */
    require_once dirname(__DIR__, 3) . '/vendor/autoload.php';

    $pid = getmypid();

    $solver = new HexaHopSolver($level_number);
    echo $solver->map_info(JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
    $steps = 0;
    $timestamp = hrtime(true);
    while ($solver->step($pid)) {
        echo '.';
        if (++$steps % DISPLAY_STEPS === 0) {
            $old = $timestamp;
            $timestamp = hrtime(true);
            echo $steps, ' @ ', (DISPLAY_STEPS * 1e9 / ($timestamp - $old)), PHP_EOL;
        }
        usleep(SLEEP_TIME);
    }
    echo PHP_EOL, 'Done!', PHP_EOL;
    echo file_get_contents(dirname(__DIR__, 3) . '/data/' . $level_number . '/solved.ini'), PHP_EOL;
})(
    +$argv[1]
);
