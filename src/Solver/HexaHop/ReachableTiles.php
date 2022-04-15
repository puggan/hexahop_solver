<?php

namespace Puggan\Solver\HexaHop;

use Puggan\Solver\Entities\Player;
use Puggan\Solver\Entities\Point;
use Puggan\Solver\Entities\Projectile;

class ReachableTiles
{
    /** @var int<0, 16>[][] keys: y, x */
    public array $myTiles = [];

    /** @var bool[][][] keys: z, y, x */
    public array $reachable = [];

    /** @var Projectile[] */
    public array $reachableLasers = [];

    /**
     * @var int[]
     * @phpstan-var array<int<0, 16>, int<0, max>>
     */
    public  array $tileTypes;

    /**
     * @var int[]
     * @phpstan-var array<int<1, 2>, int<1, max>>
     */
    public array $totalItems;

    public Player $player;

    /**
     * @var int
     * @phpstan-var int<0, 16>
     */
    public int $playerTile;
    public bool $allLowGreenReached = false;
    public bool $allLowBlueReached = false;

    public function __construct(
        private HexaHopMap $map
    )
    {
        $this->initMyTiles();
        $this->player = $map->player();
        $this->reachable[$this->player->z][$this->player->y][$this->player->x] = true;

        $this->tileTypes = $map->tile_type_count();
        $this->totalItems = $map->item_count();

        $this->playerTile = $this->myTiles[$this->player->y][$this->player->x];

        $this->wallTest();
    }

    private function initMyTiles(): void
    {
        foreach ($this->map->tiles() as $y => $row) {
            foreach ($row as $x => $tileWi) {
                $this->reachable[0][$y][$x] = false;
                $this->reachable[1][$y][$x] = false;
                $tile = $tileWi & HexaHopMap::MASK_TILE_TYPE;
                if ($tile < 0 || $tile > 16) {
                    throw new \RuntimeException('Unknown tile');
                }
                $this->myTiles[$y][$x] = $tile;
            }
        }
    }

    public function minPoints(bool $lowerBlueWalls = false): int
    {
        $basePoints = $this->map->points();
        $greenCost = $this->tileTypes[HexaHopMap::TILE_LOW_GREEN] + $this->tileTypes[HexaHopMap::TILE_HIGH_GREEN];
        if (in_array($this->playerTile, [HexaHopMap::TILE_LOW_GREEN, HexaHopMap::TILE_HIGH_GREEN])) {
            $greenCost--;
        }

        if (!$this->tileTypes[HexaHopMap::TILE_LASER]) {
            if ($lowerBlueWalls) {
                return $basePoints + 1 + $greenCost + 11 * $this->tileTypes[HexaHopMap::TILE_LOW_BLUE];
            }
            return $basePoints + 1 + $greenCost;
        }

        if ($lowerBlueWalls) {
            $basePoints += 10 * $this->tileTypes[HexaHopMap::TILE_LOW_BLUE];
        }

        // With laser and ice, you can destroy allot with just one step
        if ($this->tileTypes[HexaHopMap::TILE_ICE]) {
            return $basePoints + 1;
        }

        if (!$this->tileTypes[HexaHopMap::ITEM_JUMP]) {
            return $basePoints + $greenCost;
        }

        // 1 Jump = up to 6 green, so a 5 points discount for each jump used
        $laserJumpDiscount = 5 * $this->totalItems[HexaHopMap::ITEM_JUMP];

        // If discount is larger than number of greens, count how may jump item we need to use.
        if ($laserJumpDiscount > $greenCost) {
            return $basePoints + (int) ceil($greenCost / 6);
        }

        return $basePoints + $greenCost - $laserJumpDiscount;
    }

