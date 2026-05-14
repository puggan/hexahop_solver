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

    pub fn make_list(path: &str) -> Result<Vec<Direction>, &'static str> {
        let validate_full = Regex::new(r"^(([NnSs][ErWw]|[1-7NnSsJj]),?\s*)+$").unwrap();
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
            "N" | "n" | "1" => Ok(Direction::N),
            "NE" | "ne" | "2" => Ok(Direction::NE),
            "SE" | "se" | "3" => Ok(Direction::SE),
            "S" | "s" | "4" => Ok(Direction::S),
            "SW" | "sw" | "5" => Ok(Direction::SW),
            "NW" | "nw" | "6" => Ok(Direction::NW),
            "J" | "j" | "7" => Ok(Direction::Jump),
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
