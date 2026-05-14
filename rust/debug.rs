use crate::direction::Direction;
use crate::map;
use crate::map::list;
use crate::map::MapState;
use crate::step::GameState;
use crate::tile;

pub fn run_debug(map_nr: usize, path: Option<String>) -> Result<(), String> {
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
    print_map(&state, &info);

    if path.is_some() {
        let path_list = Direction::make_list(path.unwrap().as_str())?;
        println!("\n--- Apply Path ---");
        println!("\nPath {} steps: {}", path_list.len(), Direction::list2string(&path_list));

        let final_state = path_list.iter().fold(GameState::new(state), |current_state, path| current_state.step_if_alive(path, &info, &None));
        println!("\nStanding on: ({}, {}): {}", final_state.state.player_x, final_state.state.player_y, final_state.state.describe_tile(final_state.state.player_x, final_state.state.player_y, &info));
        println!("\nStatus: {}", final_state.state.status(&info, final_state.cost, &None));
        println!("\nCost: {}", final_state.cost);
        print_map(&final_state.state, &info);
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

pub fn print_map(state: &MapState, info: &map::MapInfo) {
    let total_rows = (info.height as usize * 2) + info.width as usize;
    let mut all_lines: Vec<String> = Vec::new();

    for row in 0..total_rows {
        let mut line = String::new();

        for x in 0..info.width as i8 {
            // 1. Check if this row is a "Content" row (Player/Items)
            // Solve: row = 2y + x - 1  => 2y = row - x + 1
            let target_content = row as i16 - x as i16 + 1;

            // 2. Check if this row is a "Base" row (Tile Type)
            // Solve: row = 2y + x      => 2y = row - x
            let target_base = row as i16 - x as i16;

            let mut display_text = "  ".to_string();

            if target_content >= 0 && target_content % 2 == 0 {
                let y = (target_content / 2) as i8;
                if y < info.height as i8 {
                    // Check for Player or Item here
                    if x == state.player_x && y == state.player_y {
                        display_text = "PL".to_string();
                    } else {
                        let byte = state.get_tile(x, y, info);
                        let item = tile::item_code_high_byte(byte);
                        display_text = item.to_string();
                    }
                }
            } else if target_base >= 0 && target_base % 2 == 0 {
                let y = (target_base / 2) as i8;
                if y < info.height as i8 {
                    let byte = state.get_tile(x, y, info);
                    display_text = tile::tile_code_byte(byte).to_string();
                }
            }

            // Alignment: Pad and push
            let spaces = x as usize * 4 ; // Increased to 4 for breathing room
            if line.len() < spaces {
                line.push_str(&" ".repeat(spaces - line.len()));
            }

            line.push_str(&display_text);
        }

        all_lines.push(line);
    }

    let mut empty_line_count = -(total_rows as i32);

    for row in 0..total_rows {
        let line = &all_lines[row];
        if line.trim().is_empty() {
            empty_line_count += 1;
            continue;
        }
        println!("{}{}", "\n".repeat(empty_line_count.max(0) as usize), line);
        empty_line_count = 0;
    }
}