    /**
     * @return bool true => all reachable
     * @phpstan-impure
     */
    public function expandReachable(bool $lowerGreenWalls, bool $lowerBlueWalls): bool
    {
        /** @var bool[] $trampolines prevent infinite loops of trampolines */
        $trampolines = [];
        /** @var Projectile[] $todo */
        $todo = [];
        /** @var Projectile[] $tested */
        $tested = [];

        // build todo
        foreach ($this->reachable as $z => $plane) {
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

            $startPoint = array_pop($todo);
            $startKey = (string)$startPoint;
            if (isset($tested[$startKey])) {
                continue;
            }

            $tested[$startKey] = $startPoint;
            $neighbors = Projectile::neighbours($startPoint);
            $start_tile = $this->myTiles[$startPoint->y][$startPoint->x] ?? HexaHopMap::TILE_WATER;

            if ($start_tile === HexaHopMap::TILE_WATER) {
                continue;
            }

            switch ($start_tile) {
                case HexaHopMap::TILE_TRAMPOLINE:
                    $neighbors[] = Projectile::PointDir($startPoint, $startPoint->dir, 2)->endPoint();
                    break;

                case HexaHopMap::TILE_ROTATOR:
                    $neighbor_count = 0;
                    $rotating_trampoline = false;
                    $rotating_builder = false;
                    foreach ($neighbors as $neighbor) {
                        $neighbor_tile = $this->myTiles[$neighbor->y][$neighbor->x] ?? 0;

                        // Double rotator can move about everywhere, rotated builder is a mess too
                        if ($neighbor_tile === HexaHopMap::TILE_ROTATOR) {
                            return true;
                        }
                        // Rotatable Laser
                        if ($neighbor_tile === HexaHopMap::TILE_LASER) {
                            // TODO $reachable_lasers
                            return true;
                        }
                        if ($neighbor_tile !== HexaHopMap::TILE_WATER) {
                            $neighbor_count++;
                        }
                        if ($neighbor_tile === HexaHopMap::TILE_TRAMPOLINE) {
                            $rotating_trampoline = true;
                        }
                        if ($neighbor_tile === HexaHopMap::TILE_BUILD) {
                            $rotating_builder = true;
                        }
                        // Rotateable boat
                        if ($neighbor_tile === HexaHopMap::TILE_BOAT) {
                            // TODO $reachable_boats
                            return true;
                        }
                    }
                    // If at least one neighbor then all neighbor can be reached
                    if ($neighbor_count > 0) {
                        foreach ($neighbors as $neighbor) {
                            $neighbor_tile = $this->myTiles[$neighbor->y][$neighbor->x] ?? 0;
                            // As it can be either low or high, treat it as an elevator
                            if ($neighbor_tile !== HexaHopMap::TILE_BUILDABLE_WATER) {
                                if ($this->tileTypes[$neighbor_tile]) {
                                    $this->tileTypes[$neighbor_tile]--;
                                }
                                $this->myTiles[$neighbor->y][$neighbor->x] = HexaHopMap::TILE_BUILDABLE_WATER;
                                $this->tileTypes[HexaHopMap::TILE_BUILDABLE_WATER]++;
                            }
                        }
                    }
                    if ($rotating_trampoline) {
                        foreach(Projectile::neighbours($startPoint) as $trampolineLocation) {
                            $trampolineLocation->z = 0;
                            // jump 1 step:
                            foreach(Projectile::neighbours($trampolineLocation) as $jumpLocation) {
                                $neighbors[] = $jumpLocation;
                            }
                            // jump 2 steps:
                            foreach(Projectile::neighbours($trampolineLocation, 2) as $jumpLocation) {
                                $neighbors[] = $jumpLocation;
                            }
                        }
                        $neighbors = Point::unique($neighbors);
                    }
                    if ($rotating_builder) {
                        foreach ($neighbors as $neighbor) {
                            foreach(Projectile::neighbours($neighbor) as $buildLocation) {
                                $buildTile = $this->myTiles[$buildLocation->y][$buildLocation->x];
                                if (in_array($buildTile, [HexaHopMap::TILE_WATER, HexaHopMap::TILE_LOW_GREEN], true)) {
                                    if ($this->tileTypes[$buildTile]) {
                                        $this->tileTypes[$buildTile]--;
                                    }
                                    $this->myTiles[$neighbor->y][$neighbor->x] = HexaHopMap::TILE_BUILDABLE_WATER;
                                    $this->tileTypes[HexaHopMap::TILE_BUILDABLE_WATER]++;
                                }
                            }
                        }
                    }
                    foreach ($neighbors as $neighbor) {
                        $elevatedPoint = clone $neighbor;
                        $elevatedPoint->z = 1 - $elevatedPoint->z;
                        $neighbors[] = $elevatedPoint;
                    }
                    $neighbors = Point::unique($neighbors);
                    break;

                case HexaHopMap::TILE_BUILD:
                    foreach ($neighbors as $neighbor) {
                        $neighbor_tile = $this->myTiles[$neighbor->y][$neighbor->x] ?? 0;
                        if (in_array($neighbor_tile, [HexaHopMap::TILE_WATER, HexaHopMap::TILE_LOW_GREEN], true)) {
                            if ($this->tileTypes[$neighbor_tile]) {
                                $this->tileTypes[$neighbor_tile]--;
                            }
                            $this->myTiles[$neighbor->y][$neighbor->x] = HexaHopMap::TILE_BUILDABLE_WATER;
                            $this->tileTypes[HexaHopMap::TILE_BUILDABLE_WATER]++;
                        }
                    }
                    foreach ($neighbors as $neighbor) {
                        $elevatedPoint = clone $neighbor;
                        $elevatedPoint->z = 1 - $elevatedPoint->z;
                        $neighbors[] = $elevatedPoint;
                    }
                    $neighbors = Point::unique($neighbors);
                    break;

                case HexaHopMap::TILE_LOW_ELEVATOR:
                    /** @var Projectile $new_point workaround, phpstan think new_point may be a Point instead */
                    $new_point = clone $startPoint;
                    $new_point->z = 1;
                    $todo[] = $new_point;
                    break;
            }
            foreach ($neighbors as $point) {
                $tile = $this->myTiles[$point->y][$point->x] ?? 0;
                if (!empty($this->reachable[$point->z][$point->y][$point->x]) && !in_array($tile, [HexaHopMap::TILE_LASER, HexaHopMap::TILE_TRAMPOLINE, HexaHopMap::TILE_BOAT])) {
                    continue;
                }

                switch ($tile) {
                    case HexaHopMap::TILE_LOW_GREEN:
                        // Green is only reachable, if there is a way to leave it.
                        if ($point->length === 1) {
                            $return_dir = $point->dir === Projectile::DIR_J ? $point->dir : ($point->dir + 3) % 6;
                            $green_have_neighbors = false;
                            $n = [];
                            foreach (Projectile::neighbours($point) as $green_neighbor_point) {
                                $green_neighbor_tile = $this->myTiles[$green_neighbor_point->y][$green_neighbor_point->x] ?? 0;
                                $n[] = [$green_neighbor_point->dir === $return_dir, $green_neighbor_tile, $green_neighbor_point];
                                if ($green_neighbor_point->dir === $return_dir) {
                                    if ($green_neighbor_tile === HexaHopMap::TILE_LOW_GREEN || $green_neighbor_tile === HexaHopMap::TILE_HIGH_GREEN) {
                                        continue;
                                    }
                                }
                                if ($green_neighbor_tile !== HexaHopMap::TILE_WATER) {
                                    $green_have_neighbors = true;
                                    break;
                                }
                            }
                            if (!$green_have_neighbors) {
                                break;
                            }
                        }
                        $this->reachable[0][$point->y][$point->x] = true;
                        $new_point = clone $point;
                        $new_point->z = 0;
                        $todo[] = $new_point;
                        break;

                    case HexaHopMap::TILE_HIGH_GREEN:
                        if ($point->z > 0) {
                            $this->reachable[1][$point->y][$point->x] = true;
                            $todo[] = $point;
                        }
                        if ($lowerGreenWalls) {
                            $this->reachable[0][$point->y][$point->x] = true;
                            $new_point = clone $point;
                            $new_point->z = 0;
                            $todo[] = $new_point;
                        }
                        break;

                    case HexaHopMap::TILE_HIGH_BLUE:
                        if ($point->z > 0) {
                            $this->reachable[1][$point->y][$point->x] = true;
                            $todo[] = $point;
                        }
                        if ($lowerBlueWalls) {
                            $this->reachable[0][$point->y][$point->x] = true;
                            $new_point = clone $point;
                            $new_point->z = 0;
                            $todo[] = $new_point;
                        }
                        break;

                    // Always reach z 0 and z 1
                    case HexaHopMap::TILE_LOW_ELEVATOR:
                        $this->reachable[0][$point->y][$point->x] = true;
                        $this->reachable[1][$point->y][$point->x] = true;
                        $new_point = clone $point;
                        $new_point->z = 1;
                        $todo[] = $new_point;
                        break;

                    // Each direction are different, don't save it as reached, just add todo
                    case HexaHopMap::TILE_TRAMPOLINE:
                        $this->reachable[0][$point->y][$point->x] = true;
                        $point_key = (string)$point;
                        if (isset($trampolines[$point_key])) {
                            break;
                        }
                        $trampolines[$point_key] = true;
                        $todo[] = $point;
                        break;

                    // Each direction are different, don't save it as reached, just add todo
                    case HexaHopMap::TILE_LASER:
                        $this->reachable[0][$point->y][$point->x] = true;

                        if (!empty($this->totalItems[HexaHopMap::ITEM_JUMP])) {
                            $jump_point = clone $point;
                            $jump_point->dir = Projectile::DIR_J;
                            $this->reachableLasers[(string)$jump_point] = $jump_point;
                            $todo[] = $point;
                            break;
                        }

                        $point_key = (string)$point;
                        if (isset($this->reachableLasers[$point_key])) {
                            break;
                        }
                        $this->reachableLasers[$point_key] = $point;
                        $todo[] = $point;
                        break;

                    case HexaHopMap::TILE_BOAT:
                        $boatLocation = clone $point;
                        $boatDestination = $point->endPoint();
                        $boatDestination->z = 0;
                        foreach (range(0, $this->map->maxDistance()) as $boatDistance) {
                            if (!$this->map->isInside($boatDestination)) {
                                break;
                            }
                            $destinationTile = $this->myTiles[$boatDestination->y][$boatDestination->x] ?? 0;
                            if ($destinationTile) {
                                $todo[] = $boatLocation;
                                $boatTile = $this->myTiles[$boatLocation->y][$boatLocation->x] ?? 0;
                                if (in_array($boatTile, [HexaHopMap::TILE_WATER, HexaHopMap::TILE_LOW_GREEN, HexaHopMap::TILE_LOW_BLUE, HexaHopMap::TILE_HIGH_GREEN, HexaHopMap::TILE_HIGH_BLUE, HexaHopMap::TILE_BUILDABLE_WATER])) {
                                    $this->myTiles[$boatLocation->y][$boatLocation->x] = HexaHopMap::TILE_BOAT;
                                }
                            }
                            $boatLocation = $boatDestination;
                            $boatDestination = $boatDestination->endPoint();
                        }
                        break;
                    case HexaHopMap::TILE_ROTATOR:
                    case HexaHopMap::TILE_ICE:
                    case HexaHopMap::TILE_BUILD:
                    case HexaHopMap::TILE_ANTI_ICE:
                    case HexaHopMap::TILE_LOW_LAND:
                    case HexaHopMap::TILE_LOW_BLUE:
                        if (!$this->reachable[0][$point->y][$point->x]) {
                            $this->reachable[0][$point->y][$point->x] = true;
                            $new_point = clone $point;
                            $new_point->z = 0;
                            $todo[] = $new_point;
                        }
                        break;

                    case HexaHopMap::TILE_HIGH_ELEVATOR:
                    case HexaHopMap::TILE_HIGH_LAND:
                        if ($point->z > 0) {
                            if (!$this->reachable[1][$point->y][$point->x]) {
                                $this->reachable[1][$point->y][$point->x] = true;
                                $todo[] = $point;
                            }
                        }
                        break;

                    case HexaHopMap::TILE_BUILDABLE_WATER:
                        if ($point->z > 0) {
                            if (!$this->reachable[$point->z][$point->y][$point->x]) {
                                $this->reachable[$point->z][$point->y][$point->x] = true;
                                $todo[] = $point;
                            }
                        }
                        $new_point = clone $point;
                        $new_point->z = 0;
                        if (!$this->reachable[$new_point->z][$new_point->y][$new_point->x]) {
                            $this->reachable[$new_point->z][$new_point->y][$new_point->x] = true;
                            $todo[] = $new_point;
                        }
                        break;

                    case HexaHopMap::TILE_WATER:
                    default:
                        break;
                }
            }
        }

        return $this->greenReached();
    }

