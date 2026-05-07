use strum_macros::FromRepr;

pub const MASK_TILE_TYPE: u8 = 0x1F;
pub const MASK_ITEM_TYPE: u8 = 0xE0;
pub const SHIFT_TILE_ITEM: u8 = 5;

#[derive(Debug, PartialEq, Eq, Clone, Copy, FromRepr)]
#[repr(u8)] // This ensures the enum is stored as a single byte
pub enum TileType {
    Water = 0,
    LowLand = 1,
    LowGreen = 2,
    HighGreen = 3,
    Trampoline = 4,
    Rotator = 5,
    HighLand = 6,
    LowBlue = 7,
    HighBlue = 8,
    Laser = 9,
    Ice = 10,
    AntiIce = 11,
    Build = 12,
    BuildableWater = 13,
    Boat = 14,
    LowElevator = 15,
    HighElevator = 16,
}

#[derive(Debug, PartialEq, Eq, Clone, Copy, FromRepr)]
#[repr(u8)] // This ensures the enum is stored as a single byte
pub enum ItemType {
    None = 0,
    AntiIce = 1,
    Jump = 2,
}

pub fn describe_tile_byte(tile_byte: u8) -> String {
    let tile_type_int = tile_byte & MASK_TILE_TYPE;
    let item_int = (tile_byte & MASK_ITEM_TYPE) >> SHIFT_TILE_ITEM;

    let type_name = TileType::from_repr(tile_type_int).map(describe_tile).unwrap_or("??");
    let item_name = ItemType::from_repr(item_int).map(describe_item).unwrap_or("??");

    format!("{}{}", type_name, item_name)
}

pub fn describe_tile(tile_type: TileType) -> &'static str {
    match tile_type {
        TileType::Water => "Water",
        TileType::LowLand => "Low Land",
        TileType::LowGreen => "Low Green",
        TileType::HighGreen => "High Green",
        TileType::Trampoline => "Trampoline",
        TileType::Rotator => "Rotator",
        TileType::HighLand => "High Land",
        TileType::LowBlue => "Low Blue",
        TileType::HighBlue => "High Blue",
        TileType::Laser => "Laser",
        TileType::Ice => "Ice",
        TileType::AntiIce => "Anti-Ice Tile",
        TileType::Build => "Build",
        TileType::BuildableWater => "Buildable Water",
        TileType::Boat => "Boat",
        TileType::LowElevator => "Low Elevator",
        TileType::HighElevator => "High Elevator",
    }
}

pub fn tile_code_byte(tile_byte: u8) -> &'static str {
    TileType::from_repr(tile_byte & MASK_TILE_TYPE).map(tile_code).unwrap_or("??")
}

pub fn tile_code(tile_type: TileType) -> &'static str {
    match tile_type {
        TileType::Water => "  ",
        TileType::LowLand => "la",
        TileType::LowGreen => "gr",
        TileType::HighGreen => "GR",
        TileType::Trampoline => "tr",
        TileType::Rotator => "ro",
        TileType::HighLand => "LA",
        TileType::LowBlue => "bl",
        TileType::HighBlue => "BL",
        TileType::Laser => "la",
        TileType::Ice => "ic",
        TileType::AntiIce => "ai",
        TileType::Build => "bu",
        TileType::BuildableWater => "bw",
        TileType::Boat => "bo",
        TileType::LowElevator => "el",
        TileType::HighElevator => "EL",
    }
}

pub fn describe_item(item_type: ItemType) -> &'static str {
    match item_type {
        ItemType::None => "",
        ItemType::AntiIce => " + [Anti-Ice Item]",
        ItemType::Jump => " + [Jump Item]",
    }
}
pub fn item_code(item_type: ItemType) -> &'static str {
    match item_type {
        ItemType::None => "",
        ItemType::AntiIce => "AI",
        ItemType::Jump => "JU",
    }
}
