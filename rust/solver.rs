use std::collections::BinaryHeap;
use std::collections::HashSet;
use crate::direction::Direction;
use crate::map;
use crate::map::{MapState, MapStatus};
use crate::step::GameState;

macro_rules! dlog {
    ($($arg:tt)*) => {
        if false { // Change this 'false' to 'true' to see logs
            println!($($arg)*);
        }
    };
}
pub fn run_solver(map_nr: usize, max_cost: &Option<u16>) -> Result<GameState, String> {
    let info = map::get(map_nr)?;
    let mut todo = BinaryHeap::new();
    let mut won = Vec::new();
    let mut done = HashSet::new();

    todo.push(GameState::new(MapState::load_lev(&info)?));

    while todo.len() > 0 {
        let game = todo.pop().unwrap();
        dlog!("Testing game, score: {}, path: {}", game.cost, Direction::list2string(&game.path));
        if done.contains(&game.state) {
            dlog!("Duplicate");
            continue;
        }
        done.insert(game.state.clone());

        let directions: &[Direction] = if game.state.jumps > 0 {
            &Direction::all()
        } else {
            &Direction::flat()
        };
        for dir in directions {
            let next_state = game.step(dir, &info);
            match next_state.status(&info, max_cost) {
                MapStatus::Won => {
                    dlog!("dir {} Won!", dir);
                    done.insert(next_state.state.clone());
                    won.push(next_state);
                }
                MapStatus::Dead => {
                    dlog!("dir {} Dead!", dir);
                }
                MapStatus::Ongoing => {
                    dlog!("dir {} queued", dir);
                    if !done.contains(&next_state.state) {
                        todo.push(next_state);
                    }
                }
            }
        }
    }

    let best = won.into_iter().max().ok_or_else(|| "No solution found".to_string())?;
    println!("Best game, score: {}, path: {}", best.cost, Direction::list2string(&best.path));
    Ok(best.clone())
}