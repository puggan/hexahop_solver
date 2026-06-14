use serde::Deserialize;
use std::collections::HashSet;
use std::env;
use std::fs;
use std::io::Cursor;
use std::io::{Read, Seek, SeekFrom};
use strum_macros::Display;
use validator::Validate;
use crate::direction::Direction;
use crate::point::Boundary;
use crate::point::Point;
use crate::point::Projectile;
use crate::tile::describe_tile_byte;
use crate::tile::ItemType;
use crate::tile::MASK_ITEM_TYPE;
use crate::tile::MASK_TILE_TYPE;
use crate::tile::SHIFT_TILE_ITEM;
use crate::tile::TileType;

// Largest framed grid: (16 + 2) * (25 + 2) = 486 (one-tile water border around the map).
const MAX_TILES: usize = 486;
const JSON_PATH: &str = "resources/hexahopmaps.json";
const LEVEL_PATH: &str = "resources/levels/";

#[derive(Deserialize, Debug, Clone, Validate)]
pub struct MapInfoJson {
    #[validate(length(min = 5, max = 32))]
    pub file: String,
    #[validate(length(min = 4, max = 22))]
    pub title: String,
    #[validate(range(min = 1, max = 100))]
    pub level_number: u8,
    #[validate(range(min = 4, max = 16))]
    pub width: u8,
    #[validate(range(min = 10, max = 25))]
    pub height: u8,
    #[validate(range(min = 11, max = 361))]
    pub par: u16,
    #[validate(range(min = 0, max = 12))]
    pub start_x: u8,
    #[validate(range(min = 4, max = 25))]
    pub start_y: u8,
}

#[derive(Debug, Clone)]
pub struct MapInfo {
    pub file: String,
    pub title: String,
    pub level_number: u8,
    pub width: u8,
    pub height: u8,
    pub par: u16,
}

impl From<MapInfoJson> for MapInfo {
    fn from(json: MapInfoJson) -> Self {
        MapInfo {
            file: json.file,
            title: json.title,
            level_number: json.level_number,
            width: json.width + 2,
            height: json.height + 2,
            par: json.par,
        }
    }
}

impl MapInfo {
    pub fn size(&self) -> Point {
        Point::new(self.width as i8, self.height as i8)
    }

    pub fn tile_index(&self, point: Point) -> Option<usize> {
        if !point.valid(self.height, self.width) {
            return None;
        }

        let index = point.index(self.height);

        if index >= MAX_TILES {
            return None;
        }

        Some(index)
    }

}

pub fn list() -> Result<Vec<MapInfo>, String> {
    let project_root = env::var("CARGO_MANIFEST_DIR").unwrap_or_else(|_| ".".into());
    let data = fs::read_to_string(std::path::Path::new(&project_root).join(JSON_PATH))
        .map_err(|e| format!("File error: {}", e))?;

    let maps: Vec<MapInfoJson> = serde_json::from_str(&data)
        .map_err(|e| format!("JSON error: {}", e))?;

    Ok(maps.into_iter().map(MapInfo::from).collect())
}

pub fn get(map_nr: usize) -> Result<MapInfo, String> {
    let maps = list()?;

    maps.get(map_nr)
        .cloned() // Clone the MapInfo out of the Vector
        .ok_or_else(|| format!("Map index {} not found (Total maps: {})", map_nr, maps.len()))
}

#[derive(Debug, Clone, Hash, Eq, PartialEq)]
pub struct MapState {
    pub tiles: [u8; MAX_TILES],
    pub player: Point,
    pub anti_ice: u8,
    pub jumps: u8,
}

#[derive(Clone, Debug, Display, PartialEq, Eq)]
pub enum MapStatus {
    Won,
    Dead,
    Ongoing,
}

