<?php
/** @noinspection TypoSafeNamingInspection */

namespace Puggan\Solver\HexaHop;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use Puggan\Solver\Entities\JSON\MapInfo;
use Puggan\Solver\Entities\Player;
use Puggan\Solver\Entities\Point;
use Puggan\Solver\Entities\Projectile;
use Puggan\Solver\MapState;

class HexaHopMap extends MapState implements \JsonSerializable
{
    public const TILE_WATER = 0;
    public const TILE_LOW_LAND = 1;
    public const TILE_LOW_GREEN = 2;
    public const TILE_HIGH_GREEN = 3;
    public const TILE_TRAMPOLINE = 4;
    public const TILE_ROTATOR = 5;
    public const TILE_HIGH_LAND = 6;
    public const TILE_LOW_BLUE = 7;
    public const TILE_HIGH_BLUE = 8;
    public const TILE_LASER = 9;
    public const TILE_ICE = 10;
    public const TILE_ANTI_ICE = 11;
    public const TILE_BUILD = 12;
    //private const TILE_UNKNOWN_13 = 13;
    public const TILE_BUILDABLE_WATER = 13;
    public const TILE_BOAT = 14;
    public const TILE_LOW_ELEVATOR = 15;
    public const TILE_HIGH_ELEVATOR = 16;

    public const ITEM_ANTI_ICE = 1;
    public const ITEM_JUMP = 2;

    public const MASK_TILE_TYPE = 0x1F;
    public const MASK_ITEM_TYPE = 0xE0;
    public const SHIFT_TILE_ITEM = 5;

    /** @var MapInfo $map_info */
    protected MapInfo $map_info;

    /** @var int x_min */
    protected int $x_min;

    /** @var int x_max */
    protected int $x_max;

    /** @var int y_min */
    protected int $y_min;

    /** @var int y_max */
    protected int $y_max;

    /** @var int[][] */
    protected array $tiles = [];

    /** @var int[] */
    protected array $items = [];

    /** @var Player player */
    protected Player $player;

    /** @var int points */
    protected int $points;

    /** @var int */
    protected int $par;

    /**
     * @param int $level_number
     * @param ?int[] $path
     */
    public function __construct($level_number, ?array $path = null)
    {
        $this->map_info = self::read_map_info($level_number);
        if (!$this->map_info) {
            throw new \RuntimeException('invalid level_number. ' . $level_number);
        }

        $this->points = 0;
        $this->par = $this->map_info->par;
        $this->items[self::ITEM_ANTI_ICE] = 0;
        $this->items[self::ITEM_JUMP] = 0;
        $this->player = new Player($this->map_info->start_x, $this->map_info->start_y, 0);

        $this->parse_map(new MapStream(self::getResourcePath('levels/' . $this->map_info->file)));

        if ($this->high_tile($this->player)) {
            $this->player->z = 1;
        }

        if ($path) {
            foreach ($path as $move) {
                $this->non_pure_move($move);
            }
        }
    }

    //<editor-fold desc="Implement MapState">

    private static function read_map_info(int $level_number): ?MapInfo
    {
        static $json;
        if (!$json) {
            $json = self::list_maps();
        }

        return $json[$level_number] ?? null;
    }

