<?php

declare(strict_types=1);

namespace Puggan\Views;

use Puggan\Solver\HexaHop\HexaHopMap;

class Bash extends View
{
    /** @var string[] */
    static array $fieldSymbols = [
        HexaHopMap::TILE_ANTI_ICE => 'ai',
        HexaHopMap::TILE_BOAT => 'bo',
        HexaHopMap::TILE_BUILD => 'bu',
        HexaHopMap::TILE_HIGH_BLUE => 'BL',
        HexaHopMap::TILE_HIGH_ELEVATOR => 'EL',
        HexaHopMap::TILE_HIGH_GREEN => 'GR',
        HexaHopMap::TILE_HIGH_LAND => 'LA',
        HexaHopMap::TILE_ICE => 'ic',
        HexaHopMap::TILE_LASER => 'la',
        HexaHopMap::TILE_LOW_BLUE => 'bl',
        HexaHopMap::TILE_LOW_ELEVATOR => 'el',
        HexaHopMap::TILE_LOW_GREEN => 'gr',
        HexaHopMap::TILE_LOW_LAND => 'la',
        HexaHopMap::TILE_ROTATOR => 'ro',
        HexaHopMap::TILE_TRAMPOLINE => 'tr',
        HexaHopMap::TILE_WATER => '  ',

        HexaHopMap::ITEM_ANTI_ICE << HexaHopMap::SHIFT_TILE_ITEM => 'iI',
        HexaHopMap::ITEM_JUMP << HexaHopMap::SHIFT_TILE_ITEM => 'iJ',
    ];

    public function header(HexaHopMap $map): string
    {
        $info = $map->info();
        $points = $map->points();
        $parPrefix = $points ? $points . ' /' : 'par:';
        return "=== #{$info->level_number}: {$info->title} [{$parPrefix} {$info->par}] ===\n";
    }

    public function map(HexaHopMap $map): string
    {
        $stringMap = [];
        $player = $map->player();
        // TODO adjust
        $playerCol = $player->x;
        $playerRow = $player->y * 2 - 1 + $player->x;
        $stringMap[$playerRow][$playerCol] = $player->z ? 'PL' : 'pl';
        $maxCol = $playerCol;
        $maxRow = $playerRow;
        $minCol = $playerCol;
        $minRow = $playerRow;
        foreach ($map->tiles() as $yPos => $tileRow) {
            foreach ($tileRow as $xPos => $tile) {
                if (!$tile) {
                    continue;
                }

                $item = $tile & HexaHopMap::MASK_ITEM_TYPE;
                $tileType = $tile & HexaHopMap::MASK_TILE_TYPE;

                // TODO adjust
                $col = $xPos;
                $row = $yPos * 2 + $xPos;

                if ($minRow > $row) {
                    $minRow = $row;
                }
                if ($maxRow < $row) {
                    $maxRow = $row;
                }
                if ($minCol > $col) {
                    $minCol = $col;
                }
                if ($maxCol < $col) {
                    $maxCol = $col;
                }

                $stringMap[$row][$col] = self::$fieldSymbols[$tileType] ?? '??';

                if (!$item) {
                    continue;
                }

                if ($minRow >= $row) {
                    $minRow = $row - 1;
                }
                $stringMap[$row - 1][$col] = self::$fieldSymbols[$item] ?? 'i?';
            }
        }

        $mapString = '╔══' . str_repeat('═══', $maxCol - $minCol) . '══╗' . PHP_EOL;
        foreach (range($minRow, $maxRow) as $row) {
            foreach (range($minCol, $maxCol) as $col) {
                if (empty($stringMap[$row][$col])) {
                    $stringMap[$row][$col] = self::$fieldSymbols[0];
                }
            }
            ksort($stringMap[$row]);
            $mapString .= '║ ' . implode(' ', $stringMap[$row]) . ' ║' . PHP_EOL;
        }
        $mapString .= '╚══' . str_repeat('═══', $maxCol - $minCol) . '══╝';
        return $mapString;
    }
}