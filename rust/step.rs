use std::cmp::Ordering;
use crate::direction::Direction;
use crate::map::MapInfo;
use crate::map::MapState;
use crate::map::MapStatus;
use crate::point::Point;
use crate::tile::ItemType;
use crate::tile::MASK_TILE_TYPE;
use crate::tile::SHIFT_TILE_ITEM;
use crate::tile::TileType;

#[derive(Clone, Debug)]
pub struct GameState {
    pub state: MapState,
    pub path: Vec<Direction>,
    pub cost: u16,
    pub status: MapStatus,
}

impl GameState {
    pub fn new(state: MapState) -> Self {
        Self {
            state,
            path: Vec::new(),
            cost: 0,
            status: MapStatus::Ongoing,
        }
    }

    pub fn ghost(&self) -> GameStateGhost {
        GameStateGhost::convert(self)
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
        match self.status {
            MapStatus::Ongoing => self.step(dir, map_info, max_cost),
            _ => self.clone()
        }
    }

    pub fn step(&self, dir: &Direction, map_info: &MapInfo, max_cost: &Option<u16>) -> GameState {
        let old_tile = TileType::from_option(self.state.get_tile(map_info.tile_index(self.state.player)));
        self
            .step_out_of(map_info, max_cost)
            .step_into(
                old_tile.unwrap_or(TileType::Water).high(),
                self.state.player + dir.offset(1),
                dir,
                map_info,
                max_cost
            )
    }

    pub fn step_out_of(&self, map_info: &MapInfo, max_cost: &Option<u16>) -> GameState {
        let mut new_tiles = self.state.tiles;
        let mut extra_cost = 1;
        let tile_index = map_info.tile_index(self.state.player);
        if tile_index.is_some() {
            let tile_value = self.state.tiles[tile_index.unwrap()];
            let tile = TileType::from_repr(tile_value).unwrap_or(TileType::Water);
            new_tiles[tile_index.unwrap()] = match tile {
                TileType::AntiIce => TileType::LowBlue as u8,
                TileType::LowBlue => {
                    extra_cost += 10;
                    TileType::LowGreen as u8
                },
                TileType::LowGreen => TileType::Water as u8,
                TileType::HighBlue => {
                    extra_cost += 10;
                    TileType::HighGreen as u8
                },
                TileType::HighGreen => TileType::Water as u8,
                _ => tile_value
            };
        };

        let mut map_state = MapState {
            tiles: new_tiles,
            player: self.state.player,
            anti_ice: self.state.anti_ice,
            jumps: self.state.jumps,
        };
        let status = map_state.status(&map_info, self.cost, max_cost);

        GameState {
            state: map_state,
            path: self.path.clone(),
            cost: self.cost + extra_cost,
            status
        }
    }

