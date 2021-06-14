#!/bin/env php
<?php

(static function(int $preSleep, int $keyDelay) {
    $basePath = dirname(__DIR__);
    $mapInfo = json_decode(file_get_contents($basePath . '/resources/hexahopmaps.json'));
    $mapInfo = array_column($mapInfo, null, "level_number");
    $testSolved = json_decode(file_get_contents($basePath . '/test/solved.json'), true);
    $mapPaths = $testSolved;

    foreach (range(1, 100) as $mapId) {
        $solvedPath = "{$basePath}/data/{$mapId}/solved.ini";
        if (!is_file($solvedPath)) {
            continue;
        }

        $mapPath = file($solvedPath)[0] ?? '';

        if (!preg_match('#="([0-6](,[0-6])+)"#', $mapPath, $m)) {
            continue;
        }

        if (empty($mapPaths[$mapId])) {
            echo "Missing solution, Map {$mapId}: {$m[1]}", PHP_EOL;
        }

        $mapPaths[$mapId] = $m[1];
    }

    ksort($mapPaths);
    foreach ($mapPaths as $mapId => $mapPath) {
        $mapTitle = $mapInfo[$mapId]->title;
        $mapTitleSafe = trim(preg_replace('#[^0-9A-Za-z]#', '-', $mapTitle), '-');
        $ahk = strtr(',' . $mapPath, [
            ',0' => 'w',
            ',1' => 'e',
            ',2' => 'd',
            ',3' => 's',
            ',4' => 'a',
            ',5' => 'q',
            ',6' => '{space}',
        ]);
        $ahkScript = <<<AHK_BLOCK
; Hex-a-Hop level {$mapId}: {$mapTitle}
SetKeyDelay {$keyDelay} ;
Sleep {$preSleep}000 ;
WinActivateBottom "Hex-a-hop" ;
Send {$ahk} ;

AHK_BLOCK;
        file_put_contents(__DIR__ . "/hex-a-hop-{$mapId}-{$mapTitleSafe}.ahk", $ahkScript);

        $xdo = strtr(',' . $mapPath, [
            ',0' => 'w',
            ',1' => 'e',
            ',2' => 'd',
            ',3' => 's',
            ',4' => 'a',
            ',5' => 'q',
            ',6' => ' ',
        ]);

        $xdoScript = <<<XDO_BLOCK
sleep {$preSleep}
xdotool type --delay={$keyDelay} "{$xdo}"

XDO_BLOCK;
        file_put_contents(__DIR__ . "/hex-a-hop-{$mapId}-{$mapTitleSafe}.xdo.sh", $xdoScript);
    }
})(2, 200);
