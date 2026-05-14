use std::cmp::Ordering;
use crate::direction::Direction;
use crate::map::MapInfo;
use crate::map::MapState;
use crate::map::MapStatus;
use crate::tile::TileType;

#[derive(Clone, Debug, Eq)]
pub struct GameState {
    pub state: MapState,
    pub path: Vec<Direction>,
    pub cost: u16,
}

impl GameState {
    pub fn new(state: MapState) -> Self {
        Self {
            state,
            path: Vec::new(),
            cost: 0,
        }
    }

    pub fn status(&self, map_info: &MapInfo, max_cost: &Option<u16>) -> MapStatus {
        self.state.status(&map_info, self.cost, max_cost)
    }

    pub fn get_path_hash(&self) -> Vec<u64> {
        let mut hashes = Vec::new();
        let mut hash: u64 = 0;
        let mut part_count = 0;
        for &dir in &self.path {
            hash = (hash << 3) | (dir as u64);
            part_count += 1;
            if part_count >= 20  {
                hashes.push(hash);
                hash = 0;
                part_count = 0;
            }
        }
        hashes
    }

    pub fn step_if_alive(&self, dir: &Direction, map_info: &MapInfo, max_cost: &Option<u16>) -> GameState {
        match self.status(map_info, max_cost) {
            MapStatus::Ongoing => self.step(dir, map_info),
            _ => self.clone()
        }
    }

    pub fn step(&self, dir: &Direction, map_info: &MapInfo) -> GameState {
        let old_tile = TileType::from_repr(
            self.state.get_tile(self.state.player_x, self.state.player_y, map_info)
        );
        self
            .step_out_of(map_info)
            .step_into(
                old_tile.unwrap_or(TileType::Water).high(),
                self.state.player_x + dir.dx(),
                self.state.player_y + dir.dy(),
                dir,
                map_info
            )
    }

    pub fn step_out_of(&self, map_info: &MapInfo) -> GameState {
        let mut new_tiles = self.state.tiles;
        let mut anti_ice_used = 0;
        let mut jumps_used = 0;
        let mut extra_cost = 1;
        let tile_index = self.state.get_tile_index(self.state.player_x, self.state.player_y, map_info);
        if tile_index.is_some() {
            let tile_value = self.state.tiles[tile_index.unwrap()];
            let tile = TileType::from_repr(tile_value).unwrap_or(TileType::Water);
            new_tiles[tile_index.unwrap()] = match tile {
                TileType::AntiIce => TileType::LowBlue as u8,
                TileType::LowBlue => TileType::LowGreen as u8,
                TileType::LowGreen => TileType::Water as u8,
                TileType::HighBlue => TileType::HighGreen as u8,
                TileType::HighGreen => TileType::Water as u8,
                _ => tile_value
            };
            extra_cost = match tile {
                TileType::LowBlue => 11,
                TileType::HighBlue => 11,
                _ => 1
            }
        };
        if self.state.anti_ice < anti_ice_used {
            anti_ice_used = 0;
            extra_cost += 1<<14;
        }
        if self.state.jumps < jumps_used {
            jumps_used = 0;
            extra_cost += 1<<14;
        }
        GameState {
            state: MapState {
                tiles: new_tiles,
                player_x: self.state.player_x,
                player_y: self.state.player_y,
                anti_ice: self.state.anti_ice - anti_ice_used,
                jumps: self.state.jumps - jumps_used,
            },
            path: self.path.clone(),
            cost: self.cost + extra_cost
        }
    }

    pub fn step_into(&self, high: bool, x: i8, y: i8, dir: &Direction, map_info: &MapInfo) -> GameState {
        let mut new_path = self.path.clone();
        new_path.push(*dir);
        let /*mut*/ new_tiles = self.state.tiles;

        let landed_on_tile = TileType::from_repr(self.state.get_tile(x, y, map_info)).unwrap_or(TileType::Water);

        match landed_on_tile {
            TileType::Water => {},
            TileType::LowGreen => {},
            TileType::LowLand => {},
            _ => {
                unimplemented!("TODO step")
            }
        }

        GameState {
            state: MapState {
                tiles: new_tiles,
                player_x: x,
                player_y: y,
                anti_ice: self.state.anti_ice,
                jumps: self.state.jumps,
            },
            path: new_path,
            cost: self.cost
        }
    }
}

impl Ord for GameState {
    fn cmp(&self, other: &Self) -> Ordering {
        // We reverse the comparison here to turn the Max-Heap into a Min-Heap
        other.cost.cmp(&self.cost)
    }
}
impl PartialOrd for GameState {
    fn partial_cmp(&self, other: &Self) -> Option<Ordering> {
        Some(self.cmp(other))
    }
}

impl PartialEq<Self> for GameState {
    fn eq(&self, other: &Self) -> bool {
        self.cost == other.cost
    }
}