    pub fn step_into(&self, high: bool, point: Point, dir: &Direction, map_info: &MapInfo, max_cost: &Option<u16>) -> GameState {
        let mut new_path = self.path.clone();
        new_path.push(*dir);
        let mut dead = new_path.len() > 100;
        let jump_used = match dir {
            Direction::Jump => {
                if self.state.jumps == 0 {
                    dead = true;
                    0
                } else {
                    1
                }
            },
            _ => 0
        };

        let tile_index = map_info.tile_index(point);
        let tile_value = self.state.get_tile(tile_index).unwrap_or(0);
        let landed_on_tile = TileType::from_repr(tile_value & MASK_TILE_TYPE).unwrap_or(TileType::Water);

        let mut map_state = MapState {
            tiles: self.state.tiles,
            player: point,
            anti_ice: self.state.anti_ice,
            jumps: self.state.jumps - jump_used,
        };
        map_state.status(&map_info, self.cost, max_cost);

        match ItemType::from_repr(tile_value >> SHIFT_TILE_ITEM).unwrap_or(ItemType::None) {
            ItemType::None => {}
            ItemType::AntiIce => {
                map_state.anti_ice += 1;
                map_state.tiles[tile_index.unwrap()] = landed_on_tile as u8;
            }
            ItemType::Jump => {
                map_state.jumps += 1;
                map_state.tiles[tile_index.unwrap()] = landed_on_tile as u8;
            }
        }

        match landed_on_tile {
            TileType::Water => {
                dead = true;
            },
            TileType::LowGreen => {},
            TileType::LowLand => {},
            TileType::LowBlue => {},
            TileType::HighLand => {
                if !high {
                    dead = true;
                }
            },
            TileType::HighGreen => {
                if !high {
                    dead = true;
                }
            },
            TileType::HighBlue => {
                if !high {
                    dead = true;
                }
            },
            TileType::Ice => {
                if map_state.anti_ice > 0 {
                    map_state.anti_ice -= 1;
                    map_state.tiles[tile_index.unwrap()] = TileType::AntiIce as u8;
                } else {
                    let mut glide = 0;
                    loop {
                        let glide_point = point + dir.offset(glide);
                        let next1_tile = TileType::from_option(map_state.get_tile(map_info.tile_index(glide_point))).unwrap_or(TileType::Water);
                        if next1_tile.high() {
                            break;
                        }

                        if next1_tile == TileType::Water {
                            dead = true;
                            map_state.player = glide_point;
                            break;
                        } else if next1_tile == TileType::Ice {
                            glide += 1;
                        } else {
                            return GameState {
                                state: map_state,
                                path: self.path.clone(),
                                cost: self.cost,
                                status: MapStatus::Ongoing,
                            }.step_into(
                                high,
                                glide_point,
                                dir,
                                map_info,
                                max_cost,
                            )
                        }
                    }
                }
            }
            TileType::Trampoline => {
                map_state.status(&map_info, self.cost, max_cost);
                let next1 = point + dir.offset(1);
                let next2 = point + dir.offset(2);
                let next1_tile = map_state.get_tile(map_info.tile_index(next1));
                let next2_tile = map_state.get_tile(map_info.tile_index(next2));
                if high {
                    return GameState {
                        state: map_state,
                        path: self.path.clone(),
                        cost: self.cost,
                        status: MapStatus::Ongoing,
                    }.step_into(
                        high,
                        next2,
                        dir,
                        map_info,
                        max_cost,
                    )
                } else if !TileType::from_option(next1_tile).unwrap_or(TileType::Water).high() {
                    let jump_size = if TileType::from_option(next2_tile).unwrap_or(TileType::Water).high() { 1 } else { 2 };
                    return GameState {
                        state: map_state,
                        path: self.path.clone(),
                        cost: self.cost,
                        status: MapStatus::Ongoing,
                    }.step_into(
                        high,
                        point + dir.offset(jump_size),
                        dir,
                        map_info,
                        max_cost,
                    )
                }
            }
            TileType::Boat => {
                loop {
                    let old_tile_index = map_info.tile_index(map_state.player);
                    let next_point = map_state.player + dir.offset(1);
                    let next_tile_index = map_info.tile_index(next_point);
                    let next_tile = self.state.get_tile(next_tile_index);
                    if next_tile.is_none() {
                        dead = true;
                        break;
                    } else if next_tile.unwrap() == TileType::Water as u8 {
                        map_state.tiles[next_tile_index.unwrap()] = TileType::Boat as u8;
                        map_state.tiles[old_tile_index.unwrap()] = TileType::Water as u8;
                        map_state.player = next_point;
                    } else {
                        break;
                    }
                }
            }
            _ => {
                unimplemented!("TODO step")
            }
        }

        let status = match dead { true => MapStatus::Dead, false => map_state.status(&map_info, self.cost, max_cost) };
        //println!("Dead: {}, Status: {}, tile: {}", dead, status, landed_on_tile.describe());
        GameState {
            state: map_state,
            path: new_path,
            cost: self.cost,
            status
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
            for _index in 0..=41 {
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
