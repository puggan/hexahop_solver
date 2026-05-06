use clap::{Parser, Subcommand};
use hexahop_solver::debug;

#[derive(Parser)]
#[command(name = "hexahop")]
#[command(about = "Hexahop Solver and Debugger", long_about = None)]
struct Cli {
    #[command(subcommand)]
    command: Commands,
}

#[derive(Subcommand)]
enum Commands {
    /// Debug a specific map and path
    Debug {
        map_nr: usize,
        /// The bit-packed path
        path: Option<String>,
    },
    /// Start the A* solver for a map
    Solve {
        map_nr: usize,
        /// Max cost limit (optional)
        #[arg(short, long, default_value_t = 100)]
        max_cost: u32,
    },
}

fn main() -> Result<(), Box<dyn std::error::Error>> {
    let cli = Cli::parse();

    match &cli.command {
        Commands::Debug { map_nr, path } => {
            println!("Debugging Map {} with path {:?}", map_nr, path);
            debug::run_debug(*map_nr, path.clone())?;
        }
        Commands::Solve { map_nr, max_cost } => {
            println!("Solving Map {} with max cost limit: {}", map_nr, max_cost);
            // Call your solver logic from lib.rs
            return Err("The solver engine is not yet implemented.".into());
        }
    }

    Ok(())
}