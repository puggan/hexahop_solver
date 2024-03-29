<?php

namespace Puggan\Solver\HexaHop;

use PHPUnit\Framework\TestCase;
use Puggan\Mock\HexaHopMapMock;
use Puggan\Solver\Entities\Projectile;

class HexaHopMapTest extends TestCase
{
    public function mockMap1(): HexaHopMapMock
    {
        /**
         * W
         *   G
         * P   B
         *   P   P
         * I   L
         *   P*  G
         * P   L
         *   I*  W
         * W   W
         *   P   P
         * W   L
         *   P   P
         *     P
         *       W
         */
        $mock_tiles = [
            [
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_GREEN,
                HexaHopMap::TILE_LOW_BLUE,
                HexaHopMap::TILE_LOW_LAND,
            ],
            [
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LASER,
                HexaHopMap::TILE_LOW_GREEN,
            ],
            [
                HexaHopMap::TILE_ICE,
                HexaHopMap::TILE_LOW_LAND | HexaHopMap::ITEM_ANTI_ICE << HexaHopMap::SHIFT_TILE_ITEM,
                HexaHopMap::TILE_LASER,
                HexaHopMap::TILE_WATER,
            ],
            [
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_ICE | HexaHopMap::ITEM_JUMP << HexaHopMap::SHIFT_TILE_ITEM,
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_LAND,
            ],
            [
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LASER,
                HexaHopMap::TILE_LOW_LAND,
            ],
            [
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_WATER,
            ],
        ];
        return new HexaHopMapMock($mock_tiles, null, 1, 1);
    }

    public function testPoints(): void
    {
        $m0 = $this->mockMap1();
        $this->assertEquals(0, $m0->points(), 'New map should have zero points');

        $mp1 = $m0->move(Projectile::DIR_S);

        $mg1 = $m0->move(Projectile::DIR_N);
        $mg2 = $mg1->move(Projectile::DIR_S);

        $mb1 = $m0->move(Projectile::DIR_NE);
        $mb2 = $mb1->move(Projectile::DIR_SW);

        $ml1 = $m0->move(Projectile::DIR_SE);
        $ml2 = $ml1->move(Projectile::DIR_SW);
        $ml3 = $ml2->move(Projectile::DIR_NE);
        $ml4 = $ml3->move(Projectile::DIR_S);

        $this->assertEquals(0, $m0->points(), 'Start map should still have zero points');
        $this->assertEquals(1, $mp1->points(), 'Normal steps cost 1');
        $this->assertEquals(1, $mg1->points(), 'Stepping into green cost no extra');
        $this->assertEquals(2, $mg2->points(), 'Leaving green cost no extra');
        $this->assertEquals(1, $mb1->points(), 'Stepping into blue cost no extra');
        $this->assertEquals(12, $mb2->points(), 'Destroying blue cost 10 extra');
        $this->assertEquals(1, $ml1->points(), 'Shooting green cost no extra');
        $this->assertEquals(2, $ml2->points(), 'Leaving lasers cost no extra');
        $this->assertEquals(13, $ml3->points(), 'Shooting none-green cost 10 extra');
        $this->assertEquals(74, $ml4->points(), 'Exploding a laser cost 10 per destroyed tile');
    }

    public function testItem_count(): void
    {
        $m0 = $this->mockMap1();
        $items = $m0->item_count();
        $this->assertIsArray($items, 'item_count-0 returns an array');
        $this->assertArrayHasKey(HexaHopMap::ITEM_ANTI_ICE, $items, 'item_count-0 has anti-ice');
        $this->assertArrayHasKey(HexaHopMap::ITEM_JUMP, $items, 'item_count-0 has jump');
        $this->assertEquals(1, $items[HexaHopMap::ITEM_ANTI_ICE], 'item_count-0 have 1 anti-ice');
        $this->assertEquals(1, $items[HexaHopMap::ITEM_JUMP], 'item_count-0 have 1 jump');

        // Recount
        $items = $m0->item_count();
        $this->assertEquals(1, $items[HexaHopMap::ITEM_JUMP], 'item_count-0 still have 1 jump');

        // Pick up Anti-Ice
        $m1 = $m0->move(Projectile::DIR_S);
        $items = $m1->item_count();
        $this->assertIsArray($items, 'item_count-1 returns an array');
        $this->assertArrayHasKey(HexaHopMap::ITEM_ANTI_ICE, $items, 'item_count-1 has anti-ice');
        $this->assertArrayHasKey(HexaHopMap::ITEM_JUMP, $items, 'item_count-1 has jump');
        $this->assertEquals(1, $items[HexaHopMap::ITEM_ANTI_ICE], 'item_count-1 have 1 anti-ice');
        $this->assertEquals(1, $items[HexaHopMap::ITEM_JUMP], 'item_count-1 have 1 jump');

        // Pick up jump, & use Anti-Ice
        $m2 = $m1->move(Projectile::DIR_S);
        $items = $m2->item_count();
        $this->assertIsArray($items, 'item_count-2 returns an array');
        $this->assertArrayHasKey(HexaHopMap::ITEM_ANTI_ICE, $items, 'item_count-2 has anti-ice');
        $this->assertArrayHasKey(HexaHopMap::ITEM_JUMP, $items, 'item_count-2 has jump');
        $this->assertEquals(0, $items[HexaHopMap::ITEM_ANTI_ICE], 'item_count-2 have 0 anti-ice');
        $this->assertEquals(1, $items[HexaHopMap::ITEM_JUMP], 'item_count-2 have 1 jump');

        // Pick use jump
        $m3 = $m2->move(Projectile::DIR_J);
        $items = $m3->item_count();
        $this->assertIsArray($items, 'item_count-3 returns an array');
        $this->assertArrayHasKey(HexaHopMap::ITEM_ANTI_ICE, $items, 'item_count-3 has anti-ice');
        $this->assertArrayHasKey(HexaHopMap::ITEM_JUMP, $items, 'item_count-3 has jump');
        $this->assertEquals(0, $items[HexaHopMap::ITEM_ANTI_ICE], 'item_count-3 have 0 anti-ice');
        $this->assertEquals(0, $items[HexaHopMap::ITEM_JUMP], 'item_count-3 have 0 jump');
    }