impl MapState {
    pub fn load_lev(info: &MapInfo) -> Result<Self, String> {
        let project_root = std::env::var("CARGO_MANIFEST_DIR").unwrap_or_else(|_| ".".into());
        let full_path = std::path::Path::new(&project_root)
            .join(LEVEL_PATH)
            .join(&info.file);

        let bytes = std::fs::read(&full_path)
            .map_err(|e| format!("Failed to read lev: {}", e))?;

        let mut reader = Cursor::new(bytes);

        // 1. Skip header: 10 bytes [version, newline, 4:par, 4:diff]
        reader.seek(SeekFrom::Start(10)).map_err(|e| e.to_string())?;

        // 2. Read 4 u8 as x-min, x-max, y-min, y-max
        let mut bounds = [0u8; 4];
        reader.read_exact(&mut bounds).map_err(|e| e.to_string())?;
        let boundary = Boundary::from_parsed(bounds);
        let framed = boundary.expand(1);

        // Calculate ACTUAL dimensions from file
        let map_size = boundary.size();

        // VERIFY: Does the file match the JSON metadata?
        if framed.size() != info.size() {
            return Err(format!(
                "ID {}: {} -> File is {}x{} at offset {}",
                info.level_number, info.title, map_size.x, map_size.y, boundary.low
            ));
            /*
            return Err(format!(
                "Dimension mismatch for {}: JSON says {}x{}, but LEV file says {}x{}",
                info.title, info.width, info.height, actual_width, actual_height
            ));
            */
        }

        let total_cells = map_size.x as usize * map_size.y as usize;
        if total_cells > MAX_TILES {
            return Err(format!("Map {} exceeds buffer ({} tiles)", info.title, total_cells));
        }

        // 3. Read 2 u32 player-x, player-y (as Little Endian)
        let mut player_coords = [0u8; 8];
        reader.read_exact(&mut player_coords).map_err(|e| e.to_string())?;

        // Convert raw bytes to u32
        let p_x = u32::from_le_bytes(player_coords[0..4].try_into().unwrap());
        let p_y = u32::from_le_bytes(player_coords[4..8].try_into().unwrap());
        let player_point = Point::new(p_x as i8, p_y as i8);

        // 4. Read the raw tile block exactly as it exists in the file
        // PHP: foreach(x) { foreach(y) { ... } }
        // This means the file is 1D: [X0Y0, X0Y1, X0Y2, X1Y0, X1Y1...]
        let mut parsed_tiles = [0u8; MAX_TILES];
        reader.read_exact(&mut parsed_tiles[..total_cells]).map_err(|e| e.to_string())?;

        let mut framed_tiles = [0u8; MAX_TILES];
        let mut parsed_index = 0;
        for x in 0..map_size.x {
            for y in 0..map_size.y {
                if let Some(index) = info.tile_index(Point::new(x + 1, y + 1)) {
                    framed_tiles[index] = parsed_tiles[parsed_index];
                }
                parsed_index += 1;
            }
        }

        Ok(MapState {
            tiles: framed_tiles,
            player: player_point - framed.low,
            anti_ice: 0,
            jumps: 0,
        })
    }

    pub fn get_tile(&self, maybe_index: Option<usize>) -> Option<u8> {
        if let Some(index) = maybe_index {
            Some(self.tiles[index])
        } else {
            None
        }
    }

    pub fn describe_tile(&self, point: Point, info: &MapInfo) -> String {
        describe_tile_byte(self.get_tile(info.tile_index(point)).unwrap_or(TileType::Water as u8))
    }

    pub fn raycast(&self, info: &MapInfo, projectile: Projectile) -> Option<Point> {
        let step = projectile.dir.offset(1);
        let mut point = projectile.point;
        loop {
            point = point + step;
            let tile = match self.get_tile(info.tile_index(point)) {
                Some(tile) => tile,
                None => return None, // ray left the map
            };
            if tile & MASK_TILE_TYPE != TileType::Water as u8 {
                return Some(point);
            }
        }
    }

    pub fn fire_laser(&mut self, info: &MapInfo, projectile: Projectile) -> u16 {
        let mut todo: Vec<Projectile> = if projectile.dir == Direction::Jump {
            Projectile::all(projectile.point).to_vec()
        } else {
            vec![projectile]
        };
        let mut visited: HashSet<Projectile> = HashSet::new();
        let mut damage: HashSet<Point> = HashSet::new();

        while let Some(beam) = todo.pop() {
            if !visited.insert(beam) {
                continue;
            }
            let hit = match self.raycast(info, beam) {
                Some(hit) => hit,
                None => continue, // beam left the map
            };
            let hit_tile = TileType::from_repr(self.tiles[info.tile_index(hit).unwrap()] & MASK_TILE_TYPE).unwrap_or(TileType::Water);
            if hit_tile == TileType::Ice {
                // reflect into the two neighbouring directions
                todo.push(hit.projectile(beam.dir.counter_clockwise()));
                todo.push(hit.projectile(beam.dir.clockwise()));
            } else {
                damage.insert(hit);
            }
        }

        let score = |tile_byte: u8| -> u16 {
            match TileType::from_repr(tile_byte & MASK_TILE_TYPE).unwrap_or(TileType::Water) {
                TileType::Water | TileType::LowGreen | TileType::HighGreen => 0,
                _ => 10,
            }
        };

        let mut points = 0;
        for hit in damage {
            let hit_index = info.tile_index(hit).unwrap();
            let hit_tile = TileType::from_repr(self.tiles[hit_index] & MASK_TILE_TYPE).unwrap_or(TileType::Water);
            points += score(self.tiles[hit_index]);
            self.tiles[hit_index] = TileType::Water as u8;
            if hit_tile == TileType::Laser {
                // chain reaction: destroy the six neighbouring tiles too
                for neighbour in hit.neighbours() {
                    if let Some(n_index) = info.tile_index(neighbour.point) {
                        points += score(self.tiles[n_index]);
                        self.tiles[n_index] = TileType::Water as u8;
                    }
                }
            }
        }
        points
    }