    /**
     * @return MapInfo[]
     */
    public static function list_maps(): array
    {
        $extra_index = 101;
        $maps = [];
        $rawMaps = self::getResource('hexahopmaps.json');
        try {
            /** @var array<int, MapInfo> $jsonMaps */
            $jsonMaps = json_decode($rawMaps, false, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException('json failed: ' . $e->getMessage() . ' on ' . $rawMaps);
        }
        foreach ($jsonMaps as $map_info) {
            if ($map_info->level_number < 0) {
                $maps[$extra_index++] = $map_info;
            } else {
                if (isset($maps[$map_info->level_number])) {
                    throw new \RuntimeException('Duplicate map at ' . $map_info->level_number);
                }
                $maps[$map_info->level_number] = new MapInfo($map_info);
            }
        }
        ksort($maps);
        return $maps;
    }

    /**
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getResource(string $filename): string
    {
        return file_get_contents(self::getResourcePath($filename));
    }

    private static function getResourcePath(string $filename): string
    {
        return dirname(__DIR__, 3) . '/resources/' . $filename;
    }

    private function parse_map(MapStream $map_stream): void
    {
        // Version(1), newline(1), par(4), diff(4)
        $map_stream->goto(10);

        $this->x_min = $map_stream->uint8();
        $this->x_max = $map_stream->uint8();
        $this->y_min = $map_stream->uint8();
        $this->y_max = $map_stream->uint8();

        // Player position: x(4), y(4)
        $px = $map_stream->uint32();
        $py = $map_stream->uint32();
        if ($this->map_info->start_x !== $px) {
            throw new \RuntimeException('wrong start position, found X: ' . $px . ' expected ' . $this->map_info->start_x);
        }
        if ($this->map_info->start_y !== $py) {
            throw new \RuntimeException('wrong start position, found Y: ' . $py . ' expected ' . $this->map_info->start_y);
        }

        $this->tiles = array_fill(
            $this->y_min - 1,
            $this->y_max - $this->y_min + 3,
            array_fill(
                $this->x_min - 1,
                $this->x_max - $this->x_min + 3,
                self::TILE_WATER
            )
        );
        foreach (range($this->x_min, $this->x_max) as $x) {
            foreach (range($this->y_min, $this->y_max) as $y) {
                /* 4 bit item, 4 bit map */
                $this->tiles[$y][$x] = $map_stream->uint8();
            }
        }
    }

    public function high_tile(Point $point): bool
    {
        // out of bounds or water
        if (empty($this->tiles[$point->y][$point->x])) {
            return false;
        }

        return match ($this->tiles[$point->y][$point->x] & self::MASK_TILE_TYPE) {
            self::TILE_WATER,
            self::TILE_LOW_ELEVATOR,
            self::TILE_BUILDABLE_WATER,
            self::TILE_TRAMPOLINE,
            self::TILE_ROTATOR,
            self::TILE_LASER,
            self::TILE_ICE,
            self::TILE_BUILD,
            self::TILE_BOAT,
            self::TILE_ANTI_ICE,
            self::TILE_LOW_LAND,
            self::TILE_LOW_GREEN,
            self::TILE_LOW_BLUE => false,

            self::TILE_HIGH_ELEVATOR,
            self::TILE_HIGH_LAND,
            self::TILE_HIGH_GREEN,
            self::TILE_HIGH_BLUE => true,

            default => throw new \RuntimeException('Unknown title: ' . $this->tiles[$point->y][$point->x]),
        };
    }
    //</editor-fold>

    /**
     * Make a move in the current state
     *
     * @param int $direction move/direction to travel
     */
    protected function non_pure_move(int $direction): void
    {
        if ($direction === Projectile::DIR_J) {
            if ($this->items[self::ITEM_JUMP] < 1) {
                $this->player->alive = false;

                return;
            }
            $this->items[self::ITEM_JUMP]--;
        }
        $next_point = $this->next_point($this->player, $direction);
        $old_tile = $this->move_out_of($this->player);
        $this->points++;
        $this->move_into($next_point, $direction, $old_tile);
    }

    private function next_point(Point $current, int $direction, int $steps = 1): Projectile
    {
        $new_point = Projectile::PointDir($current, $direction, $steps);
        switch ($direction) {
            case Projectile::DIR_N:
                $new_point->y -= $steps;

                return $new_point;

            case Projectile::DIR_NE:
                $new_point->x += $steps;
                $new_point->y -= $steps;

                return $new_point;

            case Projectile::DIR_SE:
                $new_point->x += $steps;

                return $new_point;

            case Projectile::DIR_S:
                $new_point->y += $steps;

                return $new_point;

            case Projectile::DIR_SW:
                $new_point->x -= $steps;
                $new_point->y += $steps;

                return $new_point;

            case Projectile::DIR_NW:
                $new_point->x -= $steps;

                return $new_point;

            case Projectile::DIR_J:
                return $new_point;
        }
        throw new \RuntimeException('Bad direction: ' . $direction);
    }

    private function move_out_of(Point $point): int
    {
        $tile = $this->tiles[$point->y][$point->x];
        switch ($tile & self::MASK_TILE_TYPE) {
            case self::TILE_LOW_GREEN:
            case self::TILE_HIGH_GREEN:
                $this->tiles[$point->y][$point->x] = self::TILE_WATER;
                break;

            case self::TILE_LOW_BLUE:
                $this->tiles[$point->y][$point->x] = self::TILE_LOW_GREEN;
                $this->points += 10;
                break;

            case self::TILE_HIGH_BLUE:
                $this->tiles[$point->y][$point->x] = self::TILE_HIGH_GREEN;
                $this->points += 10;
                break;

            case self::TILE_ANTI_ICE:
                $this->tiles[$point->y][$point->x] = self::TILE_LOW_BLUE;
                break;
        }

        return $tile;
    }

    private function move_into(Point $point, int $direction, int $old_tile): void
    {
        $this->player->x = $point->x;
        $this->player->y = $point->y;

        //<editor-fold desc="Out of bounds">
        if (empty($this->tiles[$point->y][$point->x])) {
            $this->player->alive = false;
            $this->player->z = 0;

            return;
        }
        //</editor-fold>

        $tile_and_item = $this->tiles[$point->y][$point->x];
        $tile = $tile_and_item & self::MASK_TILE_TYPE;

        //<editor-fold desc="Item">
        $item = $tile_and_item >> self::SHIFT_TILE_ITEM;
        if ($item) {
            $this->items[$item]++;
            $this->tiles[$point->y][$point->x] = $tile;
        }
        //</editor-fold>

        if ($point->z < 1) {
            switch ($tile) {
                case self::TILE_HIGH_GREEN:
                case self::TILE_HIGH_LAND:
                case self::TILE_HIGH_BLUE:
                case self::TILE_HIGH_ELEVATOR:
                    $this->player->alive = false;

                    return;
            }
        }

        switch ($tile) {
            case self::TILE_WATER:
                $this->player->alive = false;
                $this->player->z = 0;

                return;

            case self::TILE_LOW_ELEVATOR:
                $this->tiles[$point->y][$point->x] = self::TILE_HIGH_ELEVATOR;
                break;

            case self::TILE_HIGH_ELEVATOR:
                $this->tiles[$point->y][$point->x] = self::TILE_LOW_ELEVATOR;
                break;

            case self::TILE_TRAMPOLINE:
                $this->wall_test($old_tile);
                if ($direction === Projectile::DIR_J) {
                    break;
                }
                $goal_point = $this->next_point($point, $direction, 2);
                // if jumping from a high place, skip height tests
                if ($this->player->z <= 0) {
                    $mid_point = $this->next_point($point, $direction);
                    if ($this->high_tile($mid_point)) {
                        break;
                    }
                    if ($this->high_tile($goal_point)) {
                        $this->move_into($mid_point, $direction, $old_tile);
                        return;
                    }
                }

                $this->move_into($goal_point, $direction, $tile);
                return;

            case self::TILE_ROTATOR:
                $swap_points = $this->next_points($point);
                $swap_in_tile = ($this->tiles[$swap_points[5]->y][$swap_points[5]->x] ?? 0) & self::MASK_TILE_TYPE;
                foreach ($swap_points as $swap_point) {
                    $swap_out_tile = ($this->tiles[$swap_point->y][$swap_point->x] ?? 0);
                    $item = $swap_out_tile & self::MASK_ITEM_TYPE;
                    $swap_out_tile -= $item;
                    $this->tiles[$swap_point->y][$swap_point->x] = $swap_in_tile + $item;
                    $swap_in_tile = $swap_out_tile;
                }
                break;

            case self::TILE_LASER:
                /** @var Projectile[] $projectiles */
                $projectiles = [];
                /** @var Projectile[] $todos */
                $todos = [];
                /** @var Point[] $damage */
                $damage = [];
                if ($direction === Projectile::DIR_J) {
                    $todos = [
                        Projectile::PointDir($point, 0),
                        Projectile::PointDir($point, 1),
                        Projectile::PointDir($point, 2),
                        Projectile::PointDir($point, 3),
                        Projectile::PointDir($point, 4),
                        Projectile::PointDir($point, 5),
                    ];
                } else {
                    $todos[] = Projectile::PointDir($point, $direction);
                }

                while ($todos) {
                    $todo_projectile = array_pop($todos);
                    $todo_key = "{$todo_projectile->x}:{$todo_projectile->y}:{$todo_projectile->dir}";
                    if (isset($projectiles[$todo_key])) {
                        continue;
                    }
                    $projectiles[$todo_key] = $todo_projectile;
                    $hit_point = $this->next_point($todo_projectile, $todo_projectile->dir);
                    $hit_key = "{$hit_point->x}:{$hit_point->y}";
                    $hit_tile = ($this->tiles[$hit_point->y][$hit_point->x] ?? -1);
                    switch ($hit_tile) {
                        case -1:
                            break;

                        case self::TILE_ICE:
                            $todos[] = Projectile::PointDir($hit_point, ($todo_projectile->dir + 5) % 6);
                            $todos[] = Projectile::PointDir($hit_point, ($todo_projectile->dir + 1) % 6);
                            break;

                        case self::TILE_WATER:
                            $todos[] = Projectile::PointDir($hit_point, $todo_projectile->dir);
                            break;

                        default:
                            $damage[$hit_key] = $hit_point;
                            break;
                    }
                }

                $damage_by_tile_type = array_fill(0, 17, 0);
                foreach ($damage as $hit_point) {
                    $hit_tile = ($this->tiles[$hit_point->y][$hit_point->x] ?? self::TILE_WATER) & self::MASK_TILE_TYPE;
                    $damage_by_tile_type[$hit_tile]++;
                    switch ($hit_tile) {
                        case self::TILE_WATER:
                            break;

                        case self::TILE_LASER:
                            $this->tiles[$hit_point->y][$hit_point->x] = self::TILE_WATER;
                            foreach ($this->next_points($hit_point) as $extra_hit_point) {
                                $damage_by_tile_type[$this->tiles[$extra_hit_point->y][$extra_hit_point->x] ?? 0]++;
                                $this->tiles[$extra_hit_point->y][$extra_hit_point->x] = self::TILE_WATER;
                            }
                            break;

                        default:
                            $this->tiles[$hit_point->y][$hit_point->x] = self::TILE_WATER;
                            break;
                    }
                }

                if (!$this->tiles[$point->y][$point->x]) {
                    $this->player->alive = false;
                }

                if ($damage_by_tile_type[self::TILE_LOW_GREEN]) {
                    $this->wall_test(self::TILE_LOW_GREEN);
                }
                if ($damage_by_tile_type[self::TILE_LOW_BLUE]) {
                    $this->wall_test(self::TILE_LOW_BLUE);
                }

                // Water and green tiles give 0 point, all other give 10 points
                unset($damage_by_tile_type[self::TILE_WATER], $damage_by_tile_type[self::TILE_LOW_GREEN], $damage_by_tile_type[self::TILE_HIGH_GREEN]);
                $this->points += 10 * array_sum($damage_by_tile_type);
                break;

            case self::TILE_ICE:
                // Anti Ice?
                if ($this->items[self::ITEM_ANTI_ICE] > 0) {
                    $this->items[self::ITEM_ANTI_ICE]--;
                    $this->tiles[$point->y][$point->x] = self::TILE_ANTI_ICE;
                    break;
                }

                $this->wall_test($old_tile);

                // Normal Ice
                foreach (range(1, 100) as $distance) {
                    $goal_point = $this->next_point($point, $direction, $distance);
                    $goal_tile = ($this->tiles[$goal_point->y][$goal_point->x] ?? 0);
                    if ($goal_tile === self::TILE_ICE) {
                        continue;
                    }

                    if (!$this->high_tile($goal_point)) {
                        $this->move_into($goal_point, $direction, $old_tile);
                    }

                    return;
                }
                break;

            case self::TILE_BUILD:
                $high_built = 0;
                $low_built = 0;
                foreach ($this->next_points($point) as $build_point) {
                    $build_tile = ($this->tiles[$build_point->y][$build_point->x] ?? -1) & self::MASK_TILE_TYPE;
                    if ($build_tile === self::TILE_LOW_GREEN) {
                        $this->tiles[$build_point->y][$build_point->x] += self::TILE_HIGH_GREEN - self::TILE_LOW_GREEN;
                        $high_built++;
                    } elseif ($build_tile === self::TILE_WATER) {
                        $this->tiles[$build_point->y][$build_point->x] += self::TILE_LOW_GREEN - self::TILE_WATER;
                        $low_built++;
                    }
                }
                if ($high_built && !$low_built) {
                    $this->wall_test(self::TILE_LOW_GREEN);
                }
                break;

            case self::TILE_BOAT:
                $end_point = $point;
                foreach (range(1, 20) as $steps) {
                    $test_point = $this->next_point($point, $direction, $steps);
                    $test_tile = $this->tiles[$test_point->y][$test_point->x] ?? -1;
                    if ($test_tile > 0) {
                        $test_tile &= self::MASK_TILE_TYPE;
                    }
                    if ($test_tile > 0) {
                        break;
                    }
                    $end_point = $test_point;
                    if ($test_tile < 0) {
                        $this->player->alive = false;
                        break;
                    }
                }
                if ($end_point !== $point) {
                    $this->tiles[$point->y][$point->x] -= self::TILE_BOAT;
                    if (isset($this->tiles[$end_point->y][$end_point->x])) {
                        $this->tiles[$end_point->y][$end_point->x] += self::TILE_BOAT;
                    }
                    $this->player->x = $end_point->x;
                    $this->player->y = $end_point->y;
                }

                break;
            /*
            case self::TILE_ANTI_ICE:
            case self::TILE_LOW_LAND:
            case self::TILE_LOW_GREEN:
            case self::TILE_LOW_BLUE:
            case self::TILE_HIGH_LAND:
            case self::TILE_HIGH_GREEN:
            case self::TILE_HIGH_BLUE:
                 // no effect on enter
                 break;
            */
        }

        //<editor-fold desc="set height (z)">
        $this->player->z = match ($tile) {
            self::TILE_ANTI_ICE,
            self::TILE_BOAT,
            self::TILE_BUILD,
            self::TILE_HIGH_ELEVATOR,
            self::TILE_ICE,
            self::TILE_LASER,
            self::TILE_LOW_BLUE,
            self::TILE_LOW_GREEN,
            self::TILE_LOW_LAND,
            self::TILE_ROTATOR,
            self::TILE_TRAMPOLINE,
            self::TILE_WATER => 0,

            self::TILE_HIGH_BLUE,
            self::TILE_HIGH_GREEN,
            self::TILE_HIGH_LAND,
            self::TILE_LOW_ELEVATOR => 1,

            default => throw new \RuntimeException('Unknown tile: ' . $tile)
        };
        //</editor-fold>

        $this->wall_test($old_tile);
    }

    private function wall_test(int $old_tile): void
    {
        switch ($old_tile & self::MASK_TILE_TYPE) {
            case self::TILE_LOW_BLUE:
                $this->blue_wall_test();
                return;

            case self::TILE_LOW_GREEN:
                $this->green_wall_test();
                return;
        }
    }

    public function blue_wall_test(): void
    {
        foreach ($this->tiles as $row) {
            foreach ($row as $tile_with_item) {
                if (($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_LOW_BLUE) {
                    return;
                }
            }
        }
        foreach ($this->tiles as $y => $row) {
            foreach ($row as $x => $tile_with_item) {
                if (($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_HIGH_BLUE) {
                    $this->tiles[$y][$x] += self::TILE_LOW_BLUE - self::TILE_HIGH_BLUE;
                }
            }
        }
    }

    public function green_wall_test(): void
    {
        foreach ($this->tiles as $row) {
            foreach ($row as $tile_with_item) {
                if (($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_LOW_GREEN) {
                    return;
                }
            }
        }

        foreach ($this->tiles as $y => $row) {
            foreach ($row as $x => $tile_with_item) {
                if (($tile_with_item & self::MASK_TILE_TYPE) === self::TILE_HIGH_GREEN) {
                    $this->tiles[$y][$x] += self::TILE_LOW_GREEN - self::TILE_HIGH_GREEN;
                }
            }
        }
    }

    /**
     * @return array<int, Projectile>
     * @noinspection PhpSameParameterValueInspection
     */
    private function next_points(Point $current, int $steps = 1): array
    {
        $points = [];
        foreach (range(0, 5) as $direction) {
            $new_point = Projectile::PointDir($current, $direction, $steps);
            switch ($direction) {
                case Projectile::DIR_N:
                    $new_point->y -= $steps;
                    break;

                case Projectile::DIR_NE:
                    $new_point->x += $steps;
                    $new_point->y -= $steps;
                    break;

                case Projectile::DIR_SE:
                    $new_point->x += $steps;
                    break;

                case Projectile::DIR_S:
                    $new_point->y += $steps;
                    break;

                case Projectile::DIR_SW:
                    $new_point->x -= $steps;
                    $new_point->y += $steps;
                    break;

                case Projectile::DIR_NW:
                    $new_point->x -= $steps;
                    break;
            }

            $points[] = $new_point;
        }
        return $points;
    }

    /**
     * Player have won
     */
    #[Pure]
    public function won(): bool
    {
        if ($this->lost()) {
            return false;
        }

        foreach ($this->tiles as $row) {
            foreach ($row as $tile) {
                if (($tile & 0x1e) === self::TILE_LOW_GREEN) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Player have lost
     */
    public function lost(): bool
    {
        return !$this->player->alive || $this->points > $this->par;
    }

    /**
     * The game allows thus moves to be executed
     * May include moves that make the player lose
     * @return int[]
     */
    public function possible_moves(): array
    {
        $moves = [];
        foreach ($this->next_points($this->player) as $dir => $point) {
            $tile = ($this->tiles[$point->y][$point->x] ?? self::TILE_WATER) & self::MASK_TILE_TYPE;
            if ($tile !== self::TILE_WATER) {
                $moves[] = $dir;
            }
        }
        if ($this->items[self::ITEM_JUMP] > 0) {
            $moves[] = Projectile::DIR_J;
        }
        return $moves;
    }

    /**
     * @return string uniq state hash, used to detect duplicates
     * @throws \JsonException
     */
    public function hash(): string
    {
        return md5(json_encode([$this->player, $this->items, $this->tiles], JSON_THROW_ON_ERROR));
    }

    /**
     * is the current state better that this other state?
     */
    public function better(MapState $other): bool
    {
        if (!$other instanceof self) {
            throw new \RuntimeException('Invalid map state');
        }
        return $this->points < $other->points;
    }

    /**
     * @return array{items: int[], map_info: MapInfo, player: Player, points: int, tiles: int[][], x_max: int, x_min: int, y_max: int, y_min: int}
     */
    public function jsonSerialize(): array
    {
        return [
            'map_info' => $this->map_info,
            'x_min' => $this->x_min,
            'x_max' => $this->x_max,
            'y_min' => $this->y_min,
            'y_max' => $this->y_max,
            'tiles' => $this->tiles,
            'items' => $this->items,
            'player' => $this->player,
            'points' => $this->points,
        ];
    }

    public function __clone()
    {
        $this->player = clone $this->player;
    }

    public function map_info(int $json_option): bool|string
    {
        try {
            return json_encode($this->map_info, JSON_THROW_ON_ERROR | $json_option);
        } catch (\JsonException $e) {
            throw new \RuntimeException('json failed: ' . $e->getMessage(), previous: $e);
        }
    }

    public function print_path(array $path): string
    {
        $dir = [];
        foreach ($path as $move) {
            switch ($move) {
                case Projectile::DIR_N:
                    $dir[] = 'N';
                    break;
                case Projectile::DIR_NE:
                    $dir[] = 'NE';
                    break;
                case Projectile::DIR_SE:
                    $dir[] = 'SE';
                    break;
                case Projectile::DIR_S:
                    $dir[] = 'S';
                    break;
                case Projectile::DIR_SW:
                    $dir[] = 'SW';
                    break;
                case Projectile::DIR_NW:
                    $dir[] = 'NW';
                    break;
                case Projectile::DIR_J:
                    $dir[] = 'Jump';
                    break;
                default:
                    $dir[] = '?' . $move;
                    break;
            }
        }

        return implode(', ', $dir);
    }

    public function points(): int
    {
        return $this->points;
    }

    public function par(): int
    {
        return $this->par;
    }

    public function overridePar(int $new_par): void
    {
        $this->par = $new_par;
    }

    public function impossible(): bool
    {
        $reachableTiles = new ReachableTiles($this);

        $missingGreen = $reachableTiles->tileTypes[self::TILE_LOW_GREEN] + $reachableTiles->tileTypes[self::TILE_HIGH_GREEN];

        // Already won
        if (!$missingGreen) {
            return false;
        }

        if ($reachableTiles->minPoints() > $this->par) {
            return true;
        }

        $canLowerBlue = $reachableTiles->minPoints(true) <= $this->par;

        $result = $reachableTiles->expand($canLowerBlue);
        if ($result) {
            return false;
        }

        return true;
    }

    /**
     * @return int[]
     * @phpstan-return array<int<0, 16>, int<0, max>>
     */
    public function tile_type_count(): array
    {
        $c = array_fill(0, 17, 0);
        foreach ($this->tiles as $row) {
            foreach ($row as $tile) {
                $c[$tile & self::MASK_TILE_TYPE]++;
            }
        }
        return $c;
    }

    /**
     * @return int[]
     * @phpstan-return array<int<1, 2>, int<1, max>>
     */
    public function item_count(): array
    {
        $c = $this->items;
        foreach ($this->tiles as $row) {
            foreach ($row as $tile) {
                $item_shifted = $tile & self::MASK_ITEM_TYPE;
                if ($item_shifted) {
                    $c[$item_shifted >> self::SHIFT_TILE_ITEM]++;
                }
            }
        }
        return $c;
    }

    public function player(): Player
    {
        return clone $this->player;
    }

    public function info(): MapInfo
    {
        return clone $this->map_info;
    }

    /**
     * @return int[][]
     */
    public function tiles(): array
    {
        return $this->tiles;
    }

    public function maxDistance(): int
    {
        return $this->x_max - $this->x_min + $this->y_max - $this->y_min;
    }

    public function isInside(Point $point): bool {
        if ($point->x < $this->x_min) {
            return false;
        }
        if ($point->x > $this->x_max) {
            return false;
        }
        if ($point->y < $this->y_min) {
            return false;
        }
        if ($point->y > $this->y_max) {
            return false;
        }
        return true;
    }
}