    public function wallTest(): void
    {
        if ($this->tileTypes[HexaHopMap::TILE_HIGH_GREEN] === 0 && $this->tileTypes[HexaHopMap::TILE_HIGH_BLUE] === 0) {
            $this->allLowBlueReached = false;
            $this->allLowGreenReached = false;
            return;
        }

        // unreached:
        $highGreen = 0;
        $lowGreen = 0;
        $lowBlue = 0;

        foreach ($this->myTiles as $y => $row) {
            foreach ($row as $x => $tile) {
                switch ($tile) {
                    case HexaHopMap::TILE_HIGH_GREEN:
                        if (!$this->reachable[1][$y][$x] && !$this->reachable[0][$y][$x]) {
                            $highGreen++;
                        }
                        break;

                    case HexaHopMap::TILE_LOW_GREEN:
                        if (!$this->reachable[0][$y][$x]) {
                            $lowGreen++;
                        }
                        break;

                    case HexaHopMap::TILE_LOW_BLUE:
                        if (!$this->reachable[0][$y][$x]) {
                            $lowBlue++;
                        }
                        break;
                }
            }
        }

        $this->allLowGreenReached = $this->tileTypes[HexaHopMap::TILE_HIGH_GREEN] > 0 && !$lowGreen;
        $this->allLowBlueReached = $this->tileTypes[HexaHopMap::TILE_HIGH_BLUE] > 0 && !$lowBlue && $highGreen + $lowGreen > 0;
    }