    pub fn rotate(&mut self, info: &MapInfo, point: Point) {
        let neighbours = point.neighbours();
        let mut carry = self.get_tile(info.tile_index(neighbours[5].point)).unwrap_or(0) & MASK_TILE_TYPE;
        for neighbour in neighbours {
            let index = info.tile_index(neighbour.point);
            let old = self.get_tile(index).unwrap_or(0);
            if let Some(i) = index {
                self.tiles[i] = carry | (old & MASK_ITEM_TYPE);
            }
            carry = old & MASK_TILE_TYPE;
        }
    }

    pub fn build(&mut self, info: &MapInfo, point: Point) {
        for neighbour in point.neighbours() {
            if let Some(index) = info.tile_index(neighbour.point) {
                let item = self.tiles[index] & MASK_ITEM_TYPE;
                let tile = TileType::from_repr(self.tiles[index] & MASK_TILE_TYPE).unwrap_or(TileType::Water);
                self.tiles[index] = item | match tile {
                    TileType::Water => TileType::LowGreen as u8,
                    TileType::LowGreen => TileType::HighGreen as u8,
                    _ => tile as u8,
                };
            }
        }
    }

    pub fn status(&mut self, info: &MapInfo, current_cost: u16, max_cost: &Option<u16>) -> MapStatus {
        if current_cost > max_cost.unwrap_or(info.par) {
            return MapStatus::Dead;
        }

        let current_index = info.tile_index(self.player);
        let current_tile = self.get_tile(current_index).unwrap_or(0) & MASK_TILE_TYPE;
        if current_tile == TileType::Water as u8 {
            return MapStatus::Dead;
        }

        let tile_count = self.tile_count();

        let targets_remaining = tile_count[TileType::LowGreen as usize] + tile_count[TileType::HighGreen as usize];

        if targets_remaining == 0 {
            return MapStatus::Won;
        }

        if tile_count[TileType::HighGreen as usize] > 0 && tile_count[TileType::LowGreen as usize] == 0 {
            for tile in self.tiles.iter_mut() {
                if *tile == TileType::HighGreen as u8 {
                    *tile = TileType::LowGreen as u8;
                }
            }
        }

        if tile_count[TileType::HighBlue as usize] > 0 && tile_count[TileType::LowBlue as usize] == 0 {
            for tile in self.tiles.iter_mut() {
                if *tile == TileType::HighBlue as u8 {
                    *tile = TileType::LowBlue as u8;
                }
            }
        }

        let laser_present = tile_count[TileType::Laser as usize] > 0;
        let ice_present = tile_count[TileType::Ice as usize] > 0;
        let map_jump_items = self.tiles.iter().filter(|&&t| t >> SHIFT_TILE_ITEM == ItemType::Jump as u8).count();
        let jumps_available = self.jumps as usize + map_jump_items;

        if !(laser_present && ice_present) {
            let laser_offset = if laser_present { 1 + jumps_available * 5 } else { 0 };
            if current_cost + targets_remaining as u16 > laser_offset as u16 + max_cost.unwrap_or(info.par) {
                return MapStatus::Dead;
            }
        }

        MapStatus::Ongoing
    }

    pub fn tile_count(&self) -> [usize; 17] {
        let mut tile_counts = [0; 17];
        for &tile in self.tiles.iter() {
            tile_counts[(tile & MASK_TILE_TYPE) as usize] += 1;
        }
        tile_counts
    }
}
