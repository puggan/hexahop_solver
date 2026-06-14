use crate::point::Point;
use regex::Regex;
use std::fmt;
use std::iter::repeat_n;
use std::str::FromStr;
use strum_macros::FromRepr;

#[derive(Debug, Clone, Copy, PartialEq, Eq, Hash, FromRepr)]
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

    pub fn next(&self) -> Option<Direction> {
        match self {
            Direction::None => Some(Direction::N),
            Direction::N => Some(Direction::NE),
            Direction::NE => Some(Direction::SE),
            Direction::SE => Some(Direction::S),
            Direction::S => Some(Direction::SW),
            Direction::SW => Some(Direction::NW),
            Direction::NW => Some(Direction::Jump),
            Direction::Jump => None,
        }
    }

    pub fn clockwise(&self) -> Direction {
        match self {
            Direction::N => Direction::NE,
            Direction::NE => Direction::SE,
            Direction::SE => Direction::S,
            Direction::S => Direction::SW,
            Direction::SW => Direction::NW,
            Direction::NW => Direction::N,
            Direction::None => Direction::None,
            Direction::Jump => Direction::Jump,
        }
    }

    pub fn counter_clockwise(&self) -> Direction {
        match self {
            Direction::N => Direction::NW,
            Direction::NW => Direction::SW,
            Direction::SW => Direction::S,
            Direction::S => Direction::SE,
            Direction::SE => Direction::NE,
            Direction::NE => Direction::N,
            Direction::None => Direction::None,
            Direction::Jump => Direction::Jump,
        }
    }

    pub fn dx(&self, length: i8) -> i8 {
        match self {
            Direction::NE | Direction::SE => length,
            Direction::NW | Direction::SW => -length,
            _ => 0,
        }
    }

    pub fn dy(&self, length: i8) -> i8 {
        match self {
            Direction::N | Direction::NE => -length,
            Direction::S | Direction::SW => length,
            _ => 0,
        }
    }

    pub fn offset(&self, length: i8) -> Point {
        Point::new(self.dx(length), self.dy(length))
    }

    pub fn special(&self) -> bool {
        matches!(self, Direction::None | Direction::Jump)
    }

    pub fn make_list(path_text: &str) -> Result<Vec<Direction>, &'static str> {
        let validate_full = Regex::new(r"^(([1-9][0-9]?)?([NnSs][EeWw]|[NnSsJj]),?\s*)+$").unwrap();
        let find_parts = Regex::new(r"([1-9][0-9]?)?([NnSs][EeWw]|[NnSsJj])").unwrap();

        if !validate_full.is_match(path_text) {
            return Err("Invalid path string");
        }

        let mut path = Vec::new();
        for part in find_parts.captures_iter(path_text) {
            let dir = part.get(2).ok_or("parse failed")?.as_str().parse::<Direction>().map_err(|_| "parse failed")?;
            let count = if let Some(count_match) = part.get(1) {
                count_match.as_str().parse::<usize>().map_err(|_| "Invalid number format")?
            } else {
                1
            };
            path.extend(repeat_n(dir, count));
        }
        Ok(path)
    }

    pub fn list2string_plain(path: &[Direction]) -> String
    {
        path.iter().map(|d| d.to_string()).collect::<Vec<String>>().join(",")
    }
    pub fn list2string(path: &[Direction]) -> String {
        let mut result = String::new();
        let mut chunks = path.iter().peekable();

        while let Some(dir) = chunks.next() {
            let mut count = 1;
            while chunks.peek() == Some(&dir) {
                count += 1;
                chunks.next();
            }
            // Add the number if > 1, then the direction
            if count > 1 {
                result.push_str(&count.to_string());
            }
            result.push_str(&dir.to_string());
            result.push(',');
        }
        result
    }
}
impl FromStr for Direction {
    type Err = ();
    fn from_str(s: &str) -> Result<Self, Self::Err> {
        match s {
            "N" | "n" => Ok(Direction::N),
            "NE" | "Ne" | "ne" => Ok(Direction::NE),
            "SE" | "Se" | "se" => Ok(Direction::SE),
            "S" | "s" => Ok(Direction::S),
            "SW" | "Sw" | "sw" => Ok(Direction::SW),
            "NW" | "Nw" | "nw" => Ok(Direction::NW),
            "J" | "j" => Ok(Direction::Jump),
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
