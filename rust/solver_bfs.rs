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
    last_cost: u16,
    done_at_level_start: usize,
    prev_level_size: usize,
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
            last_cost: 0,
            done_at_level_start: 0,
            prev_level_size: 0,
            max_cost_or_par,
            pid,
            start_time: now,
            sys: System::new(),
            todo: BinaryHeap::with_capacity(8_000_000),
            won: Vec::new(),
        }
    }

    fn print_status(&mut self, cost: u16, chunk_size: usize) {
        self.sys.refresh_processes(ProcessesToUpdate::Some(&[self.pid]), true);
        let now = Instant::now();
        let done_len = self.done.len();
        let won = self.won.len().to_string().color(if self.won.is_empty() { Color::Blue } else { Color::Green });
        let max_cost = format!("/ {}", self.max_cost_or_par).bright_black();
        let memory = self.sys.process(self.pid).map(|p| p.memory() as f64).unwrap_or(f64::NAN) / (1 << 30) as f64;
        if chunk_size > 0 {
            let todo_len = self.todo.len();
            let duration_float = now.duration_since(self.last_print_time).as_secs_f64();
            let todo_delta = if todo_len < self.last_todo_len {
                format!("-{}", (self.last_todo_len - todo_len).to_formatted_string(&Locale::sv)).cyan()
            } else if todo_len - self.last_todo_len > chunk_size {
                format!("+{}", (todo_len - self.last_todo_len).to_formatted_string(&Locale::sv)).red()
            } else {
                format!("+{}", (todo_len - self.last_todo_len).to_formatted_string(&Locale::sv)).green()
            };
            let speed = chunk_size as f64 / duration_float;
            let min_eta = (todo_len as f64 / speed) as u64;
            if cost > self.last_cost {
                self.prev_level_size = done_len - self.done_at_level_start;
                self.done_at_level_start = done_len;
                self.last_cost = cost;
            }
            let level_progress = if self.prev_level_size > 0 {
                ((done_len - self.done_at_level_start) as f64 / self.prev_level_size as f64).min(1.0)
            } else {
                0.0
            };
            let levels_left = (self.max_cost_or_par as f64 - cost as f64 - level_progress).max(0.0);
            let max_eta = (todo_len as f64 * levels_left / speed) as u64;
            println!(
                "{}: {:>2} | {}: {:>11} ({:>7}) | {}: {:>11} | {}: {:>3} {} | {}: {:>9}.{:02} | {}: {:>5.2} GiB | {} {:>3}h{:>3}m{:>3}s | {} {:>3}h{:>3}m{:>3}s",
                "Won".yellow(),
                won,
                "Queued".yellow(),
                todo_len.to_formatted_string(&Locale::sv),
                todo_delta,
                "Done".yellow(),
                done_len.to_formatted_string(&Locale::sv),
                "Cost".yellow(),
                cost,
                max_cost,
                "Speed".yellow(),
                (speed as u128).to_formatted_string(&Locale::sv),
                (100f64 * speed) as u128 % 100,
                "Memory".yellow(),
                memory,
                "ETA >=".yellow(),
                min_eta / 3600,
                (min_eta % 3600) / 60,
                min_eta % 60,
                "ETA ~=".yellow(),
                max_eta / 3600,
                (max_eta % 3600) / 60,
                max_eta % 60,
            );
            self.last_todo_len = todo_len;
            self.last_print_time = now;
        } else {
            let duration = now.duration_since(self.start_time);
            let duration_full_sec = duration.as_secs();
            let duration_float = duration.as_secs_f64();
            println!(
                "{}: {:>2} | {}: {:>9} {:>13} | {}: {:>11} | {}: {:>3} {} | {}: {:>9}.{:02} | {}: {:>5.2} GiB | v1.0",
                "Won".yellow(),
                won,
                "Time".yellow(),
                duration_full_sec.to_formatted_string(&Locale::sv),
                format!("[{}h {:>2}m {:>2}s]", duration_full_sec / 3600, (duration_full_sec % 3600) / 60, duration_full_sec % 60).bright_black(),
                "Done".yellow(),
                done_len.to_formatted_string(&Locale::sv),
                "Cost".yellow(),
                cost,
                max_cost,
                "Speed".yellow(),
                ((done_len as f64 / duration_float) as u128).to_formatted_string(&Locale::sv),
                ((100 * done_len) as f64 / duration_float) as u128 % 100,
                "Memory".yellow(),
                memory
            );
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
            solver.print_status(game.cost, 10000);
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

    if let Some(best_ghost) = solver.won.iter().max() {
        let best = best_ghost.reproduce(start_state, &info);
        solver.print_status(best.cost, 0);
        println!("Best game, score: {}, path: {}", best.cost, Direction::list2string(&best.path));
        Ok(best)
    } else {
        solver.print_status(0, 0);
        Err("No solution found".to_string())
    }
}
