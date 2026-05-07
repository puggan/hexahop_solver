use crate::map::{MapInfo, MapState, MapStatus};

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
}

#[derive(Clone, Debug)]
pub struct GameState {
    pub state: MapState,
    pub path: Vec<Direction>,
    pub cost: u16,
}

impl GameState {
    pub fn new(state: MapState) -> Self {
        Self {
            state,
            path: Vec::new(),
            cost: 0,
        }
    }

    pub fn state(&self, map_info: MapInfo, current_cost: u16, max_cost: Option<u16>) -> MapStatus {
        self.state.state(&map_info, current_cost, max_cost)
    }

    pub fn get_path_hash(&self) -> Vec<u64> {
        let mut hashes = Vec::new();
        let mut hash: u64 = 0;
        let mut part_count = 0;
        for &dir in &self.path {
            hash = (hash << 3) | (dir as u64);
            part_count += 1;
            if (part_count >= 20) {
                hashes.push(hash);
                hash = 0;
                part_count = 0;
            }
        }
        hashes
    }
}