    /**
     * @return bool|null
     *   no lasers -> null
     *   won -> false
     *   destroy all greens -> false
     *   all lasers reached, and greens left -> null
     *   greens left, and unreached lasers --> true
     */
    public function triggerLasers(): ?bool
    {
        if (!$this->reachableLasers) {
            return null;
        }

        $destroyedCount = 0;

        /** @var Point[] $missingGreens */
        $missingGreens = [];
        /** @var Point[] $otherLasers */
        $otherLasers = [];
        /** @var Point[] $iceTiles */
        $iceTiles = [];
        /** @var Point[] $explodableLasers */
        $explodableLasers = [];

        foreach ($this->myTiles as $y => $row) {
            foreach ($row as $x => $tile) {
                if (empty($this->reachable[0][$y][$x])) {
                    if ($tile === HexaHopMap::TILE_LOW_GREEN) {
                        $missingGreens[] = new Point($x, $y, 0);
                    } elseif ($tile === HexaHopMap::TILE_HIGH_GREEN) {
                        $missingGreens[] = new Point($x, $y, 0);
                    } elseif ($tile === HexaHopMap::TILE_LASER) {
                        $otherLasers[] = new Point($x, $y, 0);
                    }
                }
                if ($tile === HexaHopMap::TILE_ICE) {
                    $iceTiles[] = new Point($x, $y, 0);
                }
            }
        }

        if (!$missingGreens) {
            return false;
        }

        // Convert Laser Jump to 6 laser directions
        foreach ($this->reachableLasers as $laser_point) {
            if ($laser_point->dir === Projectile::DIR_J) {
                foreach (range(0, 5) as $dir) {
                    $l2 = clone $laser_point;
                    $l2->dir = $dir;
                    $l2_key = (string)$l2;
                    if (!isset($this->reachableLasers[$l2_key])) {
                        $this->reachableLasers[$l2_key] = $l2;
                    }
                }
                unset($this->reachableLasers[(string)$laser_point]);
            }
        }

        // Handle Laser on ice, as more lasers
        $iceTodo = $iceTiles;
        $iceTested = [];
        while ($iceTodo) {
            $ice = array_shift($iceTodo);
            $hit_by_dir = [false, false, false, false, false, false];
            $dir_count = 0;
            foreach ($this->reachableLasers as $laser_point) {
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
                    if (isset($this->reachableLasers[$laser_key])) {
                        $laser_count++;
                        continue;
                    }

                    $left_dir = ($dir + 5) % 6;
                    $right_dir = ($dir + 1) % 6;
                    if (!$hit_by_dir[$left_dir] && !$hit_by_dir[$right_dir]) {
                        continue;
                    }

                    $this->reachableLasers[$laser_key] = $ice_laser;
                    $lasers_added++;
                }
                if ($lasers_added) {
                    $laser_count += $lasers_added;
                    foreach ($iceTested as $old_ice) {
                        $iceTodo[] = $old_ice;
                    }
                    $iceTested = [];
                }
                if ($laser_count === 6) {
                    continue;
                }
            }
            $iceTested[] = $ice;
        }

