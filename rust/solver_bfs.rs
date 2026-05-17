use std::collections::BinaryHeap;
use std::collections::HashSet;
use std::time::Instant;
use colored::Color;
use colored::Colorize;
use num_format::Locale;
use num_format::ToFormattedString;
use sysinfo::ProcessesToUpdate;
use sysinfo::System;
use crate::direction::Direction;
use crate::map::MapState;
use crate::map::MapStatus;
use crate::map;
use crate::step::GameState;
use crate::step::GameStateGhost;
#[cfg(feature = "h128")]
use std::hash::Hash;
#[cfg(feature = "h128")]
use xxhash_rust::xxh3::Xxh3;

macro_rules! dlog {
    ($($arg:tt)*) => {
        if false { // Change this 'false' to 'true' to see logs
            println!($($arg)*);
        }
    };
}

#[cfg(feature = "h128")]
fn hash_map_state(mut hasher: &mut Xxh3, state: &MapState) -> u128 {
    hasher.reset();
    state.hash(&mut hasher);
    hasher.digest128()
}

struct Solver {
    done: HashSet<u64>,
    last_print_time: Instant,
    last_todo_len: usize,
    max_cost_or_par: u16,
    pid: sysinfo::Pid,
    start_time: Instant,
    sys: System,
    todo: BinaryHeap<GameStateGhost>,
    won: Vec<GameStateGhost>,
}

impl Solver {
    fn new(max_cost_or_par: u16, pid: sysinfo::Pid) -> Solver {
        let now = Instant::now();
        Solver {
            done: HashSet::with_capacity(8_000_000),
            last_print_time: now,
            last_todo_len: 0,
            max_cost_or_par,
            pid,
            start_time: now,
            sys: System::new(),
            todo: BinaryHeap::with_capacity(8_000_000),
            won: Vec::new(),
        }
    }
}

// Breadth-First Search (BFS)
pub fn run_solver(map_nr: usize, max_cost: &Option<u16>) -> Result<GameState, String> {
    let info = map::get(map_nr)?;
    let mut solver = Solver::new(
        max_cost.unwrap_or(info.par),
        sysinfo::get_current_pid()?
    );
    let start_state = GameState::new(MapState::load_lev(&info)?);
    solver.todo.push(start_state.ghost());
    #[cfg(feature = "h128")]
    let mut hasher = Xxh3::new();

    while solver.todo.len() > 0 {
        let game = solver.todo.pop().unwrap().reproduce(start_state.clone(), &info);
        dlog!("Testing game, score: {}, path: {}", game.cost, Direction::list2string(&game.path));
        #[cfg(not(feature = "h128"))]
        let hash = fxhash::hash64(&game.state);
        #[cfg(feature = "h128")]
        let hash = hash_map_state(&mut hasher, &game.state);
        if solver.done.contains(&hash) {
            dlog!("Duplicate");
            continue;
        }
        solver.done.insert(hash);

        if solver.done.len() % 10000 == 0 {
            solver.sys.refresh_processes(ProcessesToUpdate::Some(&[solver.pid]), true);
            let now = Instant::now();
            let todo_len = solver.todo.len();
            let duration_float = now.duration_since(solver.last_print_time).as_secs_f64();
            println!(
                "{}: {:>2} | {}: {:>11} ({:>7}) | {}: {:>11} | {}: {:>3} {} | {}: {:>9}.{:02} | {}: {:>5.2} GiB",
                "Won".yellow(),
                solver.won.len().to_string().color( if solver.won.is_empty() { Color::Blue } else { Color::Green } ),
                "Queued".yellow(),
                todo_len.to_formatted_string(&Locale::sv),
                if todo_len < solver.last_todo_len {
                    format!("-{}", (solver.last_todo_len - todo_len).to_formatted_string(&Locale::sv)).cyan()
                } else if todo_len - solver.last_todo_len > 10_000 {
                    format!("+{}", (todo_len - solver.last_todo_len).to_formatted_string(&Locale::sv)).red()
                } else {
                    format!("+{}", (todo_len - solver.last_todo_len).to_formatted_string(&Locale::sv)).green()
                },
                "Done".yellow(),
                solver.done.len().to_formatted_string(&Locale::sv),
                "Cost".yellow(),
                game.cost,
                format!("/ {}", solver.max_cost_or_par).bright_black(),
                "Speed".yellow(),
                ((1e4 / duration_float) as u128).to_formatted_string(&Locale::sv),
                (1e6 / duration_float) as u128 % 100,
                "Memory".yellow(),
                solver.sys.process(solver.pid).map(|p| p.memory() as f64).unwrap_or(f64::NAN) / (1 << 30) as f64
            );
            solver.last_todo_len = todo_len;
            solver.last_print_time = now;
        }

        let directions: &[Direction] = if game.state.jumps > 0 {
            &Direction::all()
        } else {
            &Direction::flat()
        };
        for dir in directions {
            let next_state = game.step(dir, &info, max_cost);
            match next_state.status {
                MapStatus::Won => {
                    dlog!("dir {} Won!", dir);
                    #[cfg(not(feature = "h128"))]
                    let hash = fxhash::hash64(&next_state.state);
                    #[cfg(feature = "h128")]
                    let hash = hash_map_state(&mut hasher, &next_state.state);
                    solver.done.insert(hash);
                    solver.won.push(next_state.ghost());
                }
                MapStatus::Dead => {
                    dlog!("dir {} Dead!", dir);
                }
                MapStatus::Ongoing => {
                    dlog!("dir {} queued", dir);
                    #[cfg(not(feature = "h128"))]
                    let hash = fxhash::hash64(&next_state.state);
                    #[cfg(feature = "h128")]
                    let hash = hash_map_state(&mut hasher, &next_state.state);
                    if !solver.done.contains(&hash) {
                        solver.todo.push(next_state.ghost());
                    }
                }
            }
        }
    }

    println!("won: {}, queued: {}, done: {}", solver.won.len(), solver.todo.len(), solver.done.len());
    let best = solver.won.into_iter().max().ok_or_else(|| "No solution found".to_string())?.reproduce(start_state.clone(), &info);
    println!("Best game, score: {}, path: {}", best.cost, Direction::list2string(&best.path));
    Ok(best.clone())
}