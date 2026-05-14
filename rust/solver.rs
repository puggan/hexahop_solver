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
    let start_state = GameState::new(MapState::load_lev(&info)?);
    todo.push(start_state.ghost());

    while todo.len() > 0 {
        let game = todo.pop().unwrap().reproduce(start_state.clone(), &info);
        dlog!("Testing game, score: {}, path: {}", game.cost, Direction::list2string(&game.path));
        let hash = fxhash::hash64(&game.state);
        if done.contains(&hash) {
            dlog!("Duplicate");
            continue;
        }
        done.insert(hash);

        if done.len() % 10000 == 0 {
            println!("won: {}, queued: {}, done: {}, cost: {}", won.len(), todo.len(), done.len(), game.cost);
        }

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
                    done.insert(fxhash::hash64(&next_state.state));
                    won.push(next_state.ghost());
                }
                MapStatus::Dead => {
                    dlog!("dir {} Dead!", dir);
                }
                MapStatus::Ongoing => {
                    dlog!("dir {} queued", dir);
                    if !done.contains(&fxhash::hash64(&next_state.state)) {
                        todo.push(next_state.ghost());
                    }
                }
            }
        }
    }

    let best = won.into_iter().max().ok_or_else(|| "No solution found".to_string())?.reproduce(start_state.clone(), &info);
    println!("Best game, score: {}, path: {}", best.cost, Direction::list2string(&best.path));
    Ok(best.clone())
}