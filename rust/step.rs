use std::cmp::Ordering;
use crate::direction::Direction;
use crate::map::MapInfo;
use crate::map::MapState;
use crate::map::MapStatus;
use crate::tile::TileType;

#[derive(Clone, Debug)]
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

    pub fn ghost(&self) -> GameStateGhost {
        GameStateGhost::convert(self)
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

#[derive(Eq)]
pub struct GameStateGhost {
    pub cost: u16,
    pub compressed_path: [u128; 3]
}

impl GameStateGhost {
    pub fn compress_path(path: &Vec<Direction>) -> [u128; 3] {
        let mut compressed: [u128; 3] = [0, 0, 0];
        for (i, &dir) in path.iter().enumerate().rev() {
            let int_index = i / 42;
            compressed[int_index] = (compressed[int_index] << 3) | (dir as u128);
        }
        compressed
    }

    pub fn convert(game: &GameState) -> GameStateGhost {
        GameStateGhost {
            cost: game.cost,
            compressed_path: GameStateGhost::compress_path(&game.path),
        }
    }
    pub fn path(&self) -> Vec<Direction> {
        let mut path = Vec::new();
        for mut combined in self.compressed_path {
            for _index in 0..41 {
                let dir_value = (combined & 0x7) as u8;
                if dir_value == 0 {
                    return path;
                }
                path.push(Direction::from_repr(dir_value).unwrap());
                combined >>= 3;
            }
        }
        path
    }

    pub fn reproduce(&self, start_state: GameState, info: &MapInfo) -> GameState {
        self.path().iter().fold(start_state, |current_state, dir| current_state.step_if_alive(dir, &info, &None))
    }
}

impl Ord for GameStateGhost {
    fn cmp(&self, other: &Self) -> Ordering {
        other.cost.cmp(&self.cost)
    }
}
impl PartialOrd for GameStateGhost {
    fn partial_cmp(&self, other: &Self) -> Option<Ordering> {
        Some(self.cmp(other))
    }
}

impl PartialEq<Self> for GameStateGhost {
    fn eq(&self, other: &Self) -> bool {
        self.cost == other.cost
    }
}