    public function testHash(): void
    {
        $m0 = $this->mockMap1();
        $hash0 = $m0->hash();
        $mp1 = $m0->move(Projectile::DIR_NW);
        $mp2 = $mp1->move(Projectile::DIR_SE);
        $mg1 = $m0->move(Projectile::DIR_N);
        $mg2 = $mg1->move(Projectile::DIR_S);
        $this->assertEquals($hash0, $m0->hash(), 'Same hash each time');
        $this->assertNotEquals($hash0, $mp1->hash(), 'Different hash on different state time');
        $this->assertEquals($hash0, $mp2->hash(), 'Same hash when returning to an old state');
        $this->assertNotEquals($hash0, $mg1->hash(), 'Different hash on different state time (green)');
        $this->assertNotEquals($hash0, $mg2->hash(), 'Different hash on returning with an different state');
    }

    public function testWon(): void
    {
        $m0 = $this->mockMap1();
        $m1 = $m0->move(Projectile::DIR_N);
        $m2 = $m1->move(Projectile::DIR_S);
        $m3 = $m2->move(Projectile::DIR_SE);
        $this->assertFalse($m0->won());
        $this->assertFalse($m1->won());
        $this->assertFalse($m2->won());
        $this->assertTrue($m3->won());
    }

    public function testLost(): void
    {
        $m0 = $this->mockMap1();
        $m1 = $m0->move(Projectile::DIR_N);
        $m2 = $m1->move(Projectile::DIR_NW);
        $this->assertFalse($m0->lost());
        $this->assertFalse($m1->lost());
        $this->assertTrue($m2->lost());
    }

    public function testBetter(): void
    {
        $m0 = $this->mockMap1();
        $m2 = $m0->move(Projectile::DIR_NW)->move(Projectile::DIR_SE);
        $this->assertEquals($m0->hash(), $m2->hash());
        $this->assertTrue($m0->better($m2));
        $this->assertFalse($m2->better($m0));
    }

    public function testPossible_moves(): void
    {
        $m0 = $this->mockMap1();
        $moves = $m0->possible_moves();
        $this->assertIsArray($moves);
        $this->assertArrayHasKey(Projectile::DIR_N, $moves);
        $this->assertArrayHasKey(Projectile::DIR_SE, $moves);
    }

    public function testTile_type_count(): void
    {
        $type_keys = [
            HexaHopMap::TILE_LOW_LAND,
            HexaHopMap::TILE_LOW_GREEN,
            HexaHopMap::TILE_HIGH_GREEN,
            HexaHopMap::TILE_TRAMPOLINE,
            HexaHopMap::TILE_ROTATOR,
            HexaHopMap::TILE_HIGH_LAND,
            HexaHopMap::TILE_LOW_BLUE,
            HexaHopMap::TILE_HIGH_BLUE,
            HexaHopMap::TILE_LASER,
            HexaHopMap::TILE_ICE,
            HexaHopMap::TILE_ANTI_ICE,
            HexaHopMap::TILE_BUILD,
            HexaHopMap::TILE_BOAT,
            HexaHopMap::TILE_LOW_ELEVATOR,
            HexaHopMap::TILE_HIGH_ELEVATOR,
        ];

        $m0 = $this->mockMap1();
        $types = $m0->tile_type_count();
        $this->assertIsArray($types);

        foreach ($type_keys as $type) {
            $this->assertArrayHasKey($type, $types);
        }
        $this->assertEquals(10, $types[HexaHopMap::TILE_LOW_LAND]);
        $this->assertEquals(2, $types[HexaHopMap::TILE_LOW_GREEN]);
        $this->assertEquals(1, $types[HexaHopMap::TILE_LOW_BLUE]);
        $this->assertEquals(3, $types[HexaHopMap::TILE_LASER]);
        $this->assertEquals(2, $types[HexaHopMap::TILE_ICE]);
    }

