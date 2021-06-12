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
     * HexaHopMap constructor.
     * @param $level_number
     * @param null $path
     * @throws \JsonException
     */
    public function __construct($level_number, $path = null)
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

    /**
     * @param $level_number
     *
     * @return MapInfo
     * @throws \JsonException
     * @throws \JsonException
     */
    private static function read_map_info($level_number): MapInfo
    {
        static $json;
        if (!$json) {
            $json = self::list_maps();
        }

        return $json[$level_number];
    }

    /**
     * @return MapInfo[]
     * @throws \JsonException
     * @throws \JsonException
     */
    public static function list_maps(): array
    {
        $extra_index = 101;
        $maps = [];
        /** @var MapInfo $map_info */
        foreach (json_decode(self::getResource('hexahopmaps.json'), false, 512, JSON_THROW_ON_ERROR) as $map_info) {
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
     * @param string $filename
     *
     * @return string
     * @noinspection PhpSameParameterValueInspection
     */
    private static function getResource(string $filename): string
    {
        return file_get_contents(self::getResourcePath($filename));
    }

    /**
     * @param string $filename
     *
     * @return string
     */
    private static function getResourcePath(string $filename): string
    {
        return dirname(__DIR__, 3) . '/resources/' . $filename;
    }

    /**
     * @param MapStream $map_stream
     */
    private function parse_map(MapStream $map_stream): void
    {
        // Version(1), newline(1), par(4), diff(4)
        $map_stream->goto(10);

        $this->x_min = $map_stream->uint8();
        $this->x_max = $map_stream->uint8();
        $this->y_min = $map_stream->uint8();
        $this->y_max = $map_stream->uint8();

        // Player position: x(4), y(4)
        $map_stream->skip(8);

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

    /**
     * @param Point $point
     *
     * @return bool
     */
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

    /**
     * @param Point $current
     * @param int $direction
     * @param int $steps
     *
     * @return Projectile
     */
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

    /**
     * @param Point $point
     *
     * @return int tile
     */
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

    /**
     * @param Point $point
     * @param int $direction
     * @param int $old_tile
     */
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
                /** @var Point $damage */
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

                // TODO wall-test before or after?

                // Normal Ice
                foreach (range(1, 100) as $distance) {
                    $goal_point = $this->next_point($point, $direction, $distance);
                    if (($this->tiles[$goal_point->y][$goal_point->x] ?? 0) !== self::TILE_ICE) {
                        $this->move_into($goal_point, $direction, $old_tile);
                        return;
                    }
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
        };
        //</editor-fold>

        $this->wall_test($old_tile);
    }

    /**
     * @param $old_tile
     */
    private function wall_test($old_tile): void
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
     * @param $current
     * @param int $steps
     *
     * @return Projectile[]
     * @noinspection PhpSameParameterValueInspection
     */
    private function next_points($current, int $steps = 1): array
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
     * @return bool
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
     * @return bool
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
     *
     * @param MapState $other
     *
     * @return bool
     */
    public function better(MapState $other): bool
    {
        return $this->points < $other->points;
    }

    #[ArrayShape([
        'items' => "int[]",
        'map_info' => MapInfo::class,
        'player' => Player::class,
        'points' => "int",
        'tiles' => "int[][]",
        'x_max' => "int",
        'x_min' => "int",
        'y_max' => "int",
        'y_min' => "int",
    ])]
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

    /**
     * @param $json_option
     * @return bool|string
     * @throws \JsonException
     */
    public function map_info($json_option): bool|string
    {
        return json_encode($this->map_info, JSON_THROW_ON_ERROR | $json_option);
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

    /**
     * @return int
     */
    public function points(): int
    {
        return $this->points;
    }

    /**
     * @return int
     */
    public function par(): int
    {
        return $this->par;
    }

    /**
     * @param int $new_par
     */
    public function overridePar(int $new_par): void
    {
        $this->par = $new_par;
    }

    public function impossible(): bool
    {
        //<editor-fold desc="Init vars, counting stuff">
        $tile_types = $this->tile_type_count();
        $total_items = $this->item_count();

        $my_tiles = $this->tiles;
        $player_tile = $this->tiles[$this->player->y][$this->player->x] & self::MASK_TILE_TYPE;
        $player_on_green = $player_tile === self::TILE_LOW_GREEN || $player_tile === self::TILE_HIGH_GREEN;
        $missing_green = $tile_types[self::TILE_LOW_GREEN] + $tile_types[self::TILE_HIGH_GREEN];

        // Already won
        if (!$missing_green) {
            return false;
        }

        /** @var Projectile[] $reachable_lasers */
        $reachable_lasers = [];
        /** @var Point[] $reachable_builders */
        $reachable_builders = [];
        $reachable_boats = [];
        $reachable_rotaters = [];
        //</editor-fold>

        //<editor-fold desc="Par vs Steps + Greens">
        $minimum_cost = $missing_green + ($player_on_green ? 0 : 1);
        if ($tile_types[self::TILE_LASER]) {
            $minimum_cost--;
            if ($total_items[self::ITEM_JUMP]) {
                $minimum_cost -= 5 * 5 * $total_items[self::ITEM_JUMP];
            }
            if ($tile_types[self::TILE_ICE]) {
                $minimum_cost = 1;
            }
        }
        // Enough steps to step on all greens?
        if ($this->points + $minimum_cost > $this->par) {
            return true;
        }
        //</editor-fold>

        //<editor-fold desc="Init reachable">
        /** @var bool[][][] $reachable keys: z, y, x */
        $reachable = [];

        foreach ($my_tiles as $y => $row) {
            foreach ($row as $x => $tile_wi) {
                $reachable[0][$y][$x] = false;
                $reachable[1][$y][$x] = false;
                $my_tiles[$y][$x] = $tile_wi & self::MASK_TILE_TYPE;
            }
        }
        $reachable[$this->player->z][$this->player->y][$this->player->x] = true;
        //</editor-fold>

        //<editor-fold desc="Reachable functions">
        /**
         * @param bool $green_wall_lowerable
         * @param bool $blue_wall_lowerable
         *
         * @return bool
         */
        $expand_reachable = function (bool $green_wall_lowerable, bool $blue_wall_lowerable) use (
            &$my_tiles,
            &
            $reachable,
            &$reachable_lasers,
            &$tile_types,
            &$total_items
        ) {
            /** @var bool[] $trampolines prevent infinite loops of trampolines */
            $trampolines = [];
            /** @var Projectile[] $todo */
            $todo = [];
            /** @var Projectile[] $tested */
            $tested = [];
            foreach ($reachable as $z => $plane) {
                foreach ($plane as $y => $row) {
                    foreach ($row as $x => $reached) {
                        if ($reached) {
                            $todo[] = new Projectile($x, $y, $z, Projectile::DIR_J);
                        }
                    }
                }
            }

            while ($todo) {
                $todo = Point::unique($todo);
                $start_point = array_pop($todo);
                $start_point_key = (string)$start_point;
                if (isset($tested[$start_point_key])) {
                    continue;
                }
                $tested[$start_point_key] = $start_point;
                $neighbors = $this->next_points($start_point);
                $start_tile = $my_tiles[$start_point->y][$start_point->x] ?? self::TILE_WATER;
                if ($start_tile !== self::TILE_WATER) {
                    switch ($start_tile) {
                        case self::TILE_TRAMPOLINE:
                            $neighbors[] = $this->next_point($start_point, $start_point->dir, 2);
                            break;

                        case self::TILE_ROTATOR:
                            $neighbor_count = 0;
                            $rotating_trampoline = false;
                            $rotating_builder = false;
                            foreach ($neighbors as $neighbor) {
                                $neighbor_tile = $my_tiles[$neighbor->y][$neighbor->x] ?? 0;
                                // Double rotator can move about everywhere, rotated builder is a mess too
                                if ($neighbor_tile === self::TILE_ROTATOR) {
                                    return false;
                                }
                                if ($neighbor_tile === self::TILE_LASER) {
                                    // TODO $reachable_lasers
                                    return false;
                                }
                                if ($neighbor_tile !== self::TILE_WATER) {
                                    $neighbor_count++;
                                }
                                if ($neighbor_tile === self::TILE_TRAMPOLINE) {
                                    $rotating_trampoline = true;
                                }
                            }
                            // If at least one neighbor then all neighbor can be reached
                            if ($neighbor_count > 0) {
                                foreach ($neighbors as $neighbor) {
                                    $neighbor_tile = $my_tiles[$neighbor->y][$neighbor->x] ?? 0;
                                    // As it can be either low or high, treat it as an elevator
                                    if ($neighbor_tile !== self::TILE_BUILDABLE_WATER) {
                                        $tile_types[$neighbor_tile]--;
                                        $my_tiles[$neighbor->y][$neighbor->x] = self::TILE_BUILDABLE_WATER;
                                        $tile_types[self::TILE_BUILDABLE_WATER]++;
                                    }
                                }
                            }
                            if ($rotating_trampoline) {
                                foreach (range(0, 5) as $dir1) {
                                    $trampolinePoint = $this->next_point($start_point, $dir1);
                                    $trampolinePoint->z = 0;
                                    foreach (range(0, 5) as $dir2) {
                                        $neighbors[] = $this->next_point($trampolinePoint, $dir2);
                                        $neighbors[] = $this->next_point($trampolinePoint, $dir2, 2);
                                    }
                                }
                                $neighbors = Point::unique($neighbors);
                            }
                            if ($rotating_builder) {
                                foreach ($neighbors as $neighbor) {
                                    foreach (range(0, 5) as $dir1) {
                                        $buildPoint = $this->next_point($neighbor, $dir1);
                                        $neighbor_tile = $my_tiles[$buildPoint->y][$buildPoint->x] ?? 0;
                                        if (in_array($neighbor_tile, [self::TILE_WATER, self::TILE_LOW_GREEN], true)) {
                                            $tile_types[$neighbor_tile]--;
                                            $my_tiles[$neighbor->y][$neighbor->x] = self::TILE_BUILDABLE_WATER;
                                            $tile_types[self::TILE_BUILDABLE_WATER]++;
                                        }
                                    }
                                }
                            }
                            foreach($neighbors as $neighbor) {
                                $elevatedPoint = clone $neighbor;
                                $elevatedPoint->z = 1 - $elevatedPoint->z;
                                $neighbors[] = $elevatedPoint;
                            }
                            $neighbors = Point::unique($neighbors);
                            break;

                        case self::TILE_BUILD:
                            foreach ($neighbors as $neighbor) {
                                $neighbor_tile = $my_tiles[$neighbor->y][$neighbor->x] ?? 0;
                                if (in_array($neighbor_tile, [self::TILE_WATER, self::TILE_LOW_GREEN], true)) {
                                    $tile_types[$neighbor_tile]--;
                                    $my_tiles[$neighbor->y][$neighbor->x] = self::TILE_BUILDABLE_WATER;
                                    $tile_types[self::TILE_BUILDABLE_WATER]++;
                                }
                            }
                            foreach($neighbors as $neighbor) {
                                $elevatedPoint = clone $neighbor;
                                $elevatedPoint->z = 1 - $elevatedPoint->z;
                                $neighbors[] = $elevatedPoint;
                            }
                            $neighbors = Point::unique($neighbors);
                            break;
                    }
                    foreach ($neighbors as $point) {
                        $tile = $my_tiles[$point->y][$point->x] ?? 0;
                        if ($tile !== self::TILE_LASER && $tile !== self::TILE_TRAMPOLINE && !empty($reachable[$point->z][$point->y][$point->x])) {
                            continue;
                        }

                        switch ($tile) {
                            case self::TILE_LOW_GREEN:
                                // Green is only reachable, if there is a way to leave it.
                                if ($point->length === 1) {
                                    $green_neighbors = $this->next_points($point);
                                    $return_dir = $point->dir === Projectile::DIR_J ? $point->dir : ($point->dir + 3) % 6;
                                    $green_have_neighbors = false;
                                    foreach ($green_neighbors as $green_neighbor_point) {
                                        $green_neighbor_tile = $my_tiles[$green_neighbor_point->y][$green_neighbor_point->x] ?? 0;
                                        if ($green_neighbor_point->dir === $return_dir) {
                                            if ($green_neighbor_tile === self::TILE_LOW_GREEN || $green_neighbor_tile === self::TILE_HIGH_GREEN) {
                                                continue;
                                            }
                                        }
                                        if ($green_neighbor_tile !== self::TILE_WATER) {
                                            $green_have_neighbors = true;
                                            break;
                                        }
                                    }
                                    if (!$green_have_neighbors) {
                                        break;
                                    }
                                }
                                $reachable[0][$point->y][$point->x] = true;
                                $new_point = clone $point;
                                $new_point->z = 0;
                                $todo[] = $new_point;
                                break;

                            case self::TILE_HIGH_GREEN:
                                if ($point->z > 0) {
                                    $reachable[1][$point->y][$point->x] = true;
                                    $todo[] = $point;
                                }
                                if ($green_wall_lowerable) {
                                    $reachable[0][$point->y][$point->x] = true;
                                    $new_point = Point::copy($point);
                                    $new_point->z = 0;
                                    $todo[] = $new_point;
                                }
                                break;

                            case self::TILE_HIGH_BLUE:
                                if ($point->z > 0) {
                                    $reachable[1][$point->y][$point->x] = true;
                                    $todo[] = $point;
                                }
                                if ($blue_wall_lowerable) {
                                    $reachable[0][$point->y][$point->x] = true;
                                    $new_point = Point::copy($point);
                                    $new_point->z = 0;
                                    $todo[] = $new_point;
                                }
                                break;

                            // Always reach z 0 and z 1
                            case self::TILE_LOW_ELEVATOR:
                                $reachable[0][$point->y][$point->x] = true;
                                $reachable[1][$point->y][$point->x] = true;
                                $new_point = Point::copy($point);
                                $new_point->z = 1;
                                $todo[] = $new_point;
                                break;

                            // Each direction are different, don't save it as reached, just add todo
                            case self::TILE_TRAMPOLINE:
                                $reachable[0][$point->y][$point->x] = true;
                                $point_key = (string)$point;
                                if (isset($trampolines[$point_key])) {
                                    break;
                                }
                                $trampolines[$point_key] = true;
                                $todo[] = $point;
                                break;

                            // Each direction are different, don't save it as reached, just add todo
                            case self::TILE_LASER:
                                $reachable[0][$point->y][$point->x] = true;

                                if ($total_items[self::ITEM_JUMP]) {
                                    $jump_point = clone $point;
                                    $jump_point->dir = Projectile::DIR_J;
                                    $reachable_lasers[(string)$jump_point] = $jump_point;
                                    $todo[] = $point;
                                    break;
                                }

                                $point_key = (string)$point;
                                if (isset($reachable_lasers[$point_key])) {
                                    break;
                                }
                                $reachable_lasers[$point_key] = $point;
                                $todo[] = $point;
                                break;

                            case self::TILE_ROTATOR:
                            case self::TILE_ICE:
                            case self::TILE_BUILD:
                            case self::TILE_BOAT:
                            case self::TILE_ANTI_ICE:
                            case self::TILE_LOW_LAND:
                            case self::TILE_LOW_BLUE:
                                if (!$reachable[0][$point->y][$point->x]) {
                                    $reachable[0][$point->y][$point->x] = true;
                                    $new_point = clone $point;
                                    $new_point->z = 0;
                                    $todo[] = $new_point;
                                }
                                break;

                            case self::TILE_HIGH_ELEVATOR:
                            case self::TILE_HIGH_LAND:
                                if ($point->z > 0) {
                                    if (!$reachable[1][$point->y][$point->x]) {
                                        $reachable[1][$point->y][$point->x] = true;
                                        $todo[] = $point;
                                    }
                                }
                                break;

                            case self::TILE_BUILDABLE_WATER:
                                if ($point->z > 0) {
                                    if (!$reachable[$point->z][$point->y][$point->x]) {
                                        $reachable[$point->z][$point->y][$point->x] = true;
                                        $todo[] = $point;
                                    }
                                }
                                $new_point = clone $point;
                                $new_point->z = 0;
                                if (!$reachable[$new_point->z][$new_point->y][$new_point->x]) {
                                    $reachable[$new_point->z][$new_point->y][$new_point->x] = true;
                                    $todo[] = $new_point;
                                }
                                break;

                            case self::TILE_WATER:
                            default:
                                break;
                        }
                    }
                }
            }
            return true;
        };
        /**
         * @return bool[]
         */
        $wall_test = static function () use (&$my_tiles, &$reachable, &$tile_types) {
            if ($tile_types[self::TILE_HIGH_GREEN] === 0 && $tile_types[self::TILE_HIGH_BLUE] === 0) {
                return [false, false];
            }
            $unreached_high_green = 0;
            $unreached_low_green = 0;
            $unreached_low_blue = 0;
            foreach ($my_tiles as $y => $row) {
                foreach ($row as $x => $tile) {
                    switch ($tile) {
                        case self::TILE_HIGH_GREEN:
                            if (!$reachable[1][$y][$x] && !$reachable[0][$y][$x]) {
                                $unreached_high_green++;
                            }
                            break;

                        case self::TILE_LOW_GREEN:
                            if (!$reachable[0][$y][$x]) {
                                $unreached_low_green++;
                            }
                            break;

                        case self::TILE_LOW_BLUE:
                            if (!$reachable[0][$y][$x]) {
                                $unreached_low_blue++;
                            }
                            break;
                    }
                    if ($unreached_low_blue > 0 || $tile_types[self::TILE_HIGH_BLUE] === 0) {
                        if ($unreached_low_green > 0 || $tile_types[self::TILE_HIGH_GREEN] === 0) {
                            return [false, false];
                        }
                    }
                }
            }
            return [
                $tile_types[self::TILE_HIGH_GREEN] > 0 && $unreached_low_green === 0,
                $tile_types[self::TILE_HIGH_BLUE] > 0 && $unreached_low_blue === 0 && $unreached_high_green + $unreached_low_green > 0,
            ];
        };

        /**
         * @return bool|null
         */
        $doLasers = static function () use (&$my_tiles, &$reachable, &$reachable_lasers, &$tile_types) {
            if (!$reachable_lasers) {
                return null;
            }
            $destroyed_count = 0;

            /** @var Point[] $missing_greens */
            $missing_greens = [];
            /** @var Point[] $other_lasers */
            $other_lasers = [];
            /** @var Point[] $ice_tiles */
            $ice_tiles = [];
            /** @var Point[] $other_lasers */
            $explodeable_lasers = [];

            foreach ($my_tiles as $y => $row) {
                foreach ($row as $x => $tile) {
                    if (empty($reachable[0][$y][$x])) {
                        if ($tile === self::TILE_LOW_GREEN) {
                            $missing_greens[] = new Point($x, $y, 0);
                        } elseif ($tile === self::TILE_HIGH_GREEN) {
                            $missing_greens[] = new Point($x, $y, 0);
                        } elseif ($tile === self::TILE_LASER) {
                            $other_lasers[] = new Point($x, $y, 0);
                        }
                    }
                    if ($tile === self::TILE_ICE) {
                        $ice_tiles[] = new Point($x, $y, 0);
                    }
                }
            }

            if (!$missing_greens) {
                return false;
            }

            // Convert Laser Jump to 6 laser directions
            foreach ($reachable_lasers as $laser_point) {
                if ($laser_point->dir === Projectile::DIR_J) {
                    foreach (range(0, 5) as $dir) {
                        $l2 = clone $laser_point;
                        $l2->dir = $dir;
                        $l2_key = (string)$l2;
                        if (!isset($reachable_lasers[$l2_key])) {
                            $reachable_lasers[$l2_key] = $l2;
                        }
                    }
                    unset($reachable_lasers[(string)$laser_point]);
                }
            }

            // Handle Laser on ice, as more lasers
            $ice_todo = $ice_tiles;
            $ice_tested = [];
            while ($ice_todo) {
                $ice = array_shift($ice_todo);
                $hit_by_dir = [false, false, false, false, false, false];
                $dir_count = 0;
                foreach ($reachable_lasers as $laser_point) {
                    if (!$hit_by_dir[$laser_point->dir] && $laser_point->dirDistance($ice) > 0) {
                        $hit_by_dir[$laser_point->dir] = true;
                        $dir_count++;
                    }
                }
                if ($dir_count) {
                    $laser_count = 0;
                    $lasers_added = 0;
                    foreach (range(0, 5) as $dir) {
                        $ice_laser = Projectile::PointDir($ice, $dir);
                        $laser_key = (string)$ice_laser;
                        if (isset($reachable_lasers[$laser_key])) {
                            $laser_count++;
                            continue;
                        }

                        $left_dir = ($dir + 5) % 6;
                        $right_dir = ($dir + 1) % 6;
                        if (!$hit_by_dir[$left_dir] && !$hit_by_dir[$right_dir]) {
                            continue;
                        }

                        $reachable_lasers[$laser_key] = $ice_laser;
                        $lasers_added++;
                    }
                    if ($lasers_added) {
                        $laser_count += $lasers_added;
                        foreach ($ice_tested as $old_ice) {
                            $ice_todo[] = $old_ice;
                        }
                        $ice_tested = [];
                    }
                    if ($laser_count === 6) {
                        continue;
                    }
                }
                $ice_tested[] = $ice;
            }

            foreach ($reachable_lasers as $laser_point) {
                foreach ($missing_greens as $green_point_index => $green_point) {
                    if ($laser_point->dirDistance($green_point) > 0) {
                        $destroyed_count++;
                        $tile_types[$my_tiles[$green_point->y][$green_point->x]]--;
                        $tile_types[self::TILE_LOW_ELEVATOR]++;
                        $my_tiles[$green_point->y][$green_point->x] = self::TILE_LOW_ELEVATOR;
                        unset($missing_greens[$green_point_index]);
                        if (!$missing_greens) {
                            return false;
                        }
                    }
                }
            }
            if (!$other_lasers) {
                return null;
            }
            foreach ($reachable_lasers as $laser_point) {
                foreach ($other_lasers as $other_point_index => $other_point) {
                    if ($laser_point->dirDistance($other_point) > 0) {
                        $explodeable_lasers[] = $other_point;
                        unset($other_lasers[$other_point_index]);
                    }
                }
            }
            if (!$explodeable_lasers) {
                return null;
            }

            foreach ($explodeable_lasers as $laser_point) {
                foreach ($missing_greens as $green_point_index => $green_point) {
                    $distance = Projectile::BetweenPoints($laser_point, $green_point);
                    if ($distance && $distance->length === 1) {
                        $destroyed_count++;
                        $tile_types[$my_tiles[$green_point->y][$green_point->x]]--;
                        $tile_types[self::TILE_LOW_ELEVATOR]++;
                        $my_tiles[$green_point->y][$green_point->x] = self::TILE_LOW_ELEVATOR;
                        unset($missing_greens[$green_point_index]);
                        if (!$missing_greens) {
                            return false;
                        }
                    }
                }
            }

            if ($destroyed_count > 0) {
                return true;
            }

            return null;
        };

        $expand_with_lasers = static function ($green_wall_lowerable, $blue_wall_lowerable) use (
            &$expand_reachable,
            &
            $doLasers
        ) {
            if ($expand_reachable($green_wall_lowerable, $blue_wall_lowerable) === false) {
                return false;
            }
            while (($laser_status = $doLasers()) === true) {
                if ($expand_reachable($green_wall_lowerable, $blue_wall_lowerable) === false) {
                    return false;
                }
            }
            /** @noinspection IfReturnReturnSimplificationInspection */
            if ($laser_status === false) {
                return false;
            }
            return true;
        };
        //</editor-fold>

        //<editor-fold desc="Reachable logic">
        if ($expand_with_lasers(false, false) === false) {
            return false;
        }
        [$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();

        if ($green_wall_lowerable) {
            if ($expand_with_lasers(true, false) === false) {
                return false;
            }
            [$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();
        }
        if ($blue_wall_lowerable) {
            if ($expand_with_lasers($green_wall_lowerable, true) === false) {
                return false;
            }
            if (!$green_wall_lowerable) {
                [$green_wall_lowerable, $blue_wall_lowerable] = $wall_test();
                if ($green_wall_lowerable && $expand_with_lasers(true, true) === false) {
                    return false;
                }
            }
        }

        if ($blue_wall_lowerable) {
            $minimum_cost += 10 * $tile_types[self::TILE_LOW_BLUE];
            if ($this->points + $minimum_cost > $this->par) {
                return true;
            }
        }
        //</editor-fold>

        //<editor-fold desc="Count reachable by type">
        $reachable_types = array_fill(0, 17, 0);
        //$unreachable_types = array_fill(0, 17, 0);
        foreach ($my_tiles as $y => $row) {
            foreach ($row as $x => $tile) {
                if (!empty($reachable[0][$y][$x]) || !empty($reachable[1][$y][$x])) {
                    $reachable_types[$tile]++;
                }
                /*
                else
                {
                    $unreachable_types[$tile]++;
                }
                */
            }
        }
        //</editor-fold>

        // We need to end the game on a none-green
        if (!$reachable_lasers && array_sum(
                $reachable_types
            ) === $reachable_types[self::TILE_LOW_GREEN] + $reachable_types[self::TILE_HIGH_GREEN]) {
            return true;
        }

        //<editor-fold desc="Boats - Abort, no calculation implemented">
        // If any boat is reachable, skip the rest of the calculations
        if ($reachable_types[self::TILE_BOAT] > 0) {
            return false;
        }
        //</editor-fold>

        //<editor-fold desc="Was all green Reachable">
        foreach ($my_tiles as $y => $row) {
            foreach ($row as $x => $tile) {
                if ($tile === self::TILE_LOW_GREEN || $tile === self::TILE_HIGH_GREEN) {
                    if (empty($reachable[0][$y][$x]) && empty($reachable[1][$y][$x])) {
                        return true;
                    }
                }
            }
        }
        //</editor-fold>

        return false;
    }

    /**
     * @return int[]
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

    /**
     * @return Player
     */
    public function player(): Player
    {
        return clone $this->player;
    }

    /**
     * @return MapInfo
     */
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
}