        foreach ($this->reachableLasers as $laser_point) {
            foreach ($missingGreens as $green_point_index => $green_point) {
                if (!$laser_point->dirDistance($green_point)) {
                    continue;
                }

                $destroyedCount++;
                if ($this->tileTypes[$this->myTiles[$green_point->y][$green_point->x]]) {
                    $this->tileTypes[$this->myTiles[$green_point->y][$green_point->x]]--;
                }
                $this->tileTypes[HexaHopMap::TILE_LOW_ELEVATOR]++;
                $this->myTiles[$green_point->y][$green_point->x] = HexaHopMap::TILE_LOW_ELEVATOR;
                unset($missingGreens[$green_point_index]);
                if (!$missingGreens) {
                    return false;
                }
            }
        }

        if (!$otherLasers) {
            return null;
        }

        foreach ($this->reachableLasers as $laser_point) {
            foreach ($otherLasers as $other_point_index => $other_point) {
                if ($laser_point->dirDistance($other_point) > 0) {
                    $explodableLasers[] = $other_point;
                    unset($otherLasers[$other_point_index]);
                }
            }
        }

        if (!$explodableLasers) {
            return null;
        }

        foreach ($explodableLasers as $laser_point) {
            foreach ($missingGreens as $green_point_index => $green_point) {
                $distance = Projectile::BetweenPoints($laser_point, $green_point);
                if ($distance && $distance->length === 1) {
                    $destroyedCount++;
                    if ($this->tileTypes[$this->myTiles[$green_point->y][$green_point->x]]) {
                        $this->tileTypes[$this->myTiles[$green_point->y][$green_point->x]]--;
                    }
                    $this->tileTypes[HexaHopMap::TILE_LOW_ELEVATOR]++;
                    $this->myTiles[$green_point->y][$green_point->x] = HexaHopMap::TILE_LOW_ELEVATOR;
                    unset($missingGreens[$green_point_index]);
                    if (!$missingGreens) {
                        return false;
                    }
                }
            }
        }