    public function testImpossible(): void
    {
        /* Layout:
         * W
         *   G
         * P*  W
         *   G
         * P   G
         *   W
         *     P
         *
         */
        $mock_tiles = [
            [
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_GREEN,
                HexaHopMap::TILE_WATER,
            ],
            [
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LOW_GREEN,
                HexaHopMap::TILE_LOW_GREEN,
            ],
            [
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_LAND,
            ],
        ];

        //<editor-fold desc="Win">
        $m0 = new HexaHopMapMock($mock_tiles, null, 0, 1, 4);
        $this->assertFalse($m0->impossible(), 'Possible from start');

        // Move to 1:0 Green
        $m1 = $m0->move(Projectile::DIR_NE);
        $this->assertFalse($m1->impossible(), 'Winning 1 of 4');

        // Move to 1:1 Green
        $m2 = $m1->move(Projectile::DIR_S);
        $this->assertFalse($m2->impossible(), 'Winning 2 of 4');

        // Move to 2:1 Green
        $m3 = $m2->move(Projectile::DIR_SE);
        $this->assertFalse($m3->impossible(), 'Winning 3 of 4');

        // Move to 2:2 Plain
        $m4 = $m3->move(Projectile::DIR_S);
        $this->assertFalse($m4->impossible(), 'Map 1 Winable');

        unset($m1, $mw, $m3, $m4);
        //</editor-fold>

        //<editor-fold desc="Fail">
        // Move to 0:0 Water, and die
        $f0 = $m0->move(Projectile::DIR_N);
        $this->assertTrue($f0->impossible(), 'Lost');

        // Move to 0:2 Plain, not enough steps to beat par
        $f1 = $m0->move(Projectile::DIR_S);
        $this->assertTrue($f1->impossible(), 'Not enough steps');

        // Move to 1:1 Green, and then 2:1 Green, Levaing 1:0 Green unreachable
        $f2 = $m0->move(Projectile::DIR_SE)->move(Projectile::DIR_SE);
        $this->assertTrue($f2->impossible(), 'Split');
        unset($m0, $f0, $f1, $f2);
        //</editor-fold>

        /* Layout:
         * W
         *   G
         * X   G*
         *   G
         *     G
         */
        $mock_tiles = [
            [
                HexaHopMap::TILE_WATER,
                HexaHopMap::TILE_LOW_GREEN,
                HexaHopMap::TILE_LOW_GREEN,
            ],
            [
                HexaHopMap::TILE_LOW_LAND,
                HexaHopMap::TILE_LOW_GREEN,
                HexaHopMap::TILE_LOW_GREEN,
            ],
        ];

        $m2_0 = new HexaHopMapMock($mock_tiles, null, 2, 0, 4);
        $this->assertFalse($m2_0->impossible(), 'Possible from start, amp 2');
        $m2_4 = $m2_0->move(Projectile::DIR_S)->move(Projectile::DIR_NW)->move(Projectile::DIR_N)->move(Projectile::DIR_SW);
        $this->assertTrue($m2_4->won(), 'Map 2 Winable');
        unset($m2_4);

        $f3 = $m2_0->move(Projectile::DIR_SW);
        $this->assertTrue($f3->impossible(), 'Dead end');
        unset($m2_0, $f3);
    }

    public function testSolved(): void
    {
        /** @var string[] $solved */
        $solved = json_decode(file_get_contents(dirname(__DIR__, 3) . '/solved.json'), true);
        foreach ($solved as $level_number => $path_str) {
            /** @var int[] $path */
            $path = array_map('intval', explode(',', $path_str));
            $m = new HexaHopMap($level_number);
            $this->assertFalse($m->impossible(), 'Level ' . $level_number . ' imposible, at start');
            foreach ($path as $index => $dir) {
                $m = $m->move($dir);
                $this->assertFalse($m->impossible(), 'Level ' . $level_number . ' imposible, at step ' . ($index + 1));
            }
            $this->assertTrue($m->won(), 'Level ' . $level_number . ' winnable');
            $this->assertEquals($m->points(), $m->par(), 'Level ' . $level_number . ' beat par');
        }
    }

    public function testMapsPossible(): void
    {
        foreach (range(1, 100) as $mapId) {
            if (in_array($mapId, [60, 73, 80], true)) {
                // TODO remove exception
                continue;
            }
            $map = new HexaHopMap($mapId);
            $this->assertFalse($map->impossible(), 'Level ' . $mapId . ' imposible, at start');
        }
    }
}
