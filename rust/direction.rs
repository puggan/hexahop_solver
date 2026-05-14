use regex::Regex;
use std::fmt;
use std::str::FromStr;

#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash)]
#[repr(u8)]
pub enum Direction {
    None = 0,
    N    = 1,
    NE   = 2,
    SE   = 3,
    S    = 4,
    SW   = 5,
    NW   = 6,
    Jump = 7,
}
impl Direction {
    pub fn all() -> [Direction; 7] {
        [Direction::N, Direction::NE, Direction::SE, Direction::S, Direction::SW, Direction::NW, Direction::Jump]
    }
    pub fn flat() -> [Direction; 6] {
        [Direction::N, Direction::NE, Direction::SE, Direction::S, Direction::SW, Direction::NW]
    }

    pub fn dx(&self) -> i8 {
        match self {
            Direction::NE | Direction::SE => 1,
            Direction::NW | Direction::SW => -1,
            _ => 0,
        }
    }

    pub fn dy(&self) -> i8 {
        match self {
            Direction::N | Direction::NE => -1,
            Direction::S | Direction::SW => 1,
            _ => 0,
        }
    }

    pub fn make_list(path: &str) -> Result<Vec<Direction>, &'static str> {
        let validate_full = Regex::new(r"^(([NnSs][EeWw]|[1-7NnSsJj]),?\s*)+$").unwrap();
        let find_parts = Regex::new(r"[NnSs][EeWw]|[1-7NnSsJj]").unwrap();

        if !validate_full.is_match(path) {
            return Err("Invalid path string");
        }

        find_parts
            .find_iter(path)
            .map(
                |mat| mat
                    .as_str()
                    .parse::<Direction>()
                    .map_err(|_| "Invalid direction"),
            )
            .collect()
    }

    pub fn list2string(path: &[Direction]) -> String
    {
        path.iter().map(|d| d.to_string()).collect::<Vec<String>>().join(",")
    }
}
impl FromStr for Direction {
    type Err = ();
    fn from_str(s: &str) -> Result<Self, Self::Err> {
        match s {
            "1" | "N" | "n" => Ok(Direction::N),
            "2" | "NE" | "Ne" | "ne" => Ok(Direction::NE),
            "3" | "SE" | "Se" | "se" => Ok(Direction::SE),
            "4" | "S" | "s" => Ok(Direction::S),
            "5" | "SW" | "Sw" | "sw" => Ok(Direction::SW),
            "6" | "NW" | "Nw" | "nw" => Ok(Direction::NW),
            "7" | "J" | "j" => Ok(Direction::Jump),
            _ => Err(()),
        }
    }
}

impl fmt::Display for Direction {
    fn fmt(&self, f: &mut fmt::Formatter) -> fmt::Result {
        let s = match self {
            Direction::None => panic!("Invalid direction"),
            Direction::N    => "N",
            Direction::NE   => "NE",
            Direction::SE   => "SE",
            Direction::S    => "S",
            Direction::SW   => "SW",
            Direction::NW   => "NW",
            Direction::Jump => "J",
        };
        write!(f, "{}", s)
    }
}
