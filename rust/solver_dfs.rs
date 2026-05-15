use std::time::Instant;
use crate::debug::print_map;
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

pub struct SolverState {
    pub direction: Direction,
    pub game: GameState,
}

// Depth-First Search (DFS).
pub fn run_solver(map_nr: usize, max_cost: &Option<u16>) -> Result<GameState, String> {
    let info = map::get(map_nr)?;
    let start_state = GameState::new(MapState::load_lev(&info)?);
    let mut solve_states = vec![SolverState {direction: Direction::None, game: start_state}];
    let mut best_win = None;
    let mut tries = 0 as u128;
    let mut last_print_time = Instant::now();

    dlog!("Starting deepth: {}", solve_states.len());

    while solve_states.len() > 0 {
        let current_solve_state = solve_states.pop().unwrap();
        let next_direction = current_solve_state.direction.next();
        if next_direction.is_none() {
            continue;
        }
        let updated_solve_state = SolverState {
            direction: next_direction.unwrap(),
            game: current_solve_state.game,
        };
        dlog!("Next dir {} on deepth: {}", &updated_solve_state.direction, solve_states.len());
        let next_game_state = updated_solve_state.game.step(&updated_solve_state.direction, &info, max_cost);
        tries += 1;
        if tries % (1e6 as u128) == 0 {
            let now = Instant::now();
            println!("Try {}, score: {}, speed: {}, path: {}", tries, next_game_state.cost, 1e6 / now.duration_since(last_print_time).as_secs_f64(), Direction::list2string(&next_game_state.path));
            last_print_time = now;
        }
        solve_states.push(updated_solve_state);

        match next_game_state.status {
            MapStatus::Dead => {
                dlog!("Died");
            },
            MapStatus::Won => {
                dlog!("Won");
                if best_win.is_none() {
                    best_win = Some(next_game_state);
                } else if best_win.as_ref().unwrap().cost > next_game_state.cost {
                    best_win = Some(next_game_state);
                }
            },
            MapStatus::Ongoing => {
                dlog!("Ongoing");

                if !solve_states.iter().any(
                    |earlier_state| {
                        earlier_state.game.state.eq(&next_game_state.state)
                    }
                ) {
                    solve_states.push(
                        SolverState {
                            game: next_game_state,
                            direction: Direction::None,
                        }
                    );
                }
            },
        }
    }

    if best_win.is_none() {
        return Err("No solution found".to_string());
    }
    let best = best_win.unwrap();
    println!("Best game, score: {}, path: {}", best.cost, Direction::list2string(&best.path));
    print_map(&best.state, &info);
    Ok(best)
}