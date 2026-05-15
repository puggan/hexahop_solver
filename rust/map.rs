use serde::Deserialize;
use std::env;
use std::fs;
use std::io::Cursor;
use std::io::{Read, Seek, SeekFrom};
use strum_macros::Display;
use validator::Validate;
use crate::tile::{describe_tile_byte, TileType, MASK_TILE_TYPE};

const MAX_TILES: usize = 375;
const JSON_PATH: &str = "resources/hexahopmaps.json";
const LEVEL_PATH: &str = "resources/levels/";

#[derive(Deserialize, Debug, Clone, Validate)]
pub struct MapInfo {
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

impl MapInfo {
    pub fn tile_index(&self, x: i8, y: i8) -> Option<usize> {
        if x < 0 || y < 0 || x >= self.width as i8 || y >= self.height as i8 {
            return None;
        }

        let index = (x as usize * self.height as usize) + y as usize;

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

    let maps: Vec<MapInfo> = serde_json::from_str(&data)
        .map_err(|e| format!("JSON error: {}", e))?;

    Ok(maps)
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
    pub player_x: i8,
    pub player_y: i8,
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
        let (x_min, x_max, y_min, y_max) = (bounds[0], bounds[1], bounds[2], bounds[3]);

        // Calculate ACTUAL dimensions from file
        let actual_width = (x_max as i16 - x_min as i16 + 1) as u8;
        let actual_height = (y_max as i16 - y_min as i16 + 1) as u8;

        // VERIFY: Does the file match the JSON metadata?
        if actual_width != info.width || actual_height != info.height {
            return Err(format!(
                "ID {}: {} -> File is {}x{} at offset {},{}",
                info.level_number, info.title, actual_width, actual_height, x_min, y_min
            ));
            /*
            return Err(format!(
                "Dimension mismatch for {}: JSON says {}x{}, but LEV file says {}x{}",
                info.title, info.width, info.height, actual_width, actual_height
            ));
            */
        }

        let total_cells = actual_width as usize * actual_height as usize;
        if total_cells > MAX_TILES {
            return Err(format!("Map {} exceeds buffer ({} tiles)", info.title, total_cells));
        }

        // 3. Read 2 u32 player-x, player-y (as Little Endian)
        let mut player_coords = [0u8; 8];
        reader.read_exact(&mut player_coords).map_err(|e| e.to_string())?;

        // Convert raw bytes to u32
        let p_x = u32::from_le_bytes(player_coords[0..4].try_into().unwrap());
        let p_y = u32::from_le_bytes(player_coords[4..8].try_into().unwrap());

        // 4. Read the raw tile block exactly as it exists in the file
        // PHP: foreach(x) { foreach(y) { ... } }
        // This means the file is 1D: [X0Y0, X0Y1, X0Y2, X1Y0, X1Y1...]
        let mut tiles = [0u8; MAX_TILES];
        reader.read_exact(&mut tiles[..total_cells]).map_err(|e| e.to_string())?;

        Ok(MapState {
            tiles,
            player_x: (p_x as i8 - x_min as i8),
            player_y: (p_y as i8 - y_min as i8),
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

    pub fn describe_tile(&self, x: i8, y: i8, info: &MapInfo) -> String {
        describe_tile_byte(self.get_tile(info.tile_index(x, y)).unwrap_or(TileType::Water as u8))
    }

    pub fn status(&mut self, info: &MapInfo, current_cost: u16, max_cost: &Option<u16>) -> MapStatus {
        if current_cost > max_cost.unwrap_or(info.par) {
            return MapStatus::Dead;
        }

        let current_index = info.tile_index(self.player_x, self.player_y);
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

        let laser_offset = if tile_count[TileType::Laser as usize] > 0 { 1 } else { 0 };

        if current_cost + targets_remaining as u16 - laser_offset > max_cost.unwrap_or(info.par) {
            return MapStatus::Dead;
        }

        MapStatus::Ongoing
    }

    pub fn tile_count(&self) -> [usize; 17] {
        let mut tile_counts = [0; 17];
        for &tile in self.tiles.iter() {
            tile_counts[tile as usize] += 1;
        }
        tile_counts
    }
}
