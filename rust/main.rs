use clap::Parser;
use clap::Subcommand;
use hexahop_solver::debug;
use hexahop_solver::solver;

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
        max_cost: Option<u16>,
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
            if max_cost.is_some() {
                println!("Solving Map {} with max cost limit: {}", map_nr, max_cost.unwrap());
            } else {
                println!("Solving Map {} with default max cost", map_nr);
            }
            solver::run_solver(*map_nr, max_cost)?;
        }
    }

    Ok(())
}