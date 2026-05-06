use crate::map;

pub fn run_debug(map_nr: usize, _path: Option<String>) -> Result<(), String> {
    let info = map::get(map_nr)?;
    println!("Debugging Map: {}", info.title);
    println!("File reference: {}", info.file);
    Ok(())
}