        if ($destroyedCount > 0) {
            return true;
        }

        return null;
    }

    /**
     * @return bool true => all reachable
     */
    public function expandLasers(bool $lowerGreenWalls, bool $lowerBlueWalls): bool
    {
        if ($this->expandReachable($lowerGreenWalls, $lowerBlueWalls)) {
            return true;
        }

        while(true) {
            $laserResult = $this->triggerLasers();
            if ($laserResult === null) {
                return false;
            }
            if ($laserResult === false) {
                return true;
            }
            if ($this->expandReachable($lowerGreenWalls, $lowerBlueWalls)) {
                return true;
            }
        }
    }

    /**
     * @return bool true -> all reachable
     */
    public function expand(bool $canLowerBlue): bool
    {
        $result = $this->expandLasers(false, false);
        if ($result) {
            return true;
        }
        $this->wallTest();

        if ($this->allLowGreenReached) {
            $result = $this->expandLasers(true, false);
            if ($result) {
                return true;
            }
            $this->wallTest();
        }

        if (!$this->allLowBlueReached || !$canLowerBlue) {
            return $this->greenReached();
        }

        $result = $this->expandLasers($this->allLowGreenReached, true);
        if ($result) {
            return true;
        }

        if ($this->allLowGreenReached) {
            return $this->greenReached();
        }

        $this->wallTest();

        if (!$this->allLowGreenReached) {
            return false;
        }

        $result = $this->expandLasers(true, true);
        if ($result) {
            return true;
        }

        return $this->greenReached();
    }

    /**
     * @return bool true -> all reachable
     */
    public function greenReached(): bool
    {
        foreach ($this->myTiles as $y => $row) {
            foreach ($row as $x => $tile) {
                if ($tile === HexaHopMap::TILE_LOW_GREEN && empty($this->reachable[0][$y][$x])) {
                    return false;
                }
                if ($tile === HexaHopMap::TILE_HIGH_GREEN && empty($this->reachable[0][$y][$x]) && empty($this->reachable[1][$y][$x])) {
                    return false;
                }
            }
        }
        return true;
    }
}