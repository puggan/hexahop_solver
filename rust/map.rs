use serde::Deserialize;
use std::env;
use std::fs;
use validator::Validate;

const JSON_PATH: &str = "resources/hexahopmaps.json";
//const LEVEL_PATH: &str = "resources/levels/";

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
    pub tiles: [u8; 375],
    pub player_x: u8,
    pub player_y: u8,
    pub anti_ice: u8,
    pub jumps: u8,
}

impl MapState {
    pub fn load_from_info(info: MapInfo) -> Result<Self, String> {
        Ok(MapState {
            tiles: [0; 375],
            player_x: info.start_x,
            player_y: info.start_y,
            anti_ice: 0,
            jumps: 0,
        })
    }
}
