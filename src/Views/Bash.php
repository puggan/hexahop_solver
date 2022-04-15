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
        HexaHopMap::TILE_LASER => 'LS',
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

    /**
     * @param ?bool[][][] $reached
     */
    public function map(HexaHopMap $map, ?array $reached = null): string
    {
        $stringMap = [];
        $player = $map->player();
        // TODO adjust
        $playerCol = $player->x;
        $playerRow = $player->y * 2 - 1 + $player->x;
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

                if ($reached && !empty($reached[0][$yPos][$xPos])) {
                    $stringMap[$row][$col] = "\e[0;30;42m" . $stringMap[$row][$col] . "\e[0m";
                }


                if ($item) {
                    if ($minRow >= $row) {
                        $minRow = $row - 1;
                    }
                    $stringMap[$row - 1][$col] = self::$fieldSymbols[$item] ?? 'i?';
                }

                if ($reached && !empty($reached[1][$yPos][$xPos])) {
                    if ($minRow >= $row) {
                        $minRow = $row - 1;
                    }
                    $stringMap[$row - 1][$col] = "\e[0;30;42m" . ($stringMap[$row - 1][$col] ?? self::$fieldSymbols[0]) . "\e[0m";
                }
            }
        }
        $stringMap[$playerRow][$playerCol] = "\e[0;0;44m" . ($player->z ? 'PL' : 'pl') . "\e[0m";

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
