use crate::map;
use crate::map::list;
use crate::map::MapState;

pub fn run_debug(map_nr: usize, _path: Option<String>) -> Result<(), String> {
    if map_nr == 0 {
        if verify_all_maps() == false {
            generate_corrected_json();
        }
        return Ok(())
    }
    let info = map::get(map_nr)?;
    println!("Debugging Map: {}", info.title);
    println!("File reference: {}", info.file);

    // Load the binary data
    let state = MapState::load_lev(&info)?;

    println!("\n--- Tile Details ---");
    println!("Standing on: ({}, {}): {}", state.player_x, state.player_y, state.describe_tile(state.player_x, state.player_y, &info));

    // If you want to list all non-water tiles:
    println!("\n--- Tile Details ---");
    for y in 0..info.height as i8 {
        for x in 0..info.width as i8 {
            let byte = state.get_tile(x, y, &info);
            if byte > 0 {
                println!("({}, {}): {}", x, y, map::describe_tile(byte));
            }
        }
    }
    Ok(())
}

pub fn verify_all_maps() -> bool {
    let maps = match list() {
        Ok(m) => m,
        Err(e) => {
            println!("Failed to load JSON list: {}", e);
            return false;
        }
    };

    println!("Checking {} maps...", maps.len());
    let mut fix_count = 0;

    for info in maps.iter().skip(1) {
        match MapState::load_lev(&info) {
            Ok(_) => {
                // Map is healthy
            }
            Err(e) => {
                // This will print our formatted error with the correct values
                println!("FIX REQUIRED: {}", e);
                fix_count += 1;
            }
        }
    }

    if fix_count == 0 {
        println!("All maps are valid!");
    } else {
        println!("\nTotal fixes needed: {}", fix_count);
    }

    fix_count == 0
}

pub fn generate_corrected_json() {
    let maps = list().expect("Failed to load existing JSON");

    println!("["); // Start JSON array
    let map_length = maps.len();
    for (i, info) in maps.iter().enumerate() {
        let project_root = std::env::var("CARGO_MANIFEST_DIR").unwrap_or_else(|_| ".".into());
        let full_path = std::path::Path::new(&project_root).join("resources/levels").join(&info.file);

        let bytes = match std::fs::read(&full_path) {
            Ok(b) => b,
            Err(_) => continue,
        };

        let mut reader = std::io::Cursor::new(bytes);
        let _ = reader.set_position(10); // Skip header

        // Read Bounds
        let mut b = [0u8; 4];
        let _ = std::io::Read::read_exact(&mut reader, &mut b);
        let (x_min, x_max, y_min, y_max) = (b[0], b[1], b[2], b[3]);

        // Read Player Start (Original World Coordinates)
        let mut p = [0u8; 8];
        let _ = std::io::Read::read_exact(&mut reader, &mut p);
        let p_x = u32::from_le_bytes(p[0..4].try_into().unwrap());
        let p_y = u32::from_le_bytes(p[4..8].try_into().unwrap());

        // Calculate actual dimensions
        let width = (x_max as i16 - x_min as i16 + 1) as u8;
        let height = (y_max as i16 - y_min as i16 + 1) as u8;

        // Print as JSON object
        println!("  {{");
        println!("    \"file\": \"{}\",", info.file);
        println!("    \"title\": \"{}\",", info.title);
        println!("    \"level_number\": {},", info.level_number);
        println!("    \"width\": {},", width);
        println!("    \"height\": {},", height);
        println!("    \"x_min\": {},", x_min);
        println!("    \"x_max\": {},", x_max);
        println!("    \"y_min\": {},", y_min);
        println!("    \"y_max\": {},", y_max);
        println!("    \"start_x\": {},", p_x);
        println!("    \"start_y\": {},", p_y);
        println!("    \"par\": {}", info.par);

        if i < map_length - 1 {
            println!("  }},");
        } else {
            println!("  }}");
        }
    }
    println!("]");